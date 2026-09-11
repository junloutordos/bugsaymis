<?php

namespace App\Console\Commands;

use App\Models\CommitteeTask;
use App\Services\PerformanceManagement\CommitteeNotificationService;
use Illuminate\Console\Command;

/**
 * Notifies a recurring committee task's assignee(s) — and the committee's
 * board managers — once its computed next-due date has passed without a
 * new accomplishment update. Only monthly/quarterly/annually cadences
 * compute a next-due date (CommitteeTask::nextDueDate()); end_of_rating
 * _period/annually/varies/one_time/null never fire here by design.
 *
 * Rate-limited to once per overdue task per day via a cache lock, since
 * this command runs daily and a task can stay overdue for many days in a
 * row before someone finally submits.
 */
class SendCommitteeTaskCadenceReminders extends Command
{
    protected $signature = 'committees:cadence-reminders';
    protected $description = 'Notify assignees and board managers of overdue recurring committee task accomplishment submissions';

    public function handle(CommitteeNotificationService $notifications, \App\Services\CommitteeBoardService $board): int
    {
        $tasks = CommitteeTask::with(['committee.parentCommittee', 'assignees', 'accomplishmentUpdates'])
            ->whereIn('submission_frequency', ['monthly', 'quarterly', 'annually'])
            ->whereHas('committee', fn ($q) => $q->whereNull('revoked_at'))
            ->get();

        $sentCount = 0;

        foreach ($tasks as $task) {
            $dueDate = $task->nextDueDate();
            if (! $dueDate || ! $dueDate->isPast()) {
                continue;
            }

            $cacheKey = "committee_task_cadence_reminder:{$task->id}:" . now()->format('Y-m-d');
            if (\Illuminate\Support\Facades\Cache::has($cacheKey)) {
                continue;
            }

            $recipients = $task->assignees
                ->merge($board->boardManagers($task->committee))
                ->unique('id');

            foreach ($recipients as $recipient) {
                $notifications->cadenceOverdue($recipient, $task->committee, $task, $dueDate);
            }

            \Illuminate\Support\Facades\Cache::put($cacheKey, true, now()->endOfDay());
            $sentCount++;
            $this->line("Cadence reminder sent: [{$task->committee->name}] {$task->title} (due {$dueDate->toDateString()})");
        }

        if ($sentCount === 0) {
            $this->line('No overdue recurring committee tasks — nothing to send.');
        }

        return self::SUCCESS;
    }
}
