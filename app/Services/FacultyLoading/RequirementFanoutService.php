<?php

namespace App\Services\FacultyLoading;

use App\Models\FacultyLoading\ResearchGroup;
use App\Models\FacultyLoading\ResearchRequirement;
use App\Models\FacultyLoading\ResearchRequirementAssignment;
use Illuminate\Support\Collection;

class RequirementFanoutService
{
    /**
     * Groups that match a requirement's scope: same term, active (has a
     * non-dropped advisory), grade in grade_levels (or all if null),
     * research_type equal (or all if null).
     *
     * @return Collection<int, ResearchGroup>
     */
    public function matchingGroups(ResearchRequirement $requirement): Collection
    {
        return ResearchGroup::query()
            ->where('academic_term_id', $requirement->academic_term_id)
            ->active()
            ->when($requirement->grade_levels, fn ($q) => $q->whereIn('grade_level', $requirement->grade_levels))
            ->when($requirement->research_type, fn ($q) => $q->where('research_type', $requirement->research_type))
            ->get();
    }

    /**
     * Create a pending assignment for every matching group that doesn't
     * already have one for this requirement. Never removes or excludes
     * existing assignments — safe to call repeatedly (fan-out on create,
     * "Sync" action later).
     *
     * @return Collection<int, ResearchRequirementAssignment> newly created assignments only
     */
    public function fanOut(ResearchRequirement $requirement): Collection
    {
        $matching = $this->matchingGroups($requirement);

        $existingGroupIds = ResearchRequirementAssignment::where('research_requirement_id', $requirement->id)
            ->pluck('research_group_id')
            ->all();

        $toCreate = $matching->reject(fn ($group) => in_array($group->id, $existingGroupIds, true));

        return $toCreate->map(fn ($group) => ResearchRequirementAssignment::create([
            'research_requirement_id' => $requirement->id,
            'research_group_id'       => $group->id,
        ]));
    }
}
