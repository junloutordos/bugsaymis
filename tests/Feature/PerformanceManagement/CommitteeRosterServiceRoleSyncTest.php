<?php

namespace Tests\Feature\PerformanceManagement;

use App\Models\Committee;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\FacultyCommitteeAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\User;
use App\Services\FacultyLoading\CommitteeRosterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommitteeRosterServiceRoleSyncTest extends TestCase
{
    use RefreshDatabase;

    private function currentTerm(): AcademicTerm
    {
        $sy = SchoolYear::create(['name' => '2026-2027', 'is_current' => true, 'start_date' => '2026-06-01', 'end_date' => '2027-03-31']);

        return AcademicTerm::create(['school_year_id' => $sy->id, 'name' => 'Full Term', 'term_type' => 'full_term', 'is_current' => true]);
    }

    public function test_reconcile_reads_secretary_role_from_the_committee_user_pivot(): void
    {
        $term = $this->currentTerm();
        $secretary = User::factory()->create();
        $committee = Committee::create(['name' => 'Grievance Committee']);
        $committee->members()->attach($secretary->id, ['role' => 'secretary']);

        app(CommitteeRosterService::class)->reconcileCommitteeRoster($committee, $term->school_year_id, $term->id);

        $this->assertDatabaseHas('faculty_committee_assignments', [
            'user_id' => $secretary->id, 'committee_id' => $committee->id, 'role' => 'secretary',
        ]);
    }

    public function test_reconcile_reads_co_chair_role_from_the_committee_user_pivot(): void
    {
        $term = $this->currentTerm();
        $coChair = User::factory()->create();
        $committee = Committee::create(['name' => 'Grievance Committee']);
        $committee->members()->attach($coChair->id, ['role' => 'co_chair']);

        app(CommitteeRosterService::class)->reconcileCommitteeRoster($committee, $term->school_year_id, $term->id);

        $this->assertDatabaseHas('faculty_committee_assignments', [
            'user_id' => $coChair->id, 'committee_id' => $committee->id, 'role' => 'co_chair',
        ]);
    }

    public function test_reconcile_applies_the_per_member_load_units_override(): void
    {
        $term = $this->currentTerm();
        $member = User::factory()->create();
        $committee = Committee::create(['name' => 'Grievance Committee', 'member_load_units' => 0.5]);
        $committee->members()->attach($member->id, ['role' => 'member', 'load_units_override' => 2.0]);

        app(CommitteeRosterService::class)->reconcileCommitteeRoster($committee, $term->school_year_id, $term->id);

        $this->assertDatabaseHas('faculty_committee_assignments', [
            'user_id' => $member->id, 'committee_id' => $committee->id, 'load_units' => 2.0,
        ]);
    }

    public function test_reconcile_re_syncs_a_role_change_on_an_existing_active_assignment(): void
    {
        $term = $this->currentTerm();
        $member = User::factory()->create();
        $committee = Committee::create(['name' => 'Grievance Committee']);
        $committee->members()->attach($member->id, ['role' => 'member']);
        app(CommitteeRosterService::class)->reconcileCommitteeRoster($committee, $term->school_year_id, $term->id);

        $committee->members()->updateExistingPivot($member->id, ['role' => 'secretary']);
        $committee->refresh();
        app(CommitteeRosterService::class)->reconcileCommitteeRoster($committee, $term->school_year_id, $term->id);

        $this->assertDatabaseHas('faculty_committee_assignments', [
            'user_id' => $member->id, 'committee_id' => $committee->id, 'role' => 'secretary', 'status' => 'active',
        ]);
        $this->assertDatabaseCount('faculty_committee_assignments', 1);
    }

    public function test_chairperson_via_head_id_is_unaffected_by_pivot_role(): void
    {
        $term = $this->currentTerm();
        $chair = User::factory()->create();
        $committee = Committee::create(['name' => 'Grievance Committee', 'head_id' => $chair->id]);
        // Even if the pivot role were 'member' for the head, head_id always wins.
        $committee->members()->attach($chair->id, ['role' => 'member']);

        app(CommitteeRosterService::class)->reconcileCommitteeRoster($committee, $term->school_year_id, $term->id);

        $this->assertDatabaseHas('faculty_committee_assignments', [
            'user_id' => $chair->id, 'committee_id' => $committee->id, 'role' => 'chairperson',
        ]);
    }
}
