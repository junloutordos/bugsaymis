<?php

namespace App\Services\Recruitment;

use App\Mail\ApplicationPlacedMail;
use App\Models\Application;
use App\Models\JobItemPlantillaNumber;
use App\Models\OnboardingRequirement;
use App\Models\OnboardingTask;
use App\Models\Placement;
use App\Models\Selection;
use App\Services\AuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class OnboardingService
{
    /**
     * Approve selection and create a placement record.
     * Generates onboarding tasks from the type's requirements automatically.
     */
    public function approveAndPlace(Application $application, array $placementData, int $approvedBy): Placement
    {
        return DB::transaction(function () use ($application, $placementData, $approvedBy) {
            // Record selection approval
            $selection = Selection::updateOrCreate(
                ['application_id' => $application->id],
                [
                    'approved_by'     => $approvedBy,
                    'approval_status' => 'approved',
                    'approval_date'   => now()->toDateString(),
                ]
            );

            // Create placement
            $placement = Placement::create([
                'application_id'   => $application->id,
                'assigned_office_id'=> $placementData['assigned_office_id']
                                       ?? $application->jobVacancy->jobItem->office_id,
                'start_date'       => $placementData['start_date'],
                'end_date'         => $placementData['end_date'] ?? null,
                'status'           => 'pending',
                'remarks'          => $placementData['remarks'] ?? null,
            ]);

            if ($plantillaNumberId = $placementData['plantilla_number_id'] ?? null) {
                JobItemPlantillaNumber::where('id', $plantillaNumberId)
                    ->update(['status' => 'filled', 'placement_id' => $placement->id]);
            }

            // Auto-generate tasks from onboarding requirements for this type
            $this->generateTasks($placement, $application->recruitment_type_id);

            // Advance application stage
            $application->update(['current_stage' => 'placement']);

            AuditLogger::logModelEvent($selection, 'updated');
            AuditLogger::logModelEvent($placement, 'created');

            $loaded = $placement->load(['onboardingTasks', 'office', 'application.applicant', 'application.jobVacancy.jobItem']);

            $applicant = $loaded->application?->applicant;
            if ($applicant?->email) {
                Mail::to($applicant->email)->queue(new ApplicationPlacedMail($loaded));
            }

            return $loaded;
        });
    }

    /**
     * Disapprove selection.
     */
    public function disapprove(Application $application, int $disapprovedBy, string $reason): Selection
    {
        return DB::transaction(function () use ($application, $disapprovedBy, $reason) {
            $selection = Selection::updateOrCreate(
                ['application_id' => $application->id],
                [
                    'approved_by'        => $disapprovedBy,
                    'approval_status'    => 'disapproved',
                    'approval_date'      => now()->toDateString(),
                    'disapproval_reason' => $reason,
                ]
            );

            $application->update([
                'current_stage' => 'rejected',
                'remarks'       => $reason,
            ]);

            AuditLogger::logModelEvent($selection, 'updated');

            return $selection;
        });
    }

    /**
     * Generate onboarding tasks from the requirement templates for a recruitment type.
     */
    public function generateTasks(Placement $placement, int $recruitmentTypeId): Collection
    {
        $requirements = OnboardingRequirement::where('recruitment_type_id', $recruitmentTypeId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $tasks = collect();

        foreach ($requirements as $req) {
            $task = OnboardingTask::create([
                'placement_id' => $placement->id,
                'task_name'    => $req->requirement_name,
                'description'  => $req->description,
                'assigned_to'  => null,      // HR staff assigns later
                'due_date'     => null,
                'status'       => 'pending',
            ]);

            $tasks->push($task);
        }

        return $tasks;
    }

    /**
     * Mark a single onboarding task as complete.
     */
    public function completeTask(OnboardingTask $task, string $notes = ''): OnboardingTask
    {
        return DB::transaction(function () use ($task, $notes) {
            $task->update([
                'status'           => 'completed',
                'completion_notes' => $notes,
                'completed_at'     => now(),
            ]);

            // Check if all required tasks for this placement are done → activate placement
            $this->checkAndActivatePlacement($task->placement);

            AuditLogger::logModelEvent($task, 'updated');

            return $task->fresh();
        });
    }

    /**
     * Activate placement when all tasks are completed (or skipped).
     */
    private function checkAndActivatePlacement(Placement $placement): void
    {
        $incomplete = OnboardingTask::where('placement_id', $placement->id)
            ->whereNotIn('status', ['completed', 'skipped'])
            ->exists();

        if (! $incomplete && $placement->status === 'pending') {
            $placement->update(['status' => 'active']);
            AuditLogger::logModelEvent($placement, 'updated');
        }
    }

    /**
     * Skip an onboarding task (e.g., not applicable).
     */
    public function skipTask(OnboardingTask $task, string $reason = ''): OnboardingTask
    {
        return DB::transaction(function () use ($task, $reason) {
            $task->update([
                'status'           => 'skipped',
                'completion_notes' => $reason,
            ]);

            $this->checkAndActivatePlacement($task->placement);

            AuditLogger::logModelEvent($task, 'updated');

            return $task->fresh();
        });
    }
}
