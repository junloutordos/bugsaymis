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
        $plantillaNumbers = $data['plantilla_numbers'] ?? [];
        unset($data['plantilla_numbers']);

        return DB::transaction(function () use ($data, $plantillaNumbers) {
            $item = JobItem::create([
                ...$data,
                'created_by' => Auth::id(),
                'status'     => 'draft',
            ]);

            $this->syncPlantillaNumbers($item, $plantillaNumbers);

            AuditLogger::logModelEvent($item, 'created');

            return $item;
        });
    }

    /**
     * Update an existing job item.
     */
    public function update(JobItem $item, array $data): JobItem
    {
        $plantillaNumbers = $data['plantilla_numbers'] ?? null;
        unset($data['plantilla_numbers']);

        return DB::transaction(function () use ($item, $data, $plantillaNumbers) {
            $item->update($data);

            if ($plantillaNumbers !== null) {
                $this->syncPlantillaNumbers($item, $plantillaNumbers);
            }

            AuditLogger::logModelEvent($item, 'updated');
            return $item->fresh();
        });
    }

    /**
     * Sync the job item's plantilla item numbers to match the submitted list.
     * Removing a number that is already "filled" is blocked by StoreJobItemRequest
     * before this is ever called, so any row missing here is safe to drop.
     */
    private function syncPlantillaNumbers(JobItem $item, array $numbers): void
    {
        $numbers = array_values(array_filter($numbers));
        $existing = $item->plantillaNumbers()->pluck('plantilla_item_no', 'id');

        foreach ($existing as $id => $existingNo) {
            if (! in_array($existingNo, $numbers, true)) {
                $item->plantillaNumbers()->where('id', $id)->delete();
            }
        }

        $current = $item->plantillaNumbers()->pluck('plantilla_item_no')->all();
        foreach ($numbers as $number) {
            if (! in_array($number, $current, true)) {
                $item->plantillaNumbers()->create(['plantilla_item_no' => $number]);
            }
        }
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
