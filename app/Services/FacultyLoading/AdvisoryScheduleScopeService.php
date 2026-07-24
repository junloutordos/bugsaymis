<?php

namespace App\Services\FacultyLoading;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\Section;
use App\Models\User;
use Illuminate\Support\Collection;

class AdvisoryScheduleScopeService
{
    /** @return Collection<int, int> Adviser user IDs keyed by section ID — for formatting the name via PersonNameFormatter. */
    public function adviserUserIdsBySection(int $academicTermId, array|Collection $sectionIds): Collection
    {
        $sectionIds = collect($sectionIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($sectionIds->isEmpty()) {
            return collect();
        }

        return LoadAssignment::with(['designation.category'])
            ->where('academic_term_id', $academicTermId)
            ->whereNotNull('designation_id')
            ->whereHas('designation', fn ($query) => $query
                ->where('is_active', true)
                ->where(function ($sectionQuery) use ($sectionIds) {
                    $sectionQuery->whereIn('section_id', $sectionIds)
                        ->orWhereNull('section_id');
                })
                ->whereHas('category', fn ($categoryQuery) => $categoryQuery
                    ->whereIn('code', ['HR_ADV', 'HR_ACAD'])))
            ->where(function ($query) use ($sectionIds) {
                $query->whereIn('section_id', $sectionIds)
                    ->orWhereHas('designation', fn ($designationQuery) => $designationQuery
                        ->whereIn('section_id', $sectionIds));
            })
            ->orderBy('id')
            ->get()
            ->mapWithKeys(function (LoadAssignment $assignment) {
                $sectionId = $assignment->designation?->section_id ?? $assignment->section_id;

                return $sectionId && $assignment->user_id
                    ? [(int) $sectionId => (int) $assignment->user_id]
                    : [];
            });
    }

    /** @return Collection<int, string> Adviser names keyed by section ID. */
    public function adviserNamesBySection(int $academicTermId, array|Collection $sectionIds): Collection
    {
        $sectionIds = collect($sectionIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($sectionIds->isEmpty()) {
            return collect();
        }

        return LoadAssignment::with(['faculty:id,name', 'designation.category'])
            ->where('academic_term_id', $academicTermId)
            ->whereNotNull('designation_id')
            ->whereHas('designation', fn ($query) => $query
                ->where('is_active', true)
                ->where(function ($sectionQuery) use ($sectionIds) {
                    $sectionQuery->whereIn('section_id', $sectionIds)
                        ->orWhereNull('section_id');
                })
                ->whereHas('category', fn ($categoryQuery) => $categoryQuery
                    ->whereIn('code', ['HR_ADV', 'HR_ACAD'])))
            ->where(function ($query) use ($sectionIds) {
                $query->whereIn('section_id', $sectionIds)
                    ->orWhereHas('designation', fn ($designationQuery) => $designationQuery
                        ->whereIn('section_id', $sectionIds));
            })
            ->orderBy('id')
            ->get()
            ->mapWithKeys(function (LoadAssignment $assignment) {
                $sectionId = $assignment->designation?->section_id ?? $assignment->section_id;

                return $sectionId && $assignment->faculty
                    ? [(int) $sectionId => $assignment->faculty->name]
                    : [];
            });
    }

    /** @return array<int> */
    public function sectionIds(User $user, int $academicTermId): array
    {
        $term = AcademicTerm::find($academicTermId);
        if (! $term) {
            return [];
        }

        $assignments = $this->assignments($user, $academicTermId);

        $sectionIds = $assignments
            ->filter(fn (LoadAssignment $assignment) => in_array(
                $assignment->designation?->category?->code,
                ['HR_ADV', 'HR_ACAD'],
                true,
            ))
            ->map(fn (LoadAssignment $assignment) => $assignment->designation?->section_id ?? $assignment->section_id)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->toBase();

        $grades = $assignments
            ->map(fn (LoadAssignment $assignment) => $this->coordinatorGrades($assignment))
            ->flatten()
            ->unique()
            ->values();

        if ($grades->isNotEmpty()) {
            $sectionIds = $sectionIds->merge(
                Section::query()
                    ->where('school_year_id', $term->school_year_id)
                    ->where('is_active', true)
                    ->whereIn('levelid', $grades)
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
            );
        }

        return $sectionIds->unique()->sort()->values()->all();
    }

    public function hasCurrentScope(User $user): bool
    {
        $termId = AcademicTerm::where('is_current', true)->value('id');

        return $termId ? $this->sectionIds($user, (int) $termId) !== [] : false;
    }

    /** @return array<int> */
    public function termIds(User $user): array
    {
        return LoadAssignment::with(['designation.category'])
            ->where('user_id', $user->id)
            ->whereNotNull('designation_id')
            ->get()
            ->filter(fn (LoadAssignment $assignment) => in_array(
                $assignment->designation?->category?->code,
                ['HR_ADV', 'HR_ACAD'],
                true,
            ) || $this->coordinatorGrades($assignment) !== [])
            ->pluck('academic_term_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /** @return Collection<int, LoadAssignment> */
    private function assignments(User $user, int $academicTermId): Collection
    {
        return LoadAssignment::with(['designation.category'])
            ->where('user_id', $user->id)
            ->where('academic_term_id', $academicTermId)
            ->whereNotNull('designation_id')
            ->get();
    }

    /** @return array<int> */
    private function coordinatorGrades(LoadAssignment $assignment): array
    {
        $designation = $assignment->designation;
        if (! $designation) {
            return [];
        }

        $code = strtoupper((string) $designation->code);
        $name = strtoupper((string) $designation->name);
        $isHrCoordinator = preg_match('/^COORD-(?:HR)?G(?:7|8|9|10|11|12)/', $code) === 1
            || ((str_contains($name, 'HR') || str_contains($name, 'HOMEROOM')) && str_contains($name, 'COORDINATOR'));

        if (! $isHrCoordinator) {
            return [];
        }

        foreach ([$name, $code] as $label) {
            if (preg_match('/G\s*(7|8|9|10|11|12)\s*(?:-|–|—|&|TO)\s*G?\s*(7|8|9|10|11|12)/i', $label, $matches) !== 1) {
                continue;
            }

            $start = (int) $matches[1];
            $end = (int) $matches[2];

            return range(min($start, $end), max($start, $end));
        }

        foreach ([$name, $code] as $label) {
            if (preg_match('/(?:GRADE|G)\s*(12|11|10|9|8|7)\b/i', $label, $matches) === 1) {
                return [(int) $matches[1]];
            }
        }

        return [];
    }
}
