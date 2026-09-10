<?php

namespace Tests\Feature\PerformanceManagement;

use App\Models\Committee;
use App\Models\EmployeeFunction;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\FacultyCommitteeAssignment;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\IPCRRatingPeriod;
use App\Models\IPCRV2\IpcrV2Record;
use App\Models\Role;
use App\Models\User;
use App\Services\PerformanceManagement\CommitteeIpcrSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommitteeDeletionCascadeTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $role = Role::create(['name' => 'Administrator']);
        $admin = User::factory()->create();
        $admin->roles()->attach($role->id);

        return $admin;
    }

    private function currentTerm(): AcademicTerm
    {
        $sy = SchoolYear::create(['name' => '2026-2027', 'is_current' => true, 'start_date' => '2026-06-01', 'end_date' => '2027-03-31']);

        return AcademicTerm::create(['school_year_id' => $sy->id, 'name' => 'Full Term', 'term_type' => 'full_term', 'is_current' => true]);
    }

    public function test_deleting_a_committee_removes_its_assignments_and_employee_function_rows(): void
    {
        $admin = $this->admin();
        $term = $this->currentTerm();
        $member = User::factory()->create();
        $committee = Committee::create(['name' => 'Grievance Committee']);
        FacultyCommitteeAssignment::create([
            'user_id' => $member->id, 'school_year_id' => $term->school_year_id, 'academic_term_id' => $term->id,
            'committee_id' => $committee->id, 'committee_name' => $committee->name, 'role' => 'member', 'status' => 'active',
        ]);
        (new CommitteeIpcrSyncService())->syncForUser($member);
        $this->assertDatabaseHas('employee_functions', ['user_id' => $member->id, 'label' => 'Grievance Committee']);

        $response = $this->actingAs($admin)->delete(route('pm-committees.catalog.destroy', $committee->id));

        $response->assertRedirect();
        $this->assertDatabaseMissing('committees', ['id' => $committee->id]);
        $this->assertDatabaseMissing('faculty_committee_assignments', ['committee_id' => $committee->id]);
        $this->assertDatabaseMissing('employee_functions', ['user_id' => $member->id, 'label' => 'Grievance Committee']);
    }

    public function test_deleting_a_committee_also_cleans_up_its_load_assignment(): void
    {
        $admin = $this->admin();
        $term = $this->currentTerm();
        $member = User::factory()->create();
        $committee = Committee::create(['name' => 'Budget Committee', 'chairperson_load_units' => 2]);
        FacultyCommitteeAssignment::create([
            'user_id' => $member->id, 'school_year_id' => $term->school_year_id, 'academic_term_id' => $term->id,
            'committee_id' => $committee->id, 'committee_name' => $committee->name, 'role' => 'chairperson', 'status' => 'active',
            'load_units' => 2,
        ]);
        $laId = FacultyCommitteeAssignment::where('committee_id', $committee->id)->value('load_assignment_id');

        $this->actingAs($admin)->delete(route('pm-committees.catalog.destroy', $committee->id))->assertRedirect();

        if ($laId) {
            $this->assertDatabaseMissing('load_assignments', ['id' => $laId]);
        }
    }

    public function test_deleting_a_committee_removes_its_ipcr_v2_materialized_item_when_unrated(): void
    {
        $admin = $this->admin();
        $term = $this->currentTerm();
        $member = User::factory()->create();
        $committee = Committee::create(['name' => 'Grievance Committee']);
        FacultyCommitteeAssignment::create([
            'user_id' => $member->id, 'school_year_id' => $term->school_year_id, 'academic_term_id' => $term->id,
            'committee_id' => $committee->id, 'committee_name' => $committee->name, 'role' => 'member', 'status' => 'active',
        ]);
        $period = IPCRRatingPeriod::create(['label' => 'FY2026-1', 'year' => 2026, 'semester' => 1, 'status' => 'open', 'is_current' => true]);
        $record = IpcrV2Record::create(['user_id' => $member->id, 'rating_period_id' => $period->id]);
        (new CommitteeIpcrSyncService())->syncForUser($member);
        $this->assertDatabaseHas('ipcr_v2_support_items', ['ipcr_v2_id' => $record->id, 'label' => 'Grievance Committee']);

        $this->actingAs($admin)->delete(route('pm-committees.catalog.destroy', $committee->id))->assertRedirect();

        // employee_function_id gets nulled (FK nullOnDelete) by the EmployeeFunction
        // row's own deletion; the item itself is pruned too since it was never rated.
        $this->assertDatabaseMissing('ipcr_v2_support_items', ['ipcr_v2_id' => $record->id, 'label' => 'Grievance Committee']);
    }

    public function test_deleting_a_committee_preserves_a_materialized_item_that_already_has_an_accomplishment(): void
    {
        $admin = $this->admin();
        $term = $this->currentTerm();
        $member = User::factory()->create();
        $committee = Committee::create(['name' => 'Grievance Committee']);
        FacultyCommitteeAssignment::create([
            'user_id' => $member->id, 'school_year_id' => $term->school_year_id, 'academic_term_id' => $term->id,
            'committee_id' => $committee->id, 'committee_name' => $committee->name, 'role' => 'member', 'status' => 'active',
        ]);
        $period = IPCRRatingPeriod::create(['label' => 'FY2026-1', 'year' => 2026, 'semester' => 1, 'status' => 'open', 'is_current' => true]);
        $record = IpcrV2Record::create(['user_id' => $member->id, 'rating_period_id' => $period->id]);
        (new CommitteeIpcrSyncService())->syncForUser($member);
        $record->supportItems()->where('label', 'Grievance Committee')->update(['actual_accomplishment' => 'Attended 3 sessions.']);

        $this->actingAs($admin)->delete(route('pm-committees.catalog.destroy', $committee->id))->assertRedirect();

        // EmployeeFunctionSyncService's own cleanup rule keeps the
        // EmployeeFunction row alive when real accomplishment data is
        // already logged against it (same rule that already protects a
        // deactivated/removed committee assignment elsewhere) — so
        // neither the function nor its rated IPCR V2 item is dropped.
        $this->assertDatabaseHas('employee_functions', ['user_id' => $member->id, 'label' => 'Grievance Committee']);
        $this->assertDatabaseHas('ipcr_v2_support_items', [
            'ipcr_v2_id' => $record->id, 'label' => 'Grievance Committee', 'actual_accomplishment' => 'Attended 3 sessions.',
        ]);
    }

    public function test_deleting_a_main_committee_also_cascades_its_sub_committees_assignments(): void
    {
        $admin = $this->admin();
        $term = $this->currentTerm();
        $member = User::factory()->create();
        $main = Committee::create(['name' => 'Main Committee']);
        $sub = Committee::create(['name' => 'Sub Committee', 'parent_committee_id' => $main->id]);
        FacultyCommitteeAssignment::create([
            'user_id' => $member->id, 'school_year_id' => $term->school_year_id, 'academic_term_id' => $term->id,
            'committee_id' => $sub->id, 'committee_name' => $sub->name, 'role' => 'member', 'status' => 'active',
        ]);

        $this->actingAs($admin)->delete(route('pm-committees.catalog.destroy', $main->id))->assertRedirect();

        $this->assertDatabaseMissing('faculty_committee_assignments', ['committee_id' => $sub->id]);
    }

    public function test_a_non_admin_cannot_delete_a_committee(): void
    {
        $term = $this->currentTerm();
        $outsider = User::factory()->create();
        $committee = Committee::create(['name' => 'Grievance Committee']);

        $this->actingAs($outsider)->delete(route('pm-committees.catalog.destroy', $committee->id))
            ->assertForbidden();

        $this->assertDatabaseHas('committees', ['id' => $committee->id]);
    }
}
