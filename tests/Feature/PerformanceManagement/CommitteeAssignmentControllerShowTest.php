<?php

namespace Tests\Feature\PerformanceManagement;

use App\Models\Committee;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\FacultyCommitteeAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\IPCRRatingPeriod;
use App\Models\IPCRV2\IpcrV2Record;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\PerformanceManagement\CommitteeIpcrSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommitteeAssignmentControllerShowTest extends TestCase
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

    public function test_show_lists_each_members_own_resolved_support_item(): void
    {
        $sy = SchoolYear::create(['name' => '2026-2027', 'is_current' => true, 'start_date' => '2026-06-01', 'end_date' => '2027-03-31']);
        $term = AcademicTerm::create(['school_year_id' => $sy->id, 'name' => 'Full Term', 'term_type' => 'full_term', 'is_current' => true]);
        $admin = $this->admin();
        $member = User::factory()->create();
        $committee = Committee::create(['name' => 'Grievance Committee']);
        FacultyCommitteeAssignment::create([
            'user_id' => $member->id, 'school_year_id' => $term->school_year_id, 'academic_term_id' => $term->id,
            'committee_id' => $committee->id, 'committee_name' => $committee->name, 'role' => 'member', 'status' => 'active',
        ]);
        $period = IPCRRatingPeriod::create(['label' => 'FY2026-1', 'year' => 2026, 'semester' => 1, 'status' => 'open', 'is_current' => true]);
        IpcrV2Record::create(['user_id' => $member->id, 'rating_period_id' => $period->id]);
        (new CommitteeIpcrSyncService())->syncForUser($member);

        $response = $this->actingAs($admin)->get(route('pm-committees.show', $committee->id));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('PerformanceManagement/Committees/Show')
            ->has('members', 1)
            ->where('members.0.items.0.label', 'Grievance Committee')
        );
    }

    public function test_show_lists_a_member_with_no_materialized_item_yet_with_an_empty_items_array(): void
    {
        $sy = SchoolYear::create(['name' => '2026-2027', 'is_current' => true, 'start_date' => '2026-06-01', 'end_date' => '2027-03-31']);
        $term = AcademicTerm::create(['school_year_id' => $sy->id, 'name' => 'Full Term', 'term_type' => 'full_term', 'is_current' => true]);
        $admin = $this->admin();
        $member = User::factory()->create();
        $committee = Committee::create(['name' => 'Grievance Committee']);
        FacultyCommitteeAssignment::create([
            'user_id' => $member->id, 'school_year_id' => $term->school_year_id, 'academic_term_id' => $term->id,
            'committee_id' => $committee->id, 'committee_name' => $committee->name, 'role' => 'member', 'status' => 'active',
        ]);
        // No IpcrV2Record at all for this member — nothing to materialize into.

        $response = $this->actingAs($admin)->get(route('pm-committees.show', $committee->id));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('members', 1)
            ->has('members.0.items', 0)
        );
    }
}
