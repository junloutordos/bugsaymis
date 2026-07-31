<?php

namespace App\Services\ALP;

use App\Models\ALP\AlpProgram;
use App\Models\ALP\AlpProgramCycle;
use App\Models\FacultyLoading\Designation;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AlpProgramSyncService
{
    public function __construct(private AlpComplianceService $compliance) {}

    public function syncCurrentSchoolYear(User $actor): Collection
    {
        $schoolYear = SchoolYear::where('is_current', true)->firstOrFail();
        $coordinatorId = LoadAssignment::query()
            ->where('school_year_id', $schoolYear->id)
            ->whereHas('designation', fn ($q) => $q->where('code', 'COORD-ALP'))
            ->value('user_id');

        $designations = Designation::query()
            ->active()
            ->whereHas('category', fn ($q) => $q->where('code', 'ALA'))
            ->with(['loadAssignments' => fn ($q) => $q->where('school_year_id', $schoolYear->id)->with('faculty:id,name')])
            ->orderBy('sort_order')
            ->get();

        return $designations->map(function (Designation $designation) use ($schoolYear, $coordinatorId, $actor) {
            $program = AlpProgram::updateOrCreate(
                ['designation_id' => $designation->id],
                [
                    'code' => Str::replaceStart('ALA-', 'ALP-', $designation->code),
                    'name' => preg_replace('/\s+(Club\s+)?Adviser$/i', '', $designation->name) ?: $designation->name,
                    'status' => 'active',
                    'created_by' => $actor->id,
                ]
            );

            $assignment = $designation->loadAssignments->sortByDesc('id')->first();
            $cycle = AlpProgramCycle::firstOrCreate(
                ['alp_program_id' => $program->id, 'school_year_id' => $schoolYear->id],
                [
                    'cycle_type' => $program->cycles()->exists() ? 'reaccreditation' : 'accreditation',
                    'status' => 'draft',
                    'created_by' => $actor->id,
                ]
            );
            $cycle->update([
                'adviser_assignment_id' => $assignment?->id,
                'adviser_id' => $assignment?->user_id,
                'coordinator_id' => $coordinatorId,
            ]);
            $this->compliance->seedDocuments($cycle);

            return $cycle->fresh(['program', 'adviser']);
        });
    }
}
