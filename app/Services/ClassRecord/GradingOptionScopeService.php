<?php

namespace App\Services\ClassRecord;

use App\Models\ClassRecord\GradingOption;
use App\Models\FacultyLoading\Designation;
use App\Models\FacultyLoading\Subject;
use App\Models\Office;
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

    /**
     * Campus-wide options (owner_designation_id = NULL) plus the user's own
     * Academic Unit/Office's options. Scoped via the user's Office ->
     * Office.unit_head -> the AUH designation that head currently holds — NOT
     * users.academic_unit_id / subjects.academic_unit_id, which are declared
     * in the schema but never populated by any UI in this app. A user whose
     * office isn't linked to any AUH still sees every campus-wide option.
     */
    public function selectableForUser(User $user): Collection
    {
        if ($user->hasPermission('class-records.admin')) {
            return GradingOption::with(['categories', 'ownerDesignation:id,code,name'])
                ->where('is_active', true)
                ->orderBy('id')
                ->get();
        }

        $ownerIds = $this->officeUnitDesignationIds($user);

        return GradingOption::with(['categories', 'ownerDesignation:id,code,name'])
            ->where('is_active', true)
            ->where(function ($query) use ($ownerIds) {
                $query->whereNull('owner_designation_id');
                if ($ownerIds !== []) {
                    $query->orWhereIn('owner_designation_id', $ownerIds);
                }
            })
            ->orderBy('id')
            ->get();
    }

    /** Same rule as selectableForUser(), for a specific subject's create/edit flow. */
    public function selectableForSubject(Subject $subject, User $user): Collection
    {
        return $this->selectableForUser($user);
    }

    public function isSelectableForSubject(GradingOption $option, Subject $subject, User $user): bool
    {
        if (! $option->is_active) {
            return false;
        }

        if ($user->hasPermission('class-records.admin')) {
            return true;
        }

        if ($option->owner_designation_id === null) {
            return true;
        }

        return in_array((int) $option->owner_designation_id, $this->officeUnitDesignationIds($user), true);
    }

    /** @return array<int> */
    private function officeUnitDesignationIds(User $user): array
    {
        if (! $user->office_id) {
            return [];
        }

        $unitHeadUserId = Office::where('id', $user->office_id)->value('unit_head');
        if (! $unitHeadUserId) {
            return [];
        }

        $head = User::find($unitHeadUserId);
        if (! $head) {
            return [];
        }

        return $this->managedDesignationIds($head);
    }

    /** Active options applicable to a specific quarter (null applicable_quarters = all). */
    public function selectableForQuarter(int $quarter): Collection
    {
        return GradingOption::with(['categories', 'ownerDesignation:id,code,name'])
            ->where('is_active', true)
            ->orderBy('id')
            ->get()
            ->filter(fn (GradingOption $option) => $option->appliesToQuarter($quarter))
            ->values();
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
}
