<?php

namespace App\Services\Procurement;

use App\Enums\ApprovalStep;
use App\Models\Procurement;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\SnapshotService;
use Illuminate\Support\Facades\DB;

class PRService
{
    public function __construct(private SnapshotService $snapshots) {}

    public function submit(Procurement $pr, User $requester): void
    {
        DB::transaction(function () use ($pr, $requester) {
            // If supplemental and not yet approved, start supplemental track
            if ($pr->is_supplemental && $pr->supplemental_status !== 'ocd_approved') {
                $pr->update([
                    'status'               => 'supplemental_pending_dc',
                    'supplemental_status'  => 'pending',
                ]);
            } else {
                $pr->update(['status' => 'pending_dc']);
            }
        });

        $this->notifyDivisionChiefs($pr, $requester);
    }

    public function dcSign(Procurement $pr, User $dc, string $remarks = ''): void
    {
        DB::transaction(function () use ($pr, $dc, $remarks) {
            if ($pr->status === 'supplemental_pending_dc') {
                $pr->update([
                    'supplemental_status' => 'dc_eu_signed',
                    'status'              => 'supplemental_pending_bo',
                    'division_chief_id'   => $dc->id,
                    'division_chief_at'   => now(),
                    'division_chief_remarks' => $remarks,
                ]);
                $this->snapshots->recordApproval($pr, ApprovalStep::PR_SUPP_DIVISION_CHIEF, 1, 'approved', $dc, $remarks);
                $this->notifyBudgetOfficers($pr, $dc);
            } else {
                $pr->update([
                    'status'             => 'pending_procurement',
                    'division_chief_id'  => $dc->id,
                    'division_chief_at'  => now(),
                    'division_chief_remarks' => $remarks,
                ]);
                $this->snapshots->recordApproval($pr, ApprovalStep::PR_DIVISION_CHIEF, 1, 'approved', $dc, $remarks);
                $this->notifyProcurementOfficers($pr, $dc);
            }
        });

        NotificationService::notifyUser(
            $pr->requester,
            'Purchase Request',
            $pr->pr_no,
            'Signed by Division Chief',
            route('procurements.show', $pr->id),
        );
    }

    public function boInitial(Procurement $pr, User $bo): void
    {
        DB::transaction(function () use ($pr, $bo) {
            $pr->update([
                'supplemental_status'              => 'bo_initialed',
                'status'                           => 'supplemental_pending_ocd',
                'supplemental_budget_officer_id'   => $bo->id,
                'supplemental_budget_officer_at'   => now(),
            ]);
            $this->snapshots->recordApproval($pr, ApprovalStep::PR_SUPP_BUDGET_OFFICER, 2, 'approved', $bo);
        });

        $this->notifyOcdUsers($pr, $bo);
    }

    public function supplementalOcdSign(Procurement $pr, User $ocd): void
    {
        DB::transaction(function () use ($pr, $ocd) {
            $pr->update([
                'supplemental_status' => 'ocd_approved',
                'status'              => 'pending_dc',
            ]);
            $this->snapshots->recordApproval($pr, ApprovalStep::PR_SUPP_OCD, 3, 'approved', $ocd);
        });

        $this->notifyDivisionChiefs($pr, $ocd);
    }

    public function assignNumber(Procurement $pr, User $officer, string $prNumber): void
    {
        DB::transaction(function () use ($pr, $officer, $prNumber) {
            $pr->update([
                'status'                 => 'pending_ocd',
                'procurement_officer_id' => $officer->id,
                'procurement_officer_at' => now(),
                'assigned_pr_number'     => $prNumber,
            ]);
            $this->snapshots->recordApproval($pr, ApprovalStep::PR_PROCUREMENT_OFFICER, 2, 'approved', $officer);
        });

        $this->notifyOcdUsers($pr, $officer);
        NotificationService::notifyUser(
            $pr->requester,
            'Purchase Request',
            $pr->pr_no,
            "PR number assigned: {$prNumber}",
            route('procurements.show', $pr->id),
        );
    }

    public function ocdSign(Procurement $pr, User $ocd, string $remarks = ''): void
    {
        DB::transaction(function () use ($pr, $ocd, $remarks) {
            $pr->update([
                'status'     => 'approved',
                'ocd_id'     => $ocd->id,
                'ocd_at'     => now(),
                'ocd_remarks' => $remarks,
            ]);
            $this->snapshots->recordApproval($pr, ApprovalStep::PR_OCD, 3, 'approved', $ocd, $remarks);
        });

        NotificationService::notifyUser(
            $pr->requester,
            'Purchase Request',
            $pr->pr_no,
            'Approved by Campus Director',
            route('procurements.show', $pr->id),
        );
    }

    public function reject(Procurement $pr, User $actor, string $reason): void
    {
        DB::transaction(function () use ($pr, $actor, $reason) {
            $pr->update([
                'status'           => 'rejected',
                'rejected_by'      => $actor->id,
                'rejected_at'      => now(),
                'rejection_reason' => $reason,
            ]);
            $this->snapshots->recordApproval($pr, "pr_rejected_by_{$actor->id}", 99, 'rejected', $actor, $reason);
        });

        NotificationService::notifyUser(
            $pr->requester,
            'Purchase Request',
            $pr->pr_no,
            'Rejected',
            route('procurements.show', $pr->id),
            $reason,
        );
    }

    // ── Notification helpers ──────────────────────────────────────────────────

    private function notifyDivisionChiefs(Procurement $pr, User $sender): void
    {
        $chiefs = User::havingRole('DivisionChief')->get();
        foreach ($chiefs as $chief) {
            NotificationService::notifyUser(
                $chief, 'Purchase Request', $pr->pr_no,
                'Awaiting your signature',
                route('procurements.show', $pr->id),
            );
        }
    }

    private function notifyProcurementOfficers(Procurement $pr, User $sender): void
    {
        $officers = User::havingRole('Procurement Officer')->get();
        foreach ($officers as $officer) {
            NotificationService::notifyUser(
                $officer, 'Purchase Request', $pr->pr_no,
                'Ready for PR numbering',
                route('procurements.show', $pr->id),
            );
        }
    }

    private function notifyBudgetOfficers(Procurement $pr, User $sender): void
    {
        $officers = User::havingRole('Budget Officer')->get();
        foreach ($officers as $officer) {
            NotificationService::notifyUser(
                $officer, 'Purchase Request', $pr->pr_no,
                'Supplemental – awaiting your initial',
                route('procurements.show', $pr->id),
            );
        }
    }

    private function notifyOcdUsers(Procurement $pr, User $sender): void
    {
        $ocds = User::havingRole('OCD')->get();
        foreach ($ocds as $ocd) {
            NotificationService::notifyUser(
                $ocd, 'Purchase Request', $pr->pr_no,
                'Awaiting Campus Director signature',
                route('procurements.show', $pr->id),
            );
        }
    }
}
