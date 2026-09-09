<?php

namespace Tests\Feature\PerformanceManagement;

use App\Models\EmployeeFunction;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\FacultyCommitteeAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\IPCRRatingPeriod;
use App\Models\IPCRV2\IpcrV2Record;
use App\Models\Committee;
use App\Models\User;
use App\Services\PerformanceManagement\CommitteeIpcrSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommitteeIpcrSyncServiceTest extends TestCase
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

    public function test_sync_for_user_creates_the_support_function_row(): void
    {
        $term = $this->currentTerm();
        $member = User::factory()->create();
        $committee = Committee::create(['name' => 'Grievance Committee']);
        FacultyCommitteeAssignment::create([
            'user_id' => $member->id, 'school_year_id' => $term->school_year_id,
            'academic_term_id' => $term->id, 'committee_id' => $committee->id,
            'committee_name' => $committee->name, 'role' => 'member', 'status' => 'active',
        ]);

        (new CommitteeIpcrSyncService())->syncForUser($member);

        $this->assertDatabaseHas('employee_functions', [
            'user_id' => $member->id,
            'function_type' => EmployeeFunction::TYPE_SUPPORT,
            'label' => 'Grievance Committee',
        ]);
    }

    public function test_sync_for_user_materializes_into_an_existing_current_period_record(): void
    {
        $term = $this->currentTerm();
        $member = User::factory()->create();
        $committee = Committee::create(['name' => 'Grievance Committee']);
        FacultyCommitteeAssignment::create([
            'user_id' => $member->id, 'school_year_id' => $term->school_year_id,
            'academic_term_id' => $term->id, 'committee_id' => $committee->id,
            'committee_name' => $committee->name, 'role' => 'member', 'status' => 'active',
        ]);
        $period = IPCRRatingPeriod::create(['label' => 'FY2026-1', 'year' => 2026, 'semester' => 1, 'status' => 'open', 'is_current' => true]);
        $record = IpcrV2Record::create(['user_id' => $member->id, 'rating_period_id' => $period->id]);

        (new CommitteeIpcrSyncService())->syncForUser($member);

        $this->assertDatabaseHas('ipcr_v2_support_items', [
            'ipcr_v2_id' => $record->id,
            'label' => 'Grievance Committee',
        ]);
    }

    public function test_sync_for_user_does_not_fabricate_a_record_when_none_exists(): void
    {
        $term = $this->currentTerm();
        $member = User::factory()->create();
        $committee = Committee::create(['name' => 'Grievance Committee']);
        FacultyCommitteeAssignment::create([
            'user_id' => $member->id, 'school_year_id' => $term->school_year_id,
            'academic_term_id' => $term->id, 'committee_id' => $committee->id,
            'committee_name' => $committee->name, 'role' => 'member', 'status' => 'active',
        ]);

        (new CommitteeIpcrSyncService())->syncForUser($member);

        $this->assertDatabaseCount('ipcr_v2_records', 0);
    }

    public function test_sync_for_committee_syncs_every_active_member(): void
    {
        $term = $this->currentTerm();
        $memberA = User::factory()->create();
        $memberB = User::factory()->create();
        $committee = Committee::create(['name' => 'Sports Committee']);
        FacultyCommitteeAssignment::create([
            'user_id' => $memberA->id, 'school_year_id' => $term->school_year_id,
            'academic_term_id' => $term->id, 'committee_id' => $committee->id,
            'committee_name' => $committee->name, 'role' => 'member', 'status' => 'active',
        ]);
        FacultyCommitteeAssignment::create([
            'user_id' => $memberB->id, 'school_year_id' => $term->school_year_id,
            'academic_term_id' => $term->id, 'committee_id' => $committee->id,
            'committee_name' => $committee->name, 'role' => 'chairperson', 'status' => 'active',
        ]);
        FacultyCommitteeAssignment::create([
            'user_id' => User::factory()->create()->id, 'school_year_id' => $term->school_year_id,
            'academic_term_id' => $term->id, 'committee_id' => $committee->id,
            'committee_name' => $committee->name, 'role' => 'member', 'status' => 'inactive',
        ]);

        (new CommitteeIpcrSyncService())->syncForCommittee($committee->id);

        $this->assertDatabaseHas('employee_functions', ['user_id' => $memberA->id, 'label' => 'Sports Committee']);
        $this->assertDatabaseHas('employee_functions', ['user_id' => $memberB->id, 'label' => 'Sports Committee']);
        $this->assertDatabaseCount('employee_functions', 2); // inactive assignment's user is skipped
    }
}
