<?php

namespace Tests\Feature\PerformanceManagement;

use App\Models\Committee;
use App\Models\CommitteeTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommitteeTaskCadenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_next_due_date_is_null_when_no_cadence_is_set(): void
    {
        $committee = Committee::create(['name' => 'Grievance Committee']);
        $task = CommitteeTask::create([
            'committee_id' => $committee->id,
            'title'        => 'Ad hoc task',
            'created_by'   => User::factory()->create()->id,
        ]);

        $this->assertNull($task->nextDueDate());
    }

    public function test_next_due_date_is_null_for_varies_and_one_time_cadences(): void
    {
        $committee = Committee::create(['name' => 'Grievance Committee']);
        $user = User::factory()->create();

        foreach (['varies', 'one_time', 'end_of_rating_period'] as $freq) {
            $task = CommitteeTask::create([
                'committee_id'         => $committee->id,
                'title'                => "Task {$freq}",
                'submission_frequency' => $freq,
                'created_by'           => $user->id,
            ]);
            $this->assertNull($task->nextDueDate(), "Expected null next-due for {$freq}");
        }
    }

    public function test_monthly_cadence_computes_next_due_one_month_from_creation_when_no_accomplishment_yet(): void
    {
        $committee = Committee::create(['name' => 'Grievance Committee']);
        $task = CommitteeTask::create([
            'committee_id'         => $committee->id,
            'title'                => 'Submit monthly minutes',
            'submission_frequency' => 'monthly',
            'created_by'           => User::factory()->create()->id,
        ]);

        $expected = $task->created_at->copy()->addMonth();
        $this->assertTrue($task->nextDueDate()->isSameDay($expected));
    }

    public function test_monthly_cadence_computes_next_due_from_latest_accomplishment_update(): void
    {
        $committee = Committee::create(['name' => 'Grievance Committee']);
        $member = User::factory()->create();
        $task = CommitteeTask::create([
            'committee_id'         => $committee->id,
            'title'                => 'Submit monthly minutes',
            'submission_frequency' => 'monthly',
            'created_by'           => $member->id,
        ]);
        $task->assignees()->sync([$member->id]);

        $update = $task->updates()->create([
            'user_id'           => $member->id,
            'body'              => 'August minutes submitted.',
            'is_accomplishment' => true,
            'mov_link'          => 'https://drive.google.com/example',
        ]);

        $task->refresh();
        $expected = $update->created_at->copy()->addMonth();
        $this->assertTrue($task->nextDueDate()->isSameDay($expected));
    }

    public function test_quarterly_and_annually_cadences_add_the_correct_interval(): void
    {
        $committee = Committee::create(['name' => 'Grievance Committee']);
        $member = User::factory()->create();

        $quarterly = CommitteeTask::create([
            'committee_id' => $committee->id, 'title' => 'Quarterly report',
            'submission_frequency' => 'quarterly', 'created_by' => $member->id,
        ]);
        $annually = CommitteeTask::create([
            'committee_id' => $committee->id, 'title' => 'Annual report',
            'submission_frequency' => 'annually', 'created_by' => $member->id,
        ]);

        $this->assertTrue($quarterly->nextDueDate()->isSameDay($quarterly->created_at->copy()->addMonths(3)));
        $this->assertTrue($annually->nextDueDate()->isSameDay($annually->created_at->copy()->addYear()));
    }

    public function test_committee_default_submission_frequency_prefills_new_board_tasks(): void
    {
        $committee = Committee::create(['name' => 'Grievance Committee', 'default_submission_frequency' => 'quarterly']);
        $head = User::factory()->create();
        $committee->update(['head_id' => $head->id]);

        $response = $this->actingAs($head)->post(route('committee-tasks.store', $committee->id), [
            'title' => 'New task without explicit cadence',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('committee_tasks', [
            'committee_id'          => $committee->id,
            'title'                 => 'New task without explicit cadence',
            'submission_frequency'  => 'quarterly',
        ]);
    }
}
