<?php

namespace App\Services\PerformanceManagement;

use App\Models\Committee;
use App\Models\CommitteeTask;
use App\Models\User;

/**
 * Materializes a committee member's free-text "task" (captured on the
 * committee_user pivot at roster time) into a real CommitteeTask row on
 * the task board, so a chairperson no longer has to manually re-type each
 * member's assignment as a board task.
 *
 * One board task per (committee, member) is kept in sync by title —
 * editing the roster task text later updates the same task rather than
 * creating a duplicate, as long as the task hasn't been renamed on the
 * board itself (tracked via `auto_synced_from_roster`).
 */
class CommitteeTaskAutoSyncService
{
    /**
     * Ensures $user has an auto-synced CommitteeTask on $committee's board
     * matching $taskText. No-op when $taskText is blank. Idempotent —
     * safe to call on every roster save.
     */
    public function syncMemberTask(Committee $committee, User $user, ?string $taskText, ?int $createdById = null): void
    {
        $taskText = trim((string) $taskText);

        $existing = CommitteeTask::where('committee_id', $committee->id)
            ->where('auto_synced_from_roster', true)
            ->whereHas('assignees', fn ($q) => $q->where('users.id', $user->id))
            ->first();

        if ($taskText === '') {
            // Roster task cleared — leave any already-created board task
            // alone (it may carry real status/history now); nothing to sync.
            return;
        }

        if ($existing) {
            if ($existing->title !== $taskText) {
                $existing->update(['title' => $taskText]);
            }

            return;
        }

        $task = CommitteeTask::create([
            'committee_id'             => $committee->id,
            'title'                    => $taskText,
            'status'                   => 'not_started',
            'priority'                 => 'medium',
            'sort_order'               => (int) (CommitteeTask::where('committee_id', $committee->id)->max('sort_order') + 1),
            'created_by'               => $createdById,
            'auto_synced_from_roster'  => true,
        ]);

        $task->assignees()->sync([$user->id]);
    }

    /**
     * Unassigns (does not delete) a removed member's auto-synced task —
     * preserves work history while taking them off the board.
     */
    public function unassignMember(Committee $committee, User $user): void
    {
        CommitteeTask::where('committee_id', $committee->id)
            ->where('auto_synced_from_roster', true)
            ->whereHas('assignees', fn ($q) => $q->where('users.id', $user->id))
            ->get()
            ->each(fn (CommitteeTask $task) => $task->assignees()->detach($user->id));
    }
}
