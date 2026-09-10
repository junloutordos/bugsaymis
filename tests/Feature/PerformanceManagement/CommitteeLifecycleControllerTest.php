<?php

namespace Tests\Feature\PerformanceManagement;

use App\Models\Committee;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\FacultyCommitteeAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CommitteeLifecycleControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $role = Role::create(['name' => 'Administrator']);
        $admin = User::factory()->create(['email_verified_at' => now()]);
        $admin->roles()->attach($role->id);

        return $admin;
    }

    private function currentTerm(): AcademicTerm
    {
        $sy = SchoolYear::create(['name' => '2026-2027', 'is_current' => true, 'start_date' => '2026-06-01', 'end_date' => '2027-03-31']);

        return AcademicTerm::create(['school_year_id' => $sy->id, 'name' => 'Full Term', 'term_type' => 'full_term', 'is_current' => true]);
    }

    public function test_revoke_sets_lifecycle_fields_and_deactivates_active_assignments(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $term = $this->currentTerm();
        $member = User::factory()->create();
        $committee = Committee::create(['name' => 'Grievance Committee']);
        $committee->members()->attach($member->id);
        $assignment = FacultyCommitteeAssignment::create([
            'user_id' => $member->id, 'school_year_id' => $term->school_year_id, 'academic_term_id' => $term->id,
            'committee_id' => $committee->id, 'committee_name' => $committee->name, 'role' => 'member', 'status' => 'active',
        ]);

        $this->actingAs($admin)->post(route('pm-committees.catalog.revoke', $committee->id), [
            'revocation_reason' => 'No longer needed',
        ])->assertRedirect();

        $committee->refresh();
        $this->assertNotNull($committee->revoked_at);
        $this->assertSame($admin->id, $committee->revoked_by);
        $this->assertSame('No longer needed', $committee->revocation_reason);

        $this->assertDatabaseHas('faculty_committee_assignments', [
            'id' => $assignment->id, 'status' => 'inactive',
        ]);
    }

    public function test_revoke_requires_an_admin_role(): void
    {
        $committee = Committee::create(['name' => 'Grievance Committee']);
        $outsider = User::factory()->create();

        $this->actingAs($outsider)->post(route('pm-committees.catalog.revoke', $committee->id), [])
            ->assertForbidden();
    }

    public function test_amend_creates_a_new_committee_linked_back_to_the_old_one_and_revokes_the_old_one(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $committee = Committee::create(['name' => 'Grievance Committee']);

        $this->actingAs($admin)->post(route('pm-committees.catalog.amend', $committee->id), [
            'name' => 'Grievance Committee (Revised)',
            'scope_type' => 'perpetual',
        ])->assertRedirect();

        $committee->refresh();
        $this->assertNotNull($committee->revoked_at);

        $newCommittee = Committee::where('name', 'Grievance Committee (Revised)')->first();
        $this->assertNotNull($newCommittee);
        $this->assertSame($committee->id, $newCommittee->amended_from_committee_id);
    }

    public function test_index_excludes_revoked_committees_by_default_and_includes_them_when_show_revoked_is_true(): void
    {
        $admin = $this->admin();
        Committee::create(['name' => 'Active Committee']);
        Committee::create(['name' => 'Revoked Committee', 'revoked_at' => now(), 'revoked_by' => $admin->id]);

        $this->actingAs($admin)->get(route('pm-committees.index'))->assertInertia(function ($page) {
            $names = collect($page->toArray()['props']['catalog'])->pluck('name');
            $this->assertTrue($names->contains('Active Committee'));
            $this->assertFalse($names->contains('Revoked Committee'));

            return $page;
        });

        $this->actingAs($admin)->get(route('pm-committees.index', ['show_revoked' => 1]))->assertInertia(function ($page) {
            $names = collect($page->toArray()['props']['catalog'])->pluck('name');
            $this->assertTrue($names->contains('Revoked Committee'));

            return $page;
        });
    }
}
