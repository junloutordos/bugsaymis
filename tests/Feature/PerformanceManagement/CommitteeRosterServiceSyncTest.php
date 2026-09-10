<?php

namespace Tests\Feature\PerformanceManagement;

use App\Models\Committee;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\User;
use App\Services\FacultyLoading\CommitteeRosterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommitteeRosterServiceSyncTest extends TestCase
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

    public function test_reconcile_syncs_the_newly_added_roster_member(): void
    {
        $term = $this->currentTerm();
        $member = User::factory()->create();
        $committee = Committee::create(['name' => 'Grievance Committee']);
        $committee->members()->attach($member->id);

        app(CommitteeRosterService::class)->reconcileCommitteeRoster($committee, $term->school_year_id, $term->id);

        $this->assertDatabaseHas('employee_functions', [
            'user_id' => $member->id, 'label' => 'Grievance Committee',
        ]);
    }

    public function test_reconcile_with_zero_load_units_does_not_create_a_load_assignment(): void
    {
        $term = $this->currentTerm();
        $member = User::factory()->create();
        // member_load_units left unset (null -> 0), matching a committee
        // role that carries no unit load.
        $committee = Committee::create(['name' => 'Grievance Committee']);
        $committee->members()->attach($member->id);

        app(CommitteeRosterService::class)->reconcileCommitteeRoster($committee, $term->school_year_id, $term->id);

        $assignment = \App\Models\FacultyLoading\FacultyCommitteeAssignment::where('user_id', $member->id)->firstOrFail();
        $this->assertNull($assignment->load_assignment_id);
        $this->assertDatabaseCount('load_assignments', 0);

        $this->assertDatabaseHas('employee_functions', [
            'user_id' => $member->id,
            'label' => 'Grievance Committee',
            'function_type' => \App\Models\EmployeeFunction::TYPE_SUPPORT,
        ]);
    }

    public function test_reconcile_with_positive_load_units_creates_a_load_assignment(): void
    {
        $term = $this->currentTerm();
        $member = User::factory()->create();
        $committee = Committee::create(['name' => 'Grievance Committee', 'member_load_units' => 1]);
        $committee->members()->attach($member->id);

        app(CommitteeRosterService::class)->reconcileCommitteeRoster($committee, $term->school_year_id, $term->id);

        $assignment = \App\Models\FacultyLoading\FacultyCommitteeAssignment::where('user_id', $member->id)->firstOrFail();
        $this->assertNotNull($assignment->load_assignment_id);
        $this->assertDatabaseCount('load_assignments', 1);

        $this->assertDatabaseHas('employee_functions', [
            'user_id' => $member->id,
            'label' => 'Grievance Committee',
            'function_type' => \App\Models\EmployeeFunction::TYPE_CORE,
        ]);
    }

    public function test_reconcile_re_syncs_a_deactivated_member_so_the_row_is_removed(): void
    {
        $term = $this->currentTerm();
        $member = User::factory()->create();
        $committee = Committee::create(['name' => 'Grievance Committee']);
        $committee->members()->attach($member->id);
        app(CommitteeRosterService::class)->reconcileCommitteeRoster($committee, $term->school_year_id, $term->id);

        $committee->members()->detach($member->id);
        $committee->refresh();
        app(CommitteeRosterService::class)->reconcileCommitteeRoster($committee, $term->school_year_id, $term->id);

        $this->assertDatabaseCount('employee_functions', 0);
    }
}
