<?php

namespace App\Services\ClassRecord;

use App\Models\ClassRecord\GradingOption;
use App\Models\FacultyLoading\Designation;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Subject;
use App\Models\User;
use Illuminate\Support\Collection;

class GradingOptionScopeService
{
    /** @var array<int, Collection<int, Designation>> */
    private array $managedDesignationCache = [];

    public function canManage(User $user): bool
    {
        return $user->hasPermission('class-records.admin')
            || $this->managedDesignations($user)->isNotEmpty();
    }

    /** @return Collection<int, Designation> */
    public function managedDesignations(User $user): Collection
    {
        return $this->managedDesignationCache[$user->id] ??= Designation::query()
            ->with('category:id,code')
            ->where('is_active', true)
            ->where('code', 'like', 'AUH-%')
            ->whereHas('category', fn ($query) => $query->where('code', 'AUH'))
            ->whereHas('loadAssignments', fn ($query) => $query
                ->where('user_id', $user->id)
                ->whereHas('academicTerm', fn ($termQuery) => $termQuery->where('is_current', true)))
            ->orderBy('name')
            ->get();
    }

    /** All active AUH designations — for admins picking a unit to own a new option. */
    public function allAuhDesignations(): Collection
    {
        return Designation::query()
            ->where('is_active', true)
            ->where('code', 'like', 'AUH-%')
            ->whereHas('category', fn ($query) => $query->where('code', 'AUH'))
            ->orderBy('name')
            ->get();
    }

    /** @return array<int> */
    public function managedDesignationIds(User $user): array
    {
        return $this->managedDesignations($user)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function canManageOption(User $user, GradingOption $option): bool
    {
        if ($user->hasPermission('class-records.admin')) {
            return true;
        }

        return $option->owner_designation_id !== null
            && in_array((int) $option->owner_designation_id, $this->managedDesignationIds($user), true);
    }

    public function resolveOwnerDesignationId(User $user, ?int $requestedId): ?int
    {
        if ($user->hasPermission('class-records.admin')) {
            if ($requestedId === null) {
                return null;
            }

            abort_unless(
                Designation::query()
                    ->whereKey($requestedId)
                    ->where('is_active', true)
                    ->where('code', 'like', 'AUH-%')
                    ->whereHas('category', fn ($query) => $query->where('code', 'AUH'))
                    ->exists(),
                422,
                'Select a valid Academic Unit Head designation.',
            );

            return $requestedId;
        }

        $managedIds = $this->managedDesignationIds($user);
        abort_if($managedIds === [], 403, 'You are not assigned as an Academic Unit Head for the current term.');

        if ($requestedId === null && count($managedIds) === 1) {
            return $managedIds[0];
        }

        abort_unless(
            $requestedId !== null && in_array($requestedId, $managedIds, true),
            403,
            'You may only create grading options for your assigned academic unit.',
        );

        return $requestedId;
    }

    /** Active campus-wide and relevant unit options for the user's current teaching assignments. */
    public function selectableForUser(User $user): Collection
    {
        $query = GradingOption::with(['categories', 'ownerDesignation:id,code,name'])
            ->where('is_active', true);

        if (! $user->hasPermission('class-records.admin')) {
            $ownerIds = $this->teachingUnitDesignationIds($user);
            $query->where(function ($scopeQuery) use ($ownerIds) {
                $scopeQuery->whereNull('owner_designation_id');
                if ($ownerIds !== []) {
                    $scopeQuery->orWhereIn('owner_designation_id', $ownerIds);
                }
            });
        }

        return $query->orderBy('id')->get();
    }

    /** Active campus-wide and matching-unit options for one subject. */
    public function selectableForSubject(Subject $subject): Collection
    {
        $subject->loadMissing('academicUnit:id,code');
        $ownerId = $this->designationIdForUnitCode($subject->academicUnit?->code);

        return GradingOption::with(['categories', 'ownerDesignation:id,code,name'])
            ->where('is_active', true)
            ->where(function ($query) use ($ownerId) {
                $query->whereNull('owner_designation_id');
                if ($ownerId !== null) {
                    $query->orWhere('owner_designation_id', $ownerId);
                }
            })
            ->orderBy('id')
            ->get();
    }

    public function isSelectableForSubject(GradingOption $option, Subject $subject): bool
    {
        if (! $option->is_active) {
            return false;
        }

        if ($option->owner_designation_id === null) {
            return true;
        }

        $subject->loadMissing('academicUnit:id,code');

        return (int) $option->owner_designation_id
            === $this->designationIdForUnitCode($subject->academicUnit?->code);
    }

    /** Options shown in the management menu. */
    public function manageableFor(User $user): Collection
    {
        $query = GradingOption::with(['categories', 'ownerDesignation:id,code,name']);

        if (! $user->hasPermission('class-records.admin')) {
            $query->whereIn('owner_designation_id', $this->managedDesignationIds($user));
        }

        return $query->orderBy('id')->get();
    }

    /** @return array<int> */
    private function teachingUnitDesignationIds(User $user): array
    {
        $schoolYearId = SchoolYear::where('is_current', true)->value('id');
        if (! $schoolYearId) {
            return [];
        }

        $unitCodes = LoadAssignment::query()
            ->with('subject.academicUnit:id,code')
            ->where('user_id', $user->id)
            ->where('school_year_id', $schoolYearId)
            ->where('assignment_type', 'teaching')
            ->whereNotNull('subject_id')
            ->get()
            ->pluck('subject.academicUnit.code')
            ->filter()
            ->unique()
            ->values();

        if ($unitCodes->isEmpty()) {
            return [];
        }

        return Designation::query()
            ->whereIn('code', $unitCodes->map(fn ($code) => 'AUH-'.$code))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function designationIdForUnitCode(?string $unitCode): ?int
    {
        if (! $unitCode) {
            return null;
        }

        $id = Designation::where('code', 'AUH-'.$unitCode)->value('id');

        return $id ? (int) $id : null;
    }
}
