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

        $base = LoadAssignment::with(['designation.category'])
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

        // Collection::merge() treats numeric keys (section IDs) as a list and
        // renumbers/appends instead of preserving them as a map — union()
        // keeps keyed values intact, and preferring the override's collection
        // makes it win on any key collision with the designation-based base.
        return $this->coordinatorOverrideUserIds($sectionIds)->union($base);
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

        $base = LoadAssignment::with(['faculty:id,name', 'designation.category'])
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

        $overrides = Section::whereIn('id', $sectionIds)
            ->whereNotNull('homeroom_coordinator_id')
            ->with('homeroomCoordinatorUser:id,name')
            ->get()
            ->mapWithKeys(fn (Section $s) => [$s->id => $s->homeroomCoordinatorUser?->name])
            ->filter();

        // See adviserUserIdsBySection() — union(), not merge(), to preserve
        // numeric section-ID keys and let the override win on collision.
        return $overrides->union($base);
    }

    /**
     * Explicit per-section Homeroom Coordinator overrides, keyed by section
     * ID. This always wins over whatever LoadAssignment row happens to
     * exist for the section's HR_ADV/HR_ACAD designations — the adviser
     * keeps their own designation (and Load Assignment credit) even after
     * an override is set, so relying on "most recently created row wins"
     * would be fragile (e.g. re-assigning the adviser later would create a
     * newer row and wrongly flip WAT resolution back to them).
     *
     * @param Collection<int, int> $sectionIds
     * @return Collection<int, int>
     */
    private function coordinatorOverrideUserIds(Collection $sectionIds): Collection
    {
        return Section::whereIn('id', $sectionIds)
            ->whereNotNull('homeroom_coordinator_id')
            ->pluck('homeroom_coordinator_id', 'id')
            ->map(fn ($id) => (int) $id);
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

        // An explicit per-section Homeroom Coordinator override is
        // authoritative for WAT access: it grants access to whoever holds
        // it and revokes it from anyone else who'd otherwise qualify via
        // their own designation (e.g. the adviser, who keeps their Load
        // Assignment credit but not WAT access once overridden).
        $overridden = Section::where('school_year_id', $term->school_year_id)
            ->where('is_active', true)
            ->whereNotNull('homeroom_coordinator_id')
            ->get(['id', 'homeroom_coordinator_id']);

        $overriddenToOthers = $overridden->where('homeroom_coordinator_id', '!=', $user->id)
            ->pluck('id')->map(fn ($id) => (int) $id);
        $overriddenToMe = $overridden->where('homeroom_coordinator_id', $user->id)
            ->pluck('id')->map(fn ($id) => (int) $id);

        $sectionIds = $sectionIds->diff($overriddenToOthers)->merge($overriddenToMe);

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

        // A designation tied to one specific section (HRA-/HAC-/HRC-) can
        // never ALSO be a grade-wide "coordinates every section in this
        // range" role — those are mutually exclusive. Without this guard,
        // the per-section HRC- "Homeroom Coordinator" designation name
        // would false-positive the regex below (it contains "COORDINATOR")
        // and wrongly grant access to every other section in that grade.
        if (! $designation || $designation->section_id) {
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
