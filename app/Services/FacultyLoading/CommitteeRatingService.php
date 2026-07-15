<?php

namespace App\Services\FacultyLoading;

use App\Models\EmployeeIPCR;
use App\Models\FacultyLoading\FacultyCommitteeAccomplishment;
use App\Models\FacultyLoading\FacultyCommitteeAssignment;
use App\Services\PerformanceManagement\IPCRWorkflowService;

/**
 * Single write path for committee accomplishments and supervisor ratings —
 * used by both Faculty Loading's CommitteeAssignmentController and
 * PerformanceManagement's CommitteePerformanceController, which previously
 * wrote to two different places (faculty_committee_accomplishments vs the
 * EmployeeIPCR plan pivot directly) and could disagree with each other.
 *
 * Callers are responsible for their own authorization gate (FL's
 * hierarchical chair check vs PMS's head/sub-head check differ) — this
 * service only owns the storage + IPCR-mirror rules, which must be
 * identical regardless of which module triggered them.
 */
class CommitteeRatingService
{
    public function __construct(private readonly IPCRWorkflowService $workflow) {}

    /**
     * Member saves their own accomplishment text for a plan/period.
     * Requires a resolvable, mutable IPCR carrying that plan (matches PMS's
     * pre-existing gate) — FL's own path previously had no such check at all.
     */
    public function saveAccomplishment(FacultyCommitteeAssignment $assignment, array $data): FacultyCommitteeAccomplishment
    {
        $planId   = $data['work_distribution_plan_id'];
        $periodId = $data['rating_period_id'] ?? null;

        $ipcr = $this->resolveIpcr($assignment->user_id, $planId, $periodId, $data['ipcr_id'] ?? null);
        abort_unless($ipcr, 404, 'This plan is not in the member\'s IPCR for the selected period.');

        $this->workflow->assertMutable($ipcr);

        $accomplishment = FacultyCommitteeAccomplishment::updateOrCreate(
            [
                'faculty_committee_assignment_id' => $assignment->id,
                'work_distribution_plan_id'       => $planId,
                'rating_period_id'                => $periodId,
            ],
            [
                'accomplishment' => $data['accomplishment'] ?? null,
                'mov_link'       => $data['mov_link'] ?? null,
            ]
        );

        $ipcr->plans()->updateExistingPivot($planId, [
            'accomplishment' => $data['accomplishment'] ?? null,
            'mov_link'       => $data['mov_link'] ?? null,
        ]);

        return $accomplishment;
    }

    /**
     * Chairperson/head rates a member's accomplishment for a plan/period.
     * Requires the member's IPCR for that period to be "Submitted for Rating"
     * (and mutable) — this gate was previously only enforced on the PMS path;
     * applying it uniformly here closes that inconsistency.
     */
    public function rate(FacultyCommitteeAssignment $assignment, array $data): FacultyCommitteeAccomplishment
    {
        $planId   = $data['work_distribution_plan_id'];
        $periodId = $data['rating_period_id'] ?? null;

        $ipcr = $this->resolveIpcr($assignment->user_id, $planId, $periodId, $data['ipcr_id'] ?? null);
        abort_unless($ipcr, 404, 'This plan is not in the member\'s IPCR for the selected period.');

        $this->workflow->assertMutable($ipcr);

        abort_if(
            $ipcr->status !== 'Submitted for Rating',
            403,
            'Ratings can only be edited while the IPCR is "Submitted for Rating".'
        );

        $ratings = collect([
            $data['sup_quality'] ?? null,
            $data['sup_efficiency'] ?? null,
            $data['sup_timeliness'] ?? null,
        ])->filter(fn ($v) => ! is_null($v));

        $supAverage = $ratings->count() ? round($ratings->avg(), 2) : null;

        $accomplishment = FacultyCommitteeAccomplishment::updateOrCreate(
            [
                'faculty_committee_assignment_id' => $assignment->id,
                'work_distribution_plan_id'       => $planId,
                'rating_period_id'                => $periodId,
            ],
            [
                'accomplishment' => $data['accomplishment'] ?? null,
                'mov_link'       => $data['mov_link'] ?? null,
                'sup_quality'    => $data['sup_quality'] ?? null,
                'sup_efficiency' => $data['sup_efficiency'] ?? null,
                'sup_timeliness' => $data['sup_timeliness'] ?? null,
                'sup_average'    => $supAverage,
            ]
        );

        // Mirror into the IPCR plan pivot so both the committee views and the
        // IPCR itself always show the same rating — the chair/head only rates once.
        $ipcrPivot = [
            'sup_quality'    => $data['sup_quality'] ?? null,
            'sup_efficiency' => $data['sup_efficiency'] ?? null,
            'sup_timeliness' => $data['sup_timeliness'] ?? null,
            'sup_average'    => $supAverage,
        ];
        if (! empty($data['accomplishment'])) {
            $ipcrPivot['accomplishment'] = $data['accomplishment'];
        }
        if (! empty($data['mov_link'])) {
            $ipcrPivot['mov_link'] = $data['mov_link'];
        }
        $ipcr->plans()->updateExistingPivot($planId, $ipcrPivot);

        return $accomplishment;
    }

    /**
     * Resolve the member's IPCR carrying this plan/period. PMS's UI already
     * knows the exact IPCR id (from the period row it rendered) and passes
     * it explicitly; FL's UI doesn't enumerate IPCRs client-side, so it
     * falls back to the same plan+period lookup FL always used.
     */
    private function resolveIpcr(int $userId, int $planId, ?int $periodId, ?int $ipcrId): ?EmployeeIPCR
    {
        if ($ipcrId) {
            return EmployeeIPCR::where('id', $ipcrId)
                ->where('user_id', $userId)
                ->whereHas('plans', fn ($q) => $q->where('work_distribution_plans.id', $planId))
                ->first();
        }

        return EmployeeIPCR::where('user_id', $userId)
            ->whereHas('plans', fn ($q) => $q->where('work_distribution_plans.id', $planId))
            ->when($periodId, fn ($q, $pid) => $q->where('rating_period_id', $pid))
            ->latest()
            ->first();
    }
}
