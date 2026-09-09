<?php

namespace Tests\Feature\FacultyLoading;

use App\Models\Committee;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\FacultyCommitteeAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\IPCRRatingPeriod;
use App\Models\IPCRV2\IpcrV2Record;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\IPCRV2\IpcrV2WorkflowService;
use App\Services\PerformanceManagement\CommitteeIpcrRatingService;
use App\Services\PerformanceManagement\CommitteeIpcrSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommitteeAssignmentControllerRateTest extends TestCase
{
    use RefreshDatabase;

    private function withViewOwn(User $user): User
    {
        $role = Role::create(['name' => 'TestRole_' . uniqid()]);
        $perm = Permission::firstOrCreate(['name' => 'faculty_loading.view_own'], ['module' => 'FacultyLoading', 'description' => 'faculty_loading.view_own']);
        $role->permissions()->attach($perm->id);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function setUpChairAndMember(): array
    {
        $sy = SchoolYear::create(['name' => '2026-2027', 'is_current' => true, 'start_date' => '2026-06-01', 'end_date' => '2027-03-31']);
        $term = AcademicTerm::create(['school_year_id' => $sy->id, 'name' => 'Full Term', 'term_type' => 'full_term', 'is_current' => true]);
        $chair = $this->withViewOwn(User::factory()->create());
        $member = User::factory()->create();
        $committee = Committee::create(['name' => 'Grievance Committee']);
        FacultyCommitteeAssignment::create([
            'user_id' => $chair->id, 'school_year_id' => $term->school_year_id, 'academic_term_id' => $term->id,
            'committee_id' => $committee->id, 'committee_name' => $committee->name, 'role' => 'chairperson', 'status' => 'active',
        ]);
        $memberAssignment = FacultyCommitteeAssignment::create([
            'user_id' => $member->id, 'school_year_id' => $term->school_year_id, 'academic_term_id' => $term->id,
            'committee_id' => $committee->id, 'committee_name' => $committee->name, 'role' => 'member', 'status' => 'active',
        ]);
        $period = IPCRRatingPeriod::create(['label' => 'FY2026-1', 'year' => 2026, 'semester' => 1, 'status' => 'open', 'is_current' => true]);
        $record = IpcrV2Record::create(['user_id' => $member->id, 'rating_period_id' => $period->id, 'status' => IpcrV2WorkflowService::STATUS_FOR_RATING]);
        (new CommitteeIpcrSyncService())->syncForUser($member);
        $item = (new CommitteeIpcrRatingService())->resolveSupportItems($memberAssignment)->first();

        return [$chair, $memberAssignment, $item];
    }

    public function test_chairperson_rating_writes_the_ipcr_v2_support_item(): void
    {
        [$chair, $assignment, $item] = $this->setUpChairAndMember();

        $this->actingAs($chair)->post(route('faculty-loading.committee-assignments.rate', $assignment->id), [
            'support_item_id' => $item->id,
            'quality_rating' => 5, 'efficiency_rating' => 5, 'timeliness_rating' => 4,
        ])->assertRedirect();

        $this->assertDatabaseHas('ipcr_v2_support_items', [
            'id' => $item->id, 'quality_rating' => 5, 'efficiency_rating' => 5, 'timeliness_rating' => 4,
        ]);
    }

    public function test_a_non_chair_non_admin_member_cannot_rate(): void
    {
        [, $assignment, $item] = $this->setUpChairAndMember();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)->post(route('faculty-loading.committee-assignments.rate', $assignment->id), [
            'support_item_id' => $item->id,
            'quality_rating' => 5, 'efficiency_rating' => 5, 'timeliness_rating' => 4,
        ])->assertForbidden();
    }

    public function test_a_support_item_id_belonging_to_a_different_assignment_is_rejected(): void
    {
        [$chair, $assignment] = $this->setUpChairAndMember();
        $otherItem = IpcrV2Record::first()->supportItems()->create(['label' => 'Unrelated manual item']);

        $this->actingAs($chair)->post(route('faculty-loading.committee-assignments.rate', $assignment->id), [
            'support_item_id' => $otherItem->id,
            'quality_rating' => 5, 'efficiency_rating' => 5, 'timeliness_rating' => 4,
        ])->assertNotFound();
    }
}
