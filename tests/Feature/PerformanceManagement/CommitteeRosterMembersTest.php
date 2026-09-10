<?php

namespace Tests\Feature\PerformanceManagement;

use App\Models\Committee;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\FacultyCommitteeAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommitteeRosterMembersTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $role = Role::create(['name' => 'TestRole_' . uniqid()]);
        $perm = Permission::firstOrCreate(['name' => 'faculty_loading.manage'], ['module' => 'FacultyLoading', 'description' => 'faculty_loading.manage']);
        $role->permissions()->attach($perm->id);
        $admin = User::factory()->create();
        $admin->roles()->attach($role->id);

        return $admin;
    }

    private function accomplishmentsViewer(): User
    {
        $role = Role::create(['name' => 'TestRole_' . uniqid()]);
        $perm = Permission::firstOrCreate(['name' => 'accomplishments.view'], ['module' => 'PerformanceManagement', 'description' => 'accomplishments.view']);
        $role->permissions()->attach($perm->id);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        return $user;
    }

    private function currentTerm(): AcademicTerm
    {
        $sy = SchoolYear::create(['name' => '2026-2027', 'is_current' => true, 'start_date' => '2026-06-01', 'end_date' => '2027-03-31']);

        return AcademicTerm::create(['school_year_id' => $sy->id, 'name' => 'Full Term', 'term_type' => 'full_term', 'is_current' => true]);
    }

    public function test_show_exposes_the_roster_with_task_and_role_from_the_catalog_pivot(): void
    {
        $this->currentTerm();
        $admin = $this->admin();
        $member = User::factory()->create();
        $committee = Committee::create(['name' => 'Grievance Committee']);
        $committee->members()->attach($member->id, ['task' => 'Documentation', 'role' => 'secretary']);

        $response = $this->actingAs($admin)->get(route('pm-committees.show', $committee->id));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('PerformanceManagement/Committees/Show')
            ->has('roster', 1)
            ->where('roster.0.user_id', $member->id)
            ->where('roster.0.task', 'Documentation')
            ->where('roster.0.role', 'secretary')
        );
    }

    public function test_admin_can_add_a_member_via_the_show_page(): void
    {
        $this->currentTerm();
        $admin = $this->admin();
        $member = User::factory()->create();
        $committee = Committee::create(['name' => 'Grievance Committee']);

        $response = $this->actingAs($admin)->post(route('pm-committees.members.store', $committee->id), [
            'user_id' => $member->id,
            'role'    => 'member',
            'task'    => 'Logistics',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('committee_user', [
            'committee_id' => $committee->id,
            'user_id'      => $member->id,
            'task'         => 'Logistics',
            'role'         => 'member',
        ]);
    }

    public function test_adding_a_duplicate_member_is_rejected(): void
    {
        $this->currentTerm();
        $admin = $this->admin();
        $member = User::factory()->create();
        $committee = Committee::create(['name' => 'Grievance Committee']);
        $committee->members()->attach($member->id, ['task' => null, 'role' => 'member']);

        $response = $this->actingAs($admin)->post(route('pm-committees.members.store', $committee->id), [
            'user_id' => $member->id,
            'role'    => 'member',
        ]);

        $response->assertStatus(422);
    }

    public function test_a_committee_chairperson_can_add_a_member_without_faculty_loading_manage(): void
    {
        $term = $this->currentTerm();
        $chair = $this->accomplishmentsViewer();
        $newMember = User::factory()->create();
        $committee = Committee::create(['name' => 'Grievance Committee']);
        FacultyCommitteeAssignment::create([
            'user_id' => $chair->id, 'school_year_id' => $term->school_year_id, 'academic_term_id' => $term->id,
            'committee_id' => $committee->id, 'committee_name' => $committee->name, 'role' => 'chairperson', 'status' => 'active',
        ]);

        $response = $this->actingAs($chair)->post(route('pm-committees.members.store', $committee->id), [
            'user_id' => $newMember->id,
            'role'    => 'member',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('committee_user', [
            'committee_id' => $committee->id,
            'user_id'      => $newMember->id,
        ]);
    }

    public function test_a_non_chair_member_cannot_add_a_member(): void
    {
        $term = $this->currentTerm();
        $outsider = $this->accomplishmentsViewer();
        $newMember = User::factory()->create();
        $committee = Committee::create(['name' => 'Grievance Committee']);

        $response = $this->actingAs($outsider)->post(route('pm-committees.members.store', $committee->id), [
            'user_id' => $newMember->id,
            'role'    => 'member',
        ]);

        $response->assertForbidden();
    }

    public function test_admin_can_remove_a_member(): void
    {
        $this->currentTerm();
        $admin = $this->admin();
        $member = User::factory()->create();
        $committee = Committee::create(['name' => 'Grievance Committee']);
        $committee->members()->attach($member->id, ['task' => null, 'role' => 'member']);

        $response = $this->actingAs($admin)->delete(route('pm-committees.members.destroy', [$committee->id, $member->id]));

        $response->assertRedirect();
        $this->assertDatabaseMissing('committee_user', [
            'committee_id' => $committee->id,
            'user_id'      => $member->id,
        ]);
    }

    public function test_the_committee_head_cannot_be_removed_via_this_endpoint(): void
    {
        $this->currentTerm();
        $admin = $this->admin();
        $head = User::factory()->create();
        $committee = Committee::create(['name' => 'Grievance Committee', 'head_id' => $head->id]);
        $committee->members()->attach($head->id, ['task' => null, 'role' => 'member']);

        $response = $this->actingAs($admin)->delete(route('pm-committees.members.destroy', [$committee->id, $head->id]));

        $response->assertStatus(422);
        $this->assertDatabaseHas('committee_user', [
            'committee_id' => $committee->id,
            'user_id'      => $head->id,
        ]);
    }
}
