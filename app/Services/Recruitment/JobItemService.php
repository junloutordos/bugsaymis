<?php

namespace App\Services\Recruitment;

use App\Models\JobItem;
use App\Models\JobVacancy;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class JobItemService
{
    /**
     * Create a new job item (position).
     */
    public function create(array $data): JobItem
    {
        return DB::transaction(function () use ($data) {
            $item = JobItem::create([
                ...$data,
                'created_by' => Auth::id(),
                'status'     => 'draft',
            ]);

            AuditLogger::logModelEvent($item, 'created');

            return $item;
        });
    }

    /**
     * Update an existing job item.
     */
    public function update(JobItem $item, array $data): JobItem
    {
        return DB::transaction(function () use ($item, $data) {
            $item->update($data);
            AuditLogger::logModelEvent($item, 'updated');
            return $item->fresh();
        });
    }

    /**
     * Transition item status (draft → approved → published → closed).
     */
    public function changeStatus(JobItem $item, string $newStatus): JobItem
    {
        $allowed = ['draft', 'approved', 'published', 'closed'];

        if (! in_array($newStatus, $allowed)) {
            throw new \InvalidArgumentException("Invalid status: {$newStatus}");
        }

        return DB::transaction(function () use ($item, $newStatus) {
            $item->update(['status' => $newStatus]);
            AuditLogger::logModelEvent($item, 'updated');
            return $item->fresh();
        });
    }

    /**
     * Publish a job item and create a vacancy posting.
     */
    public function publish(JobItem $item, array $vacancyData): JobVacancy
    {
        return DB::transaction(function () use ($item, $vacancyData) {
            // Close any existing open vacancies for this item
            $item->jobVacancies()->where('status', 'open')->update(['status' => 'closed']);

            $item->update(['status' => 'published']);

            $vacancy = JobVacancy::create([
                'job_item_id'      => $item->id,
                'posting_date'     => $vacancyData['posting_date'],
                'closing_date'     => $vacancyData['closing_date'],
                'publication_type' => $vacancyData['publication_type'] ?? 'internal',
                'status'           => 'open',
            ]);

            AuditLogger::logModelEvent($item, 'updated');
            AuditLogger::logModelEvent($vacancy, 'created');

            return $vacancy->load('jobItem');
        });
    }

    /**
     * Soft-delete a job item (only if no active applications exist).
     */
    public function delete(JobItem $item): void
    {
        $hasApplications = $item->jobVacancies()
            ->whereHas('applications', fn ($q) => $q->active())
            ->exists();

        if ($hasApplications) {
            throw new \RuntimeException('Cannot delete a job item with active applications.');
        }

        DB::transaction(function () use ($item) {
            $item->jobVacancies()->update(['status' => 'cancelled']);
            $item->delete();
            AuditLogger::logModelEvent($item, 'deleted');
        });
    }
}
