<?php

namespace Tests\Feature\PerformanceManagement;

use App\Models\Committee;
use App\Models\CommitteeTask;
use App\Models\User;
use App\Notifications\RequestStatusNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CommitteeTaskCadenceRemindersCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_notifies_assignee_of_an_overdue_monthly_task(): void
    {
        Notification::fake();
        Cache::flush();

        $head   = User::factory()->create();
        $member = User::factory()->create();
        $committee = Committee::create(['name' => 'Grievance Committee', 'head_id' => $head->id]);

        $task = CommitteeTask::create([
            'committee_id'         => $committee->id,
            'title'                => 'Submit monthly minutes',
            'submission_frequency' => 'monthly',
            'created_by'           => $head->id,
        ]);
        $task->assignees()->sync([$member->id]);
        // Force it overdue — created 2 months ago, never submitted.
        $task->created_at = now()->subMonths(2);
        $task->save();

        $this->artisan('committees:cadence-reminders')->assertExitCode(0);

        Notification::assertSentTo($member, RequestStatusNotification::class, function ($notification) {
            return $notification->requestType === 'Committee Task Overdue';
        });
    }

    public function test_command_does_not_notify_when_not_yet_due(): void
    {
        Notification::fake();
        Cache::flush();

        $head   = User::factory()->create();
        $member = User::factory()->create();
        $committee = Committee::create(['name' => 'Grievance Committee', 'head_id' => $head->id]);

        $task = CommitteeTask::create([
            'committee_id'         => $committee->id,
            'title'                => 'Submit monthly minutes',
            'submission_frequency' => 'monthly',
            'created_by'           => $head->id,
        ]);
        $task->assignees()->sync([$member->id]);

        $this->artisan('committees:cadence-reminders')->assertExitCode(0);

        Notification::assertNotSentTo($member, RequestStatusNotification::class);
    }

    public function test_command_does_not_double_notify_within_the_same_day(): void
    {
        Notification::fake();
        Cache::flush();

        $head   = User::factory()->create();
        $member = User::factory()->create();
        $committee = Committee::create(['name' => 'Grievance Committee', 'head_id' => $head->id]);

        $task = CommitteeTask::create([
            'committee_id'         => $committee->id,
            'title'                => 'Submit monthly minutes',
            'submission_frequency' => 'monthly',
            'created_by'           => $head->id,
        ]);
        $task->assignees()->sync([$member->id]);
        $task->created_at = now()->subMonths(2);
        $task->save();

        $this->artisan('committees:cadence-reminders')->assertExitCode(0);
        $this->artisan('committees:cadence-reminders')->assertExitCode(0);

        Notification::assertSentToTimes($member, RequestStatusNotification::class, 1);
    }

    public function test_command_skips_revoked_committees(): void
    {
        Notification::fake();
        Cache::flush();

        $head   = User::factory()->create();
        $member = User::factory()->create();
        $committee = Committee::create([
            'name' => 'Grievance Committee', 'head_id' => $head->id,
            'revoked_at' => now(), 'revoked_by' => $head->id,
        ]);

        $task = CommitteeTask::create([
            'committee_id'         => $committee->id,
            'title'                => 'Submit monthly minutes',
            'submission_frequency' => 'monthly',
            'created_by'           => $head->id,
        ]);
        $task->assignees()->sync([$member->id]);
        $task->created_at = now()->subMonths(2);
        $task->save();

        $this->artisan('committees:cadence-reminders')->assertExitCode(0);

        Notification::assertNotSentTo($member, RequestStatusNotification::class);
    }
}
