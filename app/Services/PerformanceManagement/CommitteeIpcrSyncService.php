<?php

namespace App\Services\PerformanceManagement;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\FacultyCommitteeAssignment;
use App\Models\IPCRRatingPeriod;
use App\Models\IPCRV2\IpcrV2Record;
use App\Models\User;
use App\Services\EmployeeFunctionSyncService;
use App\Services\IPCRV2\IpcrV2GenerationService;

/**
 * Keeps a member's committee-sourced Employee Function / IPCR V2 Support
 * Item state current whenever a committee assignment changes, instead of
 * relying on HR's manual "Sync from Faculty Loading" button
 * (EmployeeFunctionSyncService itself is unchanged — this only decides
 * WHEN to call it, plus the one further step it never took: materializing
 * into an IPCR V2 record that already exists).
 */
class CommitteeIpcrSyncService
{
    public function __construct(
        private EmployeeFunctionSyncService $functions = new EmployeeFunctionSyncService(),
        private IpcrV2GenerationService $generation = new IpcrV2GenerationService(),
    ) {}

    public function syncForUser(User $user): void
    {
        $this->functions->syncFromFacultyLoading($user);

        $period = IPCRRatingPeriod::current()->first();
        if (! $period) {
            return;
        }

        $record = IpcrV2Record::where('user_id', $user->id)
            ->where('rating_period_id', $period->id)
            ->first();

        if ($record) {
            $this->generation->syncNewFunctions($record);
        }
    }

    /**
     * Syncs every currently-active member of one committee (not its
     * sub-committees — callers loop those explicitly). Takes a bare id
     * rather than a Committee instance on purpose — this method is called
     * from code paths using either of the two model classes that map to
     * the `committees` table (`App\Models\Committee` and
     * `App\Models\FacultyLoading\Committee`), and a strict type-hint on
     * either one would reject the other.
     */
    public function syncForCommittee(int $committeeId): void
    {
        $term = AcademicTerm::where('is_current', true)->first();
        if (! $term) {
            return;
        }

        $userIds = FacultyCommitteeAssignment::where('committee_id', $committeeId)
            ->where('academic_term_id', $term->id)
            ->where('status', 'active')
            ->pluck('user_id');

        foreach (User::whereIn('id', $userIds)->get() as $user) {
            $this->syncForUser($user);
        }
    }
}
