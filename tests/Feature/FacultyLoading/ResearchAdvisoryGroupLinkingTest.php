<?php

namespace Tests\Feature\FacultyLoading;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\ResearchAdvisory;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResearchAdvisoryGroupLinkingTest extends TestCase
{
    use RefreshDatabase;

    private function coordinator(): User
    {
        $role = Role::create(['name' => 'TestCoordinator_'.uniqid()]);
        $perm = Permission::firstOrCreate(['name' => 'faculty_loading.research_advisories'], ['module' => 'FacultyLoading', 'description' => 'x']);
        $role->permissions()->attach($perm->id);
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->roles()->attach($role->id);
        return $user;
    }

    private function makeTerm(): AcademicTerm
    {
        $sy = SchoolYear::create(['name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30', 'is_current' => true, 'status' => 'active']);
        return AcademicTerm::create(['school_year_id' => $sy->id, 'name' => '1st Semester', 'term_type' => '1st_semester', 'start_date' => '2025-08-01', 'end_date' => '2025-12-31', 'is_current' => true]);
    }

    public function test_creating_two_co_advised_groups_with_same_title_shares_one_research_group(): void
    {
        $coordinator = $this->coordinator();
        $term = $this->makeTerm();
        $lead = User::factory()->create();
        $co   = User::factory()->create();

        $this->actingAs($coordinator)->post(route('faculty-loading.research-advisories.store'), [
            'user_id' => $lead->id, 'academic_term_id' => $term->id, 'research_title' => 'Shared Title',
            'grade_level' => 10, 'advisory_role' => 'lead', 'research_type' => 'thesis', 'load_units' => 1.0,
        ])->assertSessionHasNoErrors();

        $this->actingAs($coordinator)->post(route('faculty-loading.research-advisories.store'), [
            'user_id' => $co->id, 'academic_term_id' => $term->id, 'research_title' => 'Shared Title',
            'grade_level' => 10, 'advisory_role' => 'co_adviser', 'research_type' => 'thesis', 'load_units' => 0.5,
        ])->assertSessionHasNoErrors();

        $leadRow = ResearchAdvisory::where('user_id', $lead->id)->first();
        $coRow   = ResearchAdvisory::where('user_id', $co->id)->first();

        $this->assertNotNull($leadRow->research_group_id);
        $this->assertSame($leadRow->research_group_id, $coRow->research_group_id);
    }

    public function test_renaming_title_on_update_re_resolves_group(): void
    {
        $coordinator = $this->coordinator();
        $term = $this->makeTerm();
        $lead = User::factory()->create();

        $this->actingAs($coordinator)->post(route('faculty-loading.research-advisories.store'), [
            'user_id' => $lead->id, 'academic_term_id' => $term->id, 'research_title' => 'Original Title',
            'grade_level' => 10, 'advisory_role' => 'lead', 'research_type' => 'thesis', 'load_units' => 1.0,
        ]);
        $advisory = ResearchAdvisory::where('user_id', $lead->id)->first();
        $originalGroupId = $advisory->research_group_id;

        $this->actingAs($coordinator)->put(route('faculty-loading.research-advisories.update', $advisory->id), [
            'research_title' => 'Renamed Title', 'grade_level' => 10, 'advisory_role' => 'lead',
            'research_type' => 'thesis', 'load_units' => 1.0, 'status' => 'active',
        ])->assertSessionHasNoErrors();

        $advisory->refresh();
        $this->assertNotSame($originalGroupId, $advisory->research_group_id);
        $this->assertSame('Renamed Title', $advisory->researchGroup->title);
    }
}
