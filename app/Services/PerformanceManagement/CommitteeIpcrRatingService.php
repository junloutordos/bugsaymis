<?php

namespace App\Services\PerformanceManagement;

use App\Models\EmployeeFunction;
use App\Models\FacultyLoading\FacultyCommitteeAssignment;
use App\Models\IPCRRatingPeriod;
use App\Models\IPCRV2\IpcrV2Record;
use App\Models\IPCRV2\IpcrV2SupportItem;
use Illuminate\Support\Collection;

/**
 * Bridges a term-scoped FacultyCommitteeAssignment to the IPCR V2 Support
 * Item(s) it materialized into — the same `committee_assignment:{id}`
 * sync_source_key EmployeeFunctionSyncService already writes is the only
 * linkage; no new column needed.
 */
class CommitteeIpcrRatingService
{
    public function resolveSupportItems(FacultyCommitteeAssignment $assignment): Collection
    {
        $period = IPCRRatingPeriod::current()->first();
        if (! $period) {
            return collect();
        }

        $record = IpcrV2Record::where('user_id', $assignment->user_id)
            ->where('rating_period_id', $period->id)
            ->first();
        if (! $record) {
            return collect();
        }

        $function = EmployeeFunction::where('sync_source_key', 'committee_assignment:' . $assignment->id)->first();
        if (! $function) {
            return collect();
        }

        return IpcrV2SupportItem::where('ipcr_v2_id', $record->id)
            ->where('employee_function_id', $function->id)
            ->get();
    }

    public function isCommitteeSourced(IpcrV2SupportItem $item): bool
    {
        $sourceKey = $item->employeeFunction?->sync_source_key;

        return $sourceKey !== null && str_starts_with($sourceKey, 'committee_assignment:');
    }
}
