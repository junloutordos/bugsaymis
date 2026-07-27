<?php

namespace App\Console\Commands\FacultyLoading;

use App\Models\FacultyLoading\Section;
use App\Models\User;
use App\Services\FacultyLoading\HeadAdvisoryService;
use Illuminate\Console\Command;

class SetHomeroomCoordinator extends Command
{
    protected $signature = 'faculty-loading:set-homeroom-coordinator
                            {section_id : Section ID}
                            {user_id? : User ID to set as coordinator; omit to clear the override (falls back to the adviser)}';

    protected $description = 'Set (or clear) a section\'s Homeroom Coordinator override, independent of the Section Adviser, and sync the HRA-/HAC- LoadAssignment accordingly.';

    public function handle(HeadAdvisoryService $advisory): int
    {
        $section = Section::find($this->argument('section_id'));
        if (! $section) {
            $this->error("Section #{$this->argument('section_id')} not found.");
            return self::FAILURE;
        }

        $userId = $this->argument('user_id') ? (int) $this->argument('user_id') : null;
        if ($userId) {
            $user = User::find($userId);
            if (! $user) {
                $this->error("User #{$userId} not found.");
                return self::FAILURE;
            }
        }

        $oldCoordinatorId = $section->homeroom_coordinator_id ? (int) $section->homeroom_coordinator_id : null;

        if ($oldCoordinatorId === $userId) {
            $this->info('No change — coordinator already set to this value.');
            return self::SUCCESS;
        }

        $section->update(['homeroom_coordinator_id' => $userId]);
        $advisory->syncHomeroomCoordinator($section->fresh(), $oldCoordinatorId);

        $this->info($userId
            ? "Set Homeroom Coordinator for Section #{$section->id} ({$section->sectionname}) to {$user->name}."
            : "Cleared Homeroom Coordinator override for Section #{$section->id} ({$section->sectionname}) — falls back to the adviser.");

        return self::SUCCESS;
    }
}
