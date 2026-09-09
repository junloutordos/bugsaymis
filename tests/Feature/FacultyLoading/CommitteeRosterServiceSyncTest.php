<?php

namespace Tests\Feature\FacultyLoading;

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
