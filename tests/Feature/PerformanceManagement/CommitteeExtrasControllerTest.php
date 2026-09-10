<?php

namespace Tests\Feature\PerformanceManagement;

use App\Models\Committee;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\FacultyCommitteeAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\Issuance;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommitteeExtrasControllerTest extends TestCase
{
    use RefreshDatabase;

    private function withViewOwn(User $user): User
    {
        $role = Role::create(['name' => 'TestRole_' . uniqid()]);
        $perm = Permission::firstOrCreate(['name' => 'accomplishments.view'], ['module' => 'Accomplishments', 'description' => 'accomplishments.view']);
        $role->permissions()->attach($perm->id);
        $user->roles()->attach($role->id);

        return $user;
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

    private function currentTerm(): AcademicTerm
    {
        $sy = SchoolYear::create(['name' => '2026-2027', 'is_current' => true, 'start_date' => '2026-06-01', 'end_date' => '2027-03-31']);

        return AcademicTerm::create(['school_year_id' => $sy->id, 'name' => 'Full Term', 'term_type' => 'full_term', 'is_current' => true]);
    }

    public function test_my_committees_lists_only_the_logged_in_users_own_assignments(): void
    {
        $term = $this->currentTerm();
        $me = $this->withViewOwn(User::factory()->create());
        $other = User::factory()->create();
        $committee = Committee::create(['name' => 'Grievance Committee']);

        FacultyCommitteeAssignment::create([
            'user_id' => $me->id, 'school_year_id' => $term->school_year_id, 'academic_term_id' => $term->id,
            'committee_id' => $committee->id, 'committee_name' => $committee->name, 'role' => 'chairperson', 'status' => 'active',
        ]);
        FacultyCommitteeAssignment::create([
            'user_id' => $other->id, 'school_year_id' => $term->school_year_id, 'academic_term_id' => $term->id,
            'committee_id' => $committee->id, 'committee_name' => $committee->name, 'role' => 'member', 'status' => 'active',
        ]);

        $this->actingAs($me)->get(route('pm-committees.my'))->assertInertia(fn ($page) => $page
            ->component('PerformanceManagement/Committees/MyCommittees')
            ->has('assignments', 1)
            ->where('assignments.0.committee_name', 'Grievance Committee')
            ->where('assignments.0.is_chairperson', true)
        );
    }

    public function test_load_conflict_check_flags_when_projected_total_exceeds_threshold(): void
    {
        config(['committees.load_conflict_threshold' => 3.0]);
        $admin = $this->admin();
        $term = $this->currentTerm();
        $member = User::factory()->create();
        $committeeA = Committee::create(['name' => 'Committee A']);
        $committeeB = Committee::create(['name' => 'Committee B']);

        FacultyCommitteeAssignment::create([
            'user_id' => $member->id, 'school_year_id' => $term->school_year_id, 'academic_term_id' => $term->id,
            'committee_id' => $committeeA->id, 'committee_name' => $committeeA->name, 'role' => 'chairperson',
            'load_units' => 2.0, 'status' => 'active',
        ]);

        $response = $this->actingAs($admin)->get(route('pm-committees.load-conflict-check', [
            'user_id' => $member->id, 'term_id' => $term->id, 'additional_units' => 2.0,
        ]));

        $response->assertOk();
        $response->assertJson(['exceeds' => true, 'current_total' => 2.0, 'projected_total' => 4.0]);
    }

    public function test_load_conflict_check_does_not_flag_when_within_threshold(): void
    {
        config(['committees.load_conflict_threshold' => 3.0]);
        $admin = $this->admin();
        $term = $this->currentTerm();
        $member = User::factory()->create();

        $response = $this->actingAs($admin)->get(route('pm-committees.load-conflict-check', [
            'user_id' => $member->id, 'term_id' => $term->id, 'additional_units' => 1.0,
        ]));

        $response->assertOk();
        $response->assertJson(['exceeds' => false, 'projected_total' => 1.0]);
    }

    public function test_search_issuances_returns_matching_issuances_by_title(): void
    {
        $admin = $this->admin();
        Issuance::create([
            'type' => 'special_order', 'control_number' => 'SO-2026-014', 'series_no' => 14, 'year' => 2026,
            'title' => 'Formation of the Grievance Committee',
            'content' => 'Body', 'recipient_type' => 'all', 'status' => 'released', 'created_by' => $admin->id,
        ]);
        Issuance::create([
            'type' => 'special_order', 'control_number' => 'SO-2026-020', 'series_no' => 20, 'year' => 2026,
            'title' => 'Unrelated Order',
            'content' => 'Body', 'recipient_type' => 'all', 'status' => 'released', 'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get(route('pm-committees.search-issuances', ['q' => 'Grievance']));

        $response->assertOk();
        $data = $response->json();
        $this->assertCount(1, $data);
        $this->assertStringContainsString('Grievance', $data[0]['label']);
    }
}
