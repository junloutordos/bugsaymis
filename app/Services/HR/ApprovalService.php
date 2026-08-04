<?php

namespace App\Services\HR;

use App\Enums\ApprovalStep;
use App\Models\HR\LeaveApplication;
use App\Models\User;
use App\Services\HR\DTRService;
use App\Services\NotificationService;
use App\Services\PerformanceManagement\IPCRWorkflowService;
use App\Services\SnapshotService;
use Illuminate\Support\Facades\DB;

/**
 * ApprovalService (Leave)
 *
 * Handles the leave approval workflow and writes immutable
 * ApprovalSnapshot records via SnapshotService.
 *
 * Stage 1 — hr_officer        : certified   | rejected  (pending       → hr_verified)
 * Stage 2 — academic_unit_head: recommended | rejected  (hr_verified   → auh_verified)
 *   (CID teaching faculty only — skipped for everyone else)
 * Stage 3 — division_chief    : forwarded   | rejected  (hr_verified/auh_verified → forwarded)
 * Stage 4 — campus_director   : approved    | rejected  (forwarded     → approved)
 */
class ApprovalService
{
    public function __construct(
        private LeaveCreditService    $credits,
        private SnapshotService       $snapshots,
        private DTRService            $dtr,
        private IPCRWorkflowService   $ipcrWorkflow,
    ) {}

    /**
     * Process a leave approval action for any of the workflow stages.
     *
     * Writes the approval snapshot inside the same DB transaction so the
     * snapshot is always in sync with the FK columns on leave_applications.
     *
     * @param  LeaveApplication  $application
     * @param  string            $stage    hr_officer | academic_unit_head | division_chief | campus_director
     * @param  string            $action   certified | recommended | forwarded | approved | rejected
     * @param  string            $remarks
     * @param  User              $approver
     */
    public function processLeave(
        LeaveApplication $application,
        string           $stage,
        string           $action,
        string           $remarks,
        User             $approver,
    ): void {
        DB::transaction(function () use ($application, $stage, $action, $remarks, $approver) {
            $requiresAuh = $application->user
                ? $this->ipcrWorkflow->requiresLeaveAuhRecommendation($application->user)
                : false;

            // Enforce the serial workflow — a stage can only be processed while
            // the application is sitting at the status that stage is meant to act on.
            // Without this, the "Review Stage" selector lets any authorized approver
            // skip stages (e.g. jump straight to Campus Director on a still-pending app).
            $expectedStatus = [
                'hr_officer'         => 'pending',
                'academic_unit_head' => 'hr_verified',
                'division_chief'     => $requiresAuh ? 'auh_verified' : 'hr_verified',
                'campus_director'    => 'forwarded',
            ][$stage] ?? null;

            abort_unless(
                $expectedStatus !== null && $application->status === $expectedStatus,
                409,
                "This application is not currently awaiting the {$stage} stage (current status: {$application->status})."
            );

            if ($stage === 'academic_unit_head') {
                abort_unless($requiresAuh, 409, 'This application does not require Academic Unit Head recommendation.');
            }

            switch ($stage) {

                case 'hr_officer':
                    abort_unless(in_array($action, ['certified', 'rejected']), 422);

                    $newStatus = 'rejected';
                    if ($action === 'certified') {
                        // Division Chiefs have no DC above them — skip straight to
                        // the Campus Director / OCD stage.
                        $newStatus = $application->user?->hasRole('DivisionChief')
                            ? 'forwarded'
                            : 'hr_verified';
                    }

                    $application->update([
                        'hr_officer_id'      => $approver->id,
                        'hr_officer_action'  => $action,
                        'hr_officer_at'      => now(),
                        'hr_officer_remarks' => $remarks,
                        'status'             => $newStatus,
                    ]);
                    $this->snapshots->recordApproval(
                        approvable: $application,
                        step:       ApprovalStep::LEAVE_HR_OFFICER,
                        sequence:   1,
                        action:     $action,
                        approver:   $approver,
                        remarks:    $remarks,
                    );
                    break;

                case 'academic_unit_head':
                    abort_unless(in_array($action, ['recommended', 'rejected']), 422);
                    $application->update([
                        'auh_id'      => $approver->id,
                        'auh_action'  => $action,
                        'auh_at'      => now(),
                        'auh_remarks' => $remarks,
                        'status'      => $action === 'recommended' ? 'auh_verified' : 'rejected',
                    ]);
                    $this->snapshots->recordApproval(
                        approvable: $application,
                        step:       ApprovalStep::LEAVE_ACADEMIC_UNIT_HEAD,
                        sequence:   2,
                        action:     $action,
                        approver:   $approver,
                        remarks:    $remarks,
                    );
                    if ($action === 'rejected') {
                        $this->credits->restoreLeaveCredits($application->id, $approver->id);
                    }
                    break;

                case 'division_chief':
                    abort_unless(in_array($action, ['forwarded', 'rejected']), 422);
                    $application->update([
                        'division_chief_id'      => $approver->id,
                        'division_chief_action'  => $action,
                        'division_chief_at'      => now(),
                        'division_chief_remarks' => $remarks,
                        'status'                 => $action,
                    ]);
                    $this->snapshots->recordApproval(
                        approvable: $application,
                        step:       ApprovalStep::LEAVE_DIVISION_CHIEF,
                        sequence:   3,
                        action:     $action,
                        approver:   $approver,
                        remarks:    $remarks,
                    );
                    if ($action === 'rejected') {
                        $this->credits->restoreLeaveCredits($application->id, $approver->id);
                    }
                    break;

                case 'campus_director':
                    abort_unless(in_array($action, ['approved', 'rejected']), 422);
                    $application->update([
                        'approved_by'      => $approver->id,
                        'approval_action'  => $action,
                        'approved_at'      => now(),
                        'approval_remarks' => $remarks,
                        'status'           => $action,
                    ]);
                    $this->snapshots->recordApproval(
                        approvable: $application,
                        step:       ApprovalStep::LEAVE_CAMPUS_DIRECTOR,
                        sequence:   4,
                        action:     $action,
                        approver:   $approver,
                        remarks:    $remarks,
                    );
                    if ($action === 'approved') {
                        $this->credits->applyLeaveDeduction($application->id, $approver->id);
                        $this->dtr->applyLeaveToExistingDtrRecords($application);
                    } else {
                        $this->credits->restoreLeaveCredits($application->id, $approver->id);
                    }
                    // Push notification to applicant on final decision
                    if ($application->user) {
                        NotificationService::notifyUser(
                            $application->user,
                            'Leave Application',
                            "Leave #{$application->id}",
                            $action === 'approved' ? 'Approved by Campus Director' : 'Rejected by Campus Director',
                            route('hr.leave.index'),
                            $remarks,
                        );
                    }
                    break;
            }
        });
    }
}
