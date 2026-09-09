<?php

namespace App\Services\PerformanceManagement;

use App\Models\EmployeeFunction;
use App\Models\FacultyLoading\FacultyCommitteeAssignment;
use App\Models\IPCRRatingPeriod;
use App\Models\IPCRV2\IpcrV2Record;
use App\Models\IPCRV2\IpcrV2SupportItem;
use App\Services\IPCRV2\IpcrV2WorkflowService;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

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

    private const NOT_YET_RATABLE_STATUSES = [
        IpcrV2WorkflowService::STATUS_NEW_TARGET,
        IpcrV2WorkflowService::STATUS_FOR_REVIEW,
        IpcrV2WorkflowService::STATUS_RETURNED,
    ];

    public function rate(IpcrV2SupportItem $item, array $data): IpcrV2SupportItem
    {
        $record = $item->ipcr()->firstOrFail();

        if (in_array($record->status, self::NOT_YET_RATABLE_STATUSES, true)) {
            throw ValidationException::withMessages([
                'status' => 'This member\'s IPCR V2 targets are not yet approved — rating is not available until then.',
            ]);
        }

        if (! $record->isMutable()) {
            throw ValidationException::withMessages([
                'status' => 'This IPCR V2 record is finalized or its rating period is closed and can no longer be rated.',
            ]);
        }

        $rowAverage = round(($data['quality_rating'] + $data['efficiency_rating'] + $data['timeliness_rating']) / 3, 2);

        $item->update([
            'quality_rating' => $data['quality_rating'],
            'efficiency_rating' => $data['efficiency_rating'],
            'timeliness_rating' => $data['timeliness_rating'],
            'row_average' => $rowAverage,
            'actual_accomplishment' => $data['accomplishment'] ?? $item->actual_accomplishment,
            'mov_link' => $data['mov_link'] ?? $item->mov_link,
        ]);

        return $item->fresh();
    }
}
