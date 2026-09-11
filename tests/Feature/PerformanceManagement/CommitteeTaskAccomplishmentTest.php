<?php

namespace Tests\Feature\PerformanceManagement;

use App\Models\Committee;
use App\Models\CommitteeTask;
use App\Models\CommitteeTaskUpdate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommitteeTaskAccomplishmentTest extends TestCase
{
    use RefreshDatabase;

    private function makeTaskWithAssignee(): array
    {
        $committee = Committee::create(['name' => 'Grievance Committee']);
        $member    = User::factory()->create();
        $task      = CommitteeTask::create([
            'committee_id' => $committee->id,
            'title'        => 'Submit monthly minutes',
            'status'       => 'working_on_it',
            'priority'     => 'medium',
            'created_by'   => $member->id,
        ]);
        $task->assignees()->sync([$member->id]);

        return [$committee, $task, $member];
    }

    public function test_assignee_can_submit_an_accomplishment_with_mov_link(): void
    {
        [, $task, $member] = $this->makeTaskWithAssignee();

        $response = $this->actingAs($member)->post(route('committee-tasks.updates.store', $task->id), [
            'body'              => 'Submitted the minutes for August.',
            'is_accomplishment' => true,
            'mov_link'          => 'https://drive.google.com/example',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('committee_task_updates', [
            'committee_task_id' => $task->id,
            'user_id'            => $member->id,
            'is_accomplishment'  => true,
            'mov_link'           => 'https://drive.google.com/example',
        ]);
    }

    public function test_accomplishment_submission_requires_a_mov_link(): void
    {
        [, $task, $member] = $this->makeTaskWithAssignee();

        $response = $this->actingAs($member)->post(route('committee-tasks.updates.store', $task->id), [
            'body'              => 'Submitted the minutes for August.',
            'is_accomplishment' => true,
        ]);

        $response->assertSessionHasErrors('mov_link');
        $this->assertDatabaseMissing('committee_task_updates', [
            'committee_task_id' => $task->id,
        ]);
    }

    public function test_plain_update_does_not_require_a_mov_link(): void
    {
        [, $task, $member] = $this->makeTaskWithAssignee();

        $response = $this->actingAs($member)->post(route('committee-tasks.updates.store', $task->id), [
            'body' => 'Still working on it.',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('committee_task_updates', [
            'committee_task_id' => $task->id,
            'is_accomplishment'  => false,
        ]);
    }

    public function test_non_assignee_non_manager_cannot_post_an_update(): void
    {
        [, $task] = $this->makeTaskWithAssignee();
        $outsider = User::factory()->create();

        $response = $this->actingAs($outsider)->post(route('committee-tasks.updates.store', $task->id), [
            'body' => 'Trying to post.',
        ]);

        $response->assertForbidden();
    }

    public function test_latest_accomplishment_relation_returns_most_recent_flagged_update(): void
    {
        [, $task, $member] = $this->makeTaskWithAssignee();

        CommitteeTaskUpdate::create(['committee_task_id' => $task->id, 'user_id' => $member->id, 'body' => 'Old note', 'is_accomplishment' => false]);
        $older = CommitteeTaskUpdate::create(['committee_task_id' => $task->id, 'user_id' => $member->id, 'body' => 'July accomplishment', 'is_accomplishment' => true, 'mov_link' => 'https://a']);
        $older->created_at = now()->subDays(30);
        $older->save();
        $newer = CommitteeTaskUpdate::create(['committee_task_id' => $task->id, 'user_id' => $member->id, 'body' => 'August accomplishment', 'is_accomplishment' => true, 'mov_link' => 'https://b']);

        $latest = $task->latestAccomplishment();

        $this->assertSame($newer->id, $latest->id);
    }
}
