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
            ->map(fn ($enrollment) => [
                'name' => $enrollment->student?->full_name,
                'grade_level' => $enrollment->grade_level,
                'section' => $enrollment->section?->sectionname,
            ])
            ->sortBy('name')
            ->values();
    }
}
