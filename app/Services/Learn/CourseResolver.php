<?php

namespace App\Services\Learn;

use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\Learn\Course;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Resolves Learn courses from Faculty Loading's teaching assignments. There
 * is no sync job or Observer — a course row is find-or-created the moment
 * someone asks to see it, so it can never be stale or fail to run.
 */
class CourseResolver
{
    /** @return Collection<int, Course> */
    public function coursesForFaculty(User $user): Collection
    {
        $schoolYearId = $this->currentSchoolYearId();
        if (! $schoolYearId) {
            return collect();
        }

        return $this->resolveFromAssignments(
            LoadAssignment::teaching()
                ->where('user_id', $user->id)
                ->where('school_year_id', $schoolYearId)
        );
    }

    /** @return Collection<int, Course> */
    public function allCoursesForCurrentSchoolYear(): Collection
    {
        $schoolYearId = $this->currentSchoolYearId();
        if (! $schoolYearId) {
            return collect();
        }

        return $this->resolveFromAssignments(
            LoadAssignment::teaching()->where('school_year_id', $schoolYearId)
        );
    }

    /** @return Collection<int, Course> */
    private function resolveFromAssignments(Builder $query): Collection
    {
        $tuples = $query
            ->whereNotNull('section_id')
            ->get(['subject_id', 'section_id', 'school_year_id', 'academic_term_id'])
            ->unique(fn ($a) => "{$a->subject_id}-{$a->section_id}-{$a->school_year_id}-{$a->academic_term_id}");

        return $tuples->map(fn ($tuple) => Course::firstOrCreate([
            'subject_id' => $tuple->subject_id,
            'section_id' => $tuple->section_id,
            'school_year_id' => $tuple->school_year_id,
            'academic_term_id' => $tuple->academic_term_id,
        ]))->values();
    }

    private function currentSchoolYearId(): ?int
    {
        return SchoolYear::where('is_current', true)->value('id');
    }
}
