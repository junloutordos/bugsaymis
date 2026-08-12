<?php

namespace App\Services\ALP;

use App\Models\ALP\AlpMembership;
use App\Models\Registrar\StudentEnrollment;
use App\Models\User;
use Illuminate\Support\Collection;

class AlpRosterService
{
    public function activeMembers(int $schoolYearId, User $user): Collection
    {
        $elevated = $user->isSuperAdmin() || $user->hasAnyPermission(['alp.manage', 'alp.coordinate', 'alp.registrar-certify', 'alp.approve', 'alp.reports', 'alp.audit']);

        return AlpMembership::query()
            ->where('status', 'active')
            ->where('school_year_id', $schoolYearId)
            ->whereHas('cycle', fn ($q) => $q->when(! $elevated, fn ($scope) => $scope->where(
                fn ($s) => $s->where('adviser_id', $user->id)->orWhere('coordinator_id', $user->id)
            )))
            ->with(['student:id,firstname,lastname,middlename', 'enrollment.section:id,sectionname', 'cycle.program:id,name'])
            ->get()
            ->filter(fn ($membership) => $membership->student !== null)
            ->map(fn ($membership) => [
                'name' => $membership->student?->full_name,
                'grade_level' => $membership->enrollment?->grade_level,
                'section' => $membership->enrollment?->section?->sectionname,
                'alp' => $membership->cycle?->program?->name,
            ])
            ->sortBy('name')
            ->values();
    }

    public function unassignedGrades7To10(int $schoolYearId): Collection
    {
        $assignedStudentIds = AlpMembership::query()
            ->where('school_year_id', $schoolYearId)
            ->where('status', 'active')
            ->whereHas('enrollment', fn ($q) => $q->whereBetween('grade_level', [7, 10]))
            ->pluck('student_id');

        return StudentEnrollment::query()
            ->where('school_year_id', $schoolYearId)
            ->where('status', 'enrolled')
            ->whereBetween('grade_level', [7, 10])
            ->whereNotIn('student_id', $assignedStudentIds)
            ->with(['student:id,firstname,lastname,middlename', 'section:id,sectionname'])
            ->get()
            ->filter(fn ($enrollment) => $enrollment->student !== null)
            ->map(fn ($enrollment) => [
                'name' => $enrollment->student?->full_name,
                'grade_level' => $enrollment->grade_level,
                'section' => $enrollment->section?->sectionname,
            ])
            ->sortBy('name')
            ->values();
    }

    /**
     * Narrow a roster (as produced by activeMembers()/unassignedGrades7To10())
     * down to rows matching the given search/grade/section filters — mirrors
     * the client-side filtering in Members.vue/Unassigned.vue so the PDF
     * export can respect the same filters the user has applied on screen.
     */
    public function filterRows(Collection $rows, ?string $search, ?string $grade, ?string $section, array $searchFields = ['name']): Collection
    {
        $q = trim(strtolower((string) $search));

        return $rows->filter(function ($row) use ($q, $grade, $section, $searchFields) {
            if ($grade !== null && $grade !== '' && (string) ($row['grade_level'] ?? '') !== (string) $grade) {
                return false;
            }
            if ($section !== null && $section !== '' && ($row['section'] ?? null) !== $section) {
                return false;
            }
            if ($q === '') {
                return true;
            }
            $haystack = strtolower(implode(' ', array_map(fn ($field) => (string) ($row[$field] ?? ''), $searchFields)));

            return str_contains($haystack, $q);
        })->values();
    }
}
