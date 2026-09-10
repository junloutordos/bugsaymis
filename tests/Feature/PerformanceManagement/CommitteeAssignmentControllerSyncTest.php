<?php

namespace Tests\Feature\PerformanceManagement;

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
        $facultyPerm = Permission::firstOrCreate(['name' => 'faculty_loading.manage'], ['module' => 'FacultyLoading', 'description' => 'faculty_loading.manage']);
        $employeeFunctionsPerm = Permission::firstOrCreate(['name' => 'employee_functions.manage'], ['module' => 'Employee Functions', 'description' => 'employee_functions.manage']);
        $role->permissions()->attach([$facultyPerm->id, $employeeFunctionsPerm->id]);
        $admin = User::factory()->create(['email_verified_at' => now()]);
        $admin->roles()->attach($role->id);

        return $admin;
    }

    public function test_store_syncs_the_assigned_members_support_function(): void
    {
        $term = $this->currentTerm();
        $admin = $this->admin();
        $member = User::factory()->create();

        $this->actingAs($admin)->post(route('pm-committees.store'), [
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

    public function test_store_with_zero_load_units_does_not_create_a_load_assignment(): void
    {
        $term = $this->currentTerm();
        $admin = $this->admin();
        $member = User::factory()->create();

        $this->actingAs($admin)->post(route('pm-committees.store'), [
            'user_id' => $member->id,
            'school_year_id' => $term->school_year_id,
            'academic_term_id' => $term->id,
            'committee_id' => null,
            'committee_name' => 'Discipline Committee',
            'role' => 'member',
            'load_units' => 0,
        ])->assertRedirect();

        $assignment = FacultyCommitteeAssignment::where('user_id', $member->id)->firstOrFail();
        $this->assertNull($assignment->load_assignment_id);
        $this->assertDatabaseCount('load_assignments', 0);

        $this->assertDatabaseHas('employee_functions', [
            'user_id' => $member->id,
            'label' => 'Discipline Committee',
            'function_type' => EmployeeFunction::TYPE_SUPPORT,
        ]);
    }

    public function test_store_with_positive_load_units_creates_a_load_assignment(): void
    {
        $term = $this->currentTerm();
        $admin = $this->admin();
        $member = User::factory()->create();

        $this->actingAs($admin)->post(route('pm-committees.store'), [
            'user_id' => $member->id,
            'school_year_id' => $term->school_year_id,
            'academic_term_id' => $term->id,
            'committee_id' => null,
            'committee_name' => 'Discipline Committee',
            'role' => 'chairperson',
            'load_units' => 1,
        ])->assertRedirect();

        $assignment = FacultyCommitteeAssignment::where('user_id', $member->id)->firstOrFail();
        $this->assertNotNull($assignment->load_assignment_id);
        $this->assertDatabaseCount('load_assignments', 1);

        $this->assertDatabaseHas('employee_functions', [
            'user_id' => $member->id,
            'label' => 'Discipline Committee',
            'function_type' => EmployeeFunction::TYPE_CORE,
        ]);
    }

    public function test_update_dropping_load_units_to_zero_removes_the_load_assignment(): void
    {
        $term = $this->currentTerm();
        $admin = $this->admin();
        $member = User::factory()->create();

        $this->actingAs($admin)->post(route('pm-committees.store'), [
            'user_id' => $member->id,
            'school_year_id' => $term->school_year_id,
            'academic_term_id' => $term->id,
            'committee_id' => null,
            'committee_name' => 'Discipline Committee',
            'role' => 'chairperson',
            'load_units' => 1,
        ]);
        $assignment = FacultyCommitteeAssignment::where('user_id', $member->id)->firstOrFail();
        $this->assertNotNull($assignment->load_assignment_id);

        $this->actingAs($admin)->put(route('pm-committees.update', $assignment->id), [
            'role' => 'member', 'load_units' => 0,
        ])->assertRedirect();

        $this->assertNull($assignment->refresh()->load_assignment_id);
        $this->assertDatabaseCount('load_assignments', 0);

        $this->assertDatabaseHas('employee_functions', [
            'user_id' => $member->id,
            'label' => 'Discipline Committee',
            'function_type' => EmployeeFunction::TYPE_SUPPORT,
        ]);
    }

    public function test_update_raising_load_units_from_zero_creates_a_load_assignment(): void
    {
        $term = $this->currentTerm();
        $admin = $this->admin();
        $member = User::factory()->create();

        $this->actingAs($admin)->post(route('pm-committees.store'), [
            'user_id' => $member->id,
            'school_year_id' => $term->school_year_id,
            'academic_term_id' => $term->id,
            'committee_id' => null,
            'committee_name' => 'Discipline Committee',
            'role' => 'member',
            'load_units' => 0,
        ]);
        $assignment = FacultyCommitteeAssignment::where('user_id', $member->id)->firstOrFail();
        $this->assertNull($assignment->load_assignment_id);

        $this->actingAs($admin)->put(route('pm-committees.update', $assignment->id), [
            'role' => 'chairperson', 'load_units' => 1,
        ])->assertRedirect();

        $this->assertNotNull($assignment->refresh()->load_assignment_id);
        $this->assertDatabaseCount('load_assignments', 1);

        $this->assertDatabaseHas('employee_functions', [
            'user_id' => $member->id,
            'label' => 'Discipline Committee',
            'function_type' => EmployeeFunction::TYPE_CORE,
        ]);
    }

    /**
     * Full-stack regression for the reported bug: HR manually deletes the
     * Support Function from the Employee Functions page (not the
     * committee assignment itself); the committee is later edited (role/
     * load_units change, triggering CommitteeRosterService's automatic
     * re-sync) — the deleted Function must stay gone.
     */
    public function test_manually_deleted_function_does_not_reappear_after_a_later_committee_update(): void
    {
        $term = $this->currentTerm();
        $admin = $this->admin();
        $member = User::factory()->create();

        $this->actingAs($admin)->post(route('pm-committees.store'), [
            'user_id' => $member->id,
            'school_year_id' => $term->school_year_id,
            'academic_term_id' => $term->id,
            'committee_id' => null,
            'committee_name' => 'Discipline Committee',
            'role' => 'member',
            'load_units' => 0,
        ]);

        $function = EmployeeFunction::where('user_id', $member->id)->firstOrFail();
        $this->actingAs($admin)->delete(route('employee-functions.destroy', [$member, $function]))->assertRedirect();
        $this->assertDatabaseCount('employee_functions', 0);

        // Edit the same assignment (e.g. HR tweaks the remarks/role) —
        // this is exactly what CommitteeAssignmentController::update()
        // does, which always re-syncs via CommitteeIpcrSyncService.
        $assignment = FacultyCommitteeAssignment::where('user_id', $member->id)->firstOrFail();
        $this->actingAs($admin)->put(route('pm-committees.update', $assignment->id), [
            'role' => 'secretary', 'load_units' => 0,
        ])->assertRedirect();

        $this->assertDatabaseCount('employee_functions', 0);
    }

    public function test_destroy_re_syncs_so_the_support_function_is_removed(): void
    {
        $term = $this->currentTerm();
        $admin = $this->admin();
        $member = User::factory()->create();

        $this->actingAs($admin)->post(route('pm-committees.store'), [
            'user_id' => $member->id,
            'school_year_id' => $term->school_year_id,
            'academic_term_id' => $term->id,
            'committee_id' => null,
            'committee_name' => 'Discipline Committee',
            'role' => 'member',
            'load_units' => 0,
        ]);
        $assignment = FacultyCommitteeAssignment::where('user_id', $member->id)->firstOrFail();

        $this->actingAs($admin)->delete(route('pm-committees.destroy', $assignment->id))
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

        $this->actingAs($admin)->put(route('pm-committees.update', $assignmentId), [
            'role' => 'chairperson', 'load_units' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('employee_functions', [
            'user_id' => $member->id, 'function_type' => EmployeeFunction::TYPE_CORE,
        ]);
    }
}
