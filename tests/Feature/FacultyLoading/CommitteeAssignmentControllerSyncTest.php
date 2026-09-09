<?php

namespace Tests\Feature\FacultyLoading;

use App\Models\EmployeeFunction;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\FacultyCommitteeAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\Committee;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommitteeAssignmentControllerSyncTest extends TestCase
{
    use RefreshDatabase;

    private function currentTerm(): AcademicTerm
    {
        $sy = SchoolYear::create([
            'name' => '2026-2027', 'is_current' => true,
            'start_date' => '2026-06-01', 'end_date' => '2027-03-31',
        ]);

        return AcademicTerm::create([
            'school_year_id' => $sy->id, 'name' => 'Full Term',
            'term_type' => 'full_term', 'is_current' => true,
        ]);
    }

    private function admin(): User
    {
        $role = Role::create(['name' => 'TestRole_' . uniqid()]);
        $perm = Permission::firstOrCreate(['name' => 'faculty_loading.manage'], ['module' => 'FacultyLoading', 'description' => 'faculty_loading.manage']);
        $role->permissions()->attach($perm->id);
        $admin = User::factory()->create(['email_verified_at' => now()]);
        $admin->roles()->attach($role->id);

        return $admin;
    }

    public function test_store_syncs_the_assigned_members_support_function(): void
    {
        $term = $this->currentTerm();
        $admin = $this->admin();
        $member = User::factory()->create();

        $this->actingAs($admin)->post(route('faculty-loading.committee-assignments.store'), [
            'user_id' => $member->id,
            'school_year_id' => $term->school_year_id,
            'academic_term_id' => $term->id,
            'committee_id' => null,
            'committee_name' => 'Discipline Committee',
            'role' => 'member',
            'load_units' => 0,
        ])->assertRedirect();

        $this->assertDatabaseHas('employee_functions', [
            'user_id' => $member->id, 'label' => 'Discipline Committee',
        ]);
    }

    public function test_destroy_re_syncs_so_the_support_function_is_removed(): void
    {
        $term = $this->currentTerm();
        $admin = $this->admin();
        $member = User::factory()->create();

        $this->actingAs($admin)->post(route('faculty-loading.committee-assignments.store'), [
            'user_id' => $member->id,
            'school_year_id' => $term->school_year_id,
            'academic_term_id' => $term->id,
            'committee_id' => null,
            'committee_name' => 'Discipline Committee',
            'role' => 'member',
            'load_units' => 0,
        ]);
        $assignment = FacultyCommitteeAssignment::where('user_id', $member->id)->firstOrFail();

        $this->actingAs($admin)->delete(route('faculty-loading.committee-assignments.destroy', $assignment->id))
            ->assertRedirect();

        $this->assertDatabaseCount('employee_functions', 0);
    }

    public function test_committee_wdp_retag_re_syncs_every_active_member(): void
    {
        $term = $this->currentTerm();
        $admin = $this->admin();
        $member = User::factory()->create();
        $committee = Committee::create(['name' => 'Sports Committee']);
        FacultyCommitteeAssignment::create([
            'user_id' => $member->id, 'school_year_id' => $term->school_year_id,
            'academic_term_id' => $term->id, 'committee_id' => $committee->id,
            'committee_name' => $committee->name, 'role' => 'member', 'status' => 'active',
        ]);
        $assignmentId = FacultyCommitteeAssignment::where('user_id', $member->id)->value('id');

        $this->actingAs($admin)->put(route('faculty-loading.committee-assignments.update', $assignmentId), [
            'role' => 'chairperson', 'load_units' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('employee_functions', [
            'user_id' => $member->id, 'function_type' => EmployeeFunction::TYPE_CORE,
        ]);
    }
}
