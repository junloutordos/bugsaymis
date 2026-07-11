<?php

namespace App\Services;

use App\Models\Committee;
use App\Models\FacultyLoading\FacultyCommitteeAssignment;
use App\Models\User;

/**
 * Authorization for the committee task board, shared by the Performance
 * Management and Faculty Loading surfaces. Mirrors the hierarchical rating
 * gate in CommitteeAssignmentController::rateAssignment():
 *   board managers = committee head, parent (main) committee head, an FL
 *   chairperson/co-chair assignment on the committee or its parent, or an
 *   admin (faculty_loading.manage / SuperAdmin).
 */
class CommitteeBoardService
{
    public function canManageBoard(User $user, Committee $committee): bool
    {
        if ($user->isSuperAdmin() || $user->hasPermission('faculty_loading.manage')) {
            return true;
        }

        $committeeIds = array_filter([$committee->id, $committee->parent_committee_id]);

        if (Committee::whereIn('id', $committeeIds)->where('head_id', $user->id)->exists()) {
            return true;
        }

        return FacultyCommitteeAssignment::whereIn('committee_id', $committeeIds)
            ->where('user_id', $user->id)
            ->whereIn('role', ['chairperson', 'co_chair'])
            ->where('status', 'active')
            ->exists();
    }

    /** Head, DM member, or active FL assignee of the committee. */
    public function isBoardMember(User $user, Committee $committee): bool
    {
        if ($committee->head_id === $user->id) {
            return true;
        }

        if ($committee->members()->where('users.id', $user->id)->exists()) {
            return true;
        }

        return FacultyCommitteeAssignment::where('committee_id', $committee->id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->exists();
    }

    public function assertCanManageBoard(User $user, Committee $committee): void
    {
        abort_unless(
            $this->canManageBoard($user, $committee),
            403,
            'Only the committee head, sub-committee head, or an administrator can manage this board.'
        );
    }

    /** Users who should be notified about board activity (heads, deduped). */
    public function boardManagers(Committee $committee): \Illuminate\Support\Collection
    {
        $committee->loadMissing('parentCommittee');

        $headIds = collect([$committee->head_id, $committee->parentCommittee?->head_id])
            ->merge(
                FacultyCommitteeAssignment::whereIn('committee_id', array_filter([$committee->id, $committee->parent_committee_id]))
                    ->whereIn('role', ['chairperson', 'co_chair'])
                    ->where('status', 'active')
                    ->pluck('user_id')
            )
            ->filter()
            ->unique();

        return User::whereIn('id', $headIds)->where('status', '<>', 'inactive')->get();
    }
}
