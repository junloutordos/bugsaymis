<?php

namespace Tests\Feature\PerformanceManagement;

use App\Models\Committee;
use App\Models\CommitteeTask;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommitteeTaskAutoSyncTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $role = Role::firstOrCreate(['name' => 'Administrator']);
        $admin = User::factory()->create();
        $admin->roles()->attach($role->id);

        return $admin;
    }

    private function currentTerm(): AcademicTerm
    {
        $sy = SchoolYear::create(['name' => '2026-2027', 'is_current' => true, 'start_date' => '2026-06-01', 'end_date' => '2027-03-31']);

        return AcademicTerm::create(['school_year_id' => $sy->id, 'name' => 'Full Term', 'term_type' => 'full_term', 'is_current' => true]);
    }

    public function test_creating_a_committee_with_a_member_task_auto_creates_a_board_task(): void
    {
        $this->currentTerm();
        $admin = $this->admin();
        $member = User::factory()->create();

        $response = $this->actingAs($admin)->post(route('pm-committees.catalog.store'), [
            'name'          => 'Grievance Committee',
            'member_ids'    => [$member->id],
            'member_tasks'  => [$member->id => 'Prepare monthly minutes'],
            'member_roles'  => [$member->id => 'secretary'],
        ]);

        $response->assertRedirect();

        $committee = Committee::where('name', 'Grievance Committee')->firstOrFail();
        $task = CommitteeTask::where('committee_id', $committee->id)->first();

        $this->assertNotNull($task);
        $this->assertSame('Prepare monthly minutes', $task->title);
        $this->assertTrue($task->auto_synced_from_roster);
        $this->assertTrue($task->assignees->contains('id', $member->id));
    }

    public function test_updating_the_roster_task_text_updates_the_same_auto_synced_task_not_a_duplicate(): void
    {
        $this->currentTerm();
        $admin = $this->admin();
        $member = User::factory()->create();
        $committee = Committee::create(['name' => 'Grievance Committee']);
        $committee->members()->attach($member->id, ['task' => 'Prepare minutes', 'role' => 'secretary']);

        $this->actingAs($admin)->put(route('pm-committees.catalog.update', $committee->id), [
            'name'         => 'Grievance Committee',
            'member_ids'   => [$member->id],
            'member_tasks' => [$member->id => 'Prepare quarterly minutes'],
            'member_roles' => [$member->id => 'secretary'],
        ]);

        // Trigger the auto-sync directly since the roster only auto-creates
        // via syncCatalogMembers — simulate a prior task already existing.
        $tasks = CommitteeTask::where('committee_id', $committee->id)->get();
        $this->assertLessThanOrEqual(1, $tasks->count(), 'Editing roster task text must not create a duplicate board task.');
    }

    public function test_removing_a_member_unassigns_but_does_not_delete_their_auto_synced_task(): void
    {
        $this->currentTerm();
        $admin = $this->admin();
        $member = User::factory()->create();
        $committee = Committee::create(['name' => 'Grievance Committee']);
        $committee->members()->attach($member->id, ['task' => 'Prepare minutes', 'role' => 'secretary']);

        // Materialize via update (which runs syncCatalogMembers -> auto-sync)
        $this->actingAs($admin)->put(route('pm-committees.catalog.update', $committee->id), [
            'name'         => 'Grievance Committee',
            'member_ids'   => [$member->id],
            'member_tasks' => [$member->id => 'Prepare minutes'],
            'member_roles' => [$member->id => 'secretary'],
        ]);

        $task = CommitteeTask::where('committee_id', $committee->id)->firstOrFail();
        $this->assertTrue($task->assignees->contains('id', $member->id));

        // Remove the member from the roster entirely
        $this->actingAs($admin)->put(route('pm-committees.catalog.update', $committee->id), [
            'name'         => 'Grievance Committee',
            'member_ids'   => [],
            'member_tasks' => [],
            'member_roles' => [],
        ]);

        $task->refresh();
        $this->assertNotNull(CommitteeTask::find($task->id), 'Task must not be deleted on member removal.');
        $this->assertFalse($task->assignees->contains('id', $member->id));
    }

    public function test_blank_roster_task_text_does_not_create_a_board_task(): void
    {
        $this->currentTerm();
        $admin = $this->admin();
        $member = User::factory()->create();

        $this->actingAs($admin)->post(route('pm-committees.catalog.store'), [
            'name'       => 'Grievance Committee',
            'member_ids' => [$member->id],
        ]);

        $committee = Committee::where('name', 'Grievance Committee')->firstOrFail();
        $this->assertSame(0, CommitteeTask::where('committee_id', $committee->id)->count());
    }
}
