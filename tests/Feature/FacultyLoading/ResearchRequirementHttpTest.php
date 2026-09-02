<?php

namespace Tests\Feature\FacultyLoading;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\ResearchAdvisory;
use App\Models\FacultyLoading\ResearchGroup;
use App\Models\FacultyLoading\ResearchRequirement;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResearchRequirementHttpTest extends TestCase
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

    private function faculty(): User
    {
        $role = Role::create(['name' => 'TestFaculty_'.uniqid()]);
        $perm = Permission::firstOrCreate(['name' => 'faculty_loading.view_own'], ['module' => 'FacultyLoading', 'description' => 'x']);
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

    private function makeActiveGroup(AcademicTerm $term, int $gradeLevel, string $researchType, string $title): ResearchGroup
    {
        $group = ResearchGroup::create(['academic_term_id' => $term->id, 'grade_level' => $gradeLevel, 'title' => $title, 'research_type' => $researchType]);
        ResearchAdvisory::create([
            'user_id' => User::factory()->create()->id, 'school_year_id' => $term->school_year_id, 'academic_term_id' => $term->id,
            'research_title' => $title, 'grade_level' => $gradeLevel, 'advisory_role' => 'lead', 'research_type' => $researchType,
            'load_units' => 1.0, 'status' => 'active', 'research_group_id' => $group->id,
        ]);
        return $group;
    }

    public function test_coordinator_can_create_requirement_and_it_fans_out(): void
    {
        $term = $this->makeTerm();
        $this->makeActiveGroup($term, 10, 'thesis', 'Group A');
        $this->makeActiveGroup($term, 11, 'thesis', 'Group B');

        $response = $this->actingAs($this->coordinator())->post(route('faculty-loading.research-requirements.store'), [
            'academic_term_id' => $term->id,
            'title'            => 'Chapter 1 Draft',
            'description'      => 'Submit the Introduction chapter.',
            'research_type'    => 'thesis',
            'grade_levels'     => [10],
            'due_at'           => now()->addDays(14)->toDateTimeString(),
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('research_requirements', ['title' => 'Chapter 1 Draft']);
        $requirement = ResearchRequirement::first();
        $this->assertSame(1, $requirement->assignments()->count());
    }

    public function test_faculty_without_permission_cannot_create_requirement(): void
    {
        $term = $this->makeTerm();

        $this->actingAs($this->faculty())->post(route('faculty-loading.research-requirements.store'), [
            'academic_term_id' => $term->id, 'title' => 'X', 'due_at' => now()->addDays(1)->toDateTimeString(),
        ])->assertForbidden();
    }

    public function test_index_reports_compliance_stats_per_requirement(): void
    {
        $term = $this->makeTerm();
        $this->makeActiveGroup($term, 10, 'thesis', 'Group A');
        $this->makeActiveGroup($term, 10, 'thesis', 'Group B');

        $coordinator = $this->coordinator();
        $this->actingAs($coordinator)->post(route('faculty-loading.research-requirements.store'), [
            'academic_term_id' => $term->id, 'title' => 'Chapter 1', 'grade_levels' => [10], 'research_type' => 'thesis',
            'due_at' => now()->addDays(14)->toDateTimeString(),
        ]);

        $response = $this->actingAs($coordinator)->get(route('faculty-loading.research-requirements.index'));
        $response->assertOk();
        $requirements = $response->viewData('page')['props']['requirements'];
        $this->assertSame(2, $requirements[0]['stats']['total']);
        $this->assertSame(0, $requirements[0]['stats']['compliance_pct']);
    }

    public function test_show_returns_assignment_grid_with_group_and_advisers(): void
    {
        $term = $this->makeTerm();
        $group = $this->makeActiveGroup($term, 10, 'thesis', 'Group A');
        $coordinator = $this->coordinator();

        $this->actingAs($coordinator)->post(route('faculty-loading.research-requirements.store'), [
            'academic_term_id' => $term->id, 'title' => 'Chapter 1', 'grade_levels' => [10], 'research_type' => 'thesis',
            'due_at' => now()->addDays(14)->toDateTimeString(),
        ]);
        $requirement = ResearchRequirement::first();

        $response = $this->actingAs($coordinator)->get(route('faculty-loading.research-requirements.show', $requirement->id));
        $response->assertOk();
        $assignments = $response->viewData('page')['props']['assignments'];
        $this->assertCount(1, $assignments);
        $this->assertSame($group->id, $assignments[0]['research_group']['id']);
        $this->assertCount(1, $assignments[0]['research_group']['advisers']);
    }

    public function test_update_edits_metadata_without_re_fanning_out(): void
    {
        $term = $this->makeTerm();
        $this->makeActiveGroup($term, 10, 'thesis', 'Group A');
        $coordinator = $this->coordinator();

        $this->actingAs($coordinator)->post(route('faculty-loading.research-requirements.store'), [
            'academic_term_id' => $term->id, 'title' => 'Chapter 1', 'grade_levels' => [10], 'research_type' => 'thesis',
            'due_at' => now()->addDays(14)->toDateTimeString(),
        ]);
        $requirement = ResearchRequirement::first();

        $this->actingAs($coordinator)->put(route('faculty-loading.research-requirements.update', $requirement->id), [
            'title' => 'Chapter 1 (Revised)', 'due_at' => now()->addDays(21)->toDateTimeString(), 'allow_late_submission' => false,
        ])->assertSessionHasNoErrors();

        $requirement->refresh();
        $this->assertSame('Chapter 1 (Revised)', $requirement->title);
        $this->assertFalse($requirement->allow_late_submission);
        $this->assertSame(1, $requirement->assignments()->count());
    }

    public function test_archive_sets_status_archived(): void
    {
        $term = $this->makeTerm();
        $coordinator = $this->coordinator();
        $this->actingAs($coordinator)->post(route('faculty-loading.research-requirements.store'), [
            'academic_term_id' => $term->id, 'title' => 'Chapter 1', 'due_at' => now()->addDays(14)->toDateTimeString(),
        ]);
        $requirement = ResearchRequirement::first();

        $this->actingAs($coordinator)->delete(route('faculty-loading.research-requirements.archive', $requirement->id))
            ->assertSessionHasNoErrors();

        $this->assertSame('archived', $requirement->fresh()->status);
    }

    public function test_sync_picks_up_a_group_created_after_the_requirement(): void
    {
        $term = $this->makeTerm();
        $this->makeActiveGroup($term, 10, 'thesis', 'Group A');
        $coordinator = $this->coordinator();

        $this->actingAs($coordinator)->post(route('faculty-loading.research-requirements.store'), [
            'academic_term_id' => $term->id, 'title' => 'Chapter 1', 'grade_levels' => [10], 'research_type' => 'thesis',
            'due_at' => now()->addDays(14)->toDateTimeString(),
        ]);
        $requirement = ResearchRequirement::first();
        $this->makeActiveGroup($term, 10, 'thesis', 'Group B (new)');

        $this->actingAs($coordinator)->post(route('faculty-loading.research-requirements.sync', $requirement->id))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, $requirement->assignments()->count());
    }

    public function test_can_add_an_out_of_scope_group_as_an_exception(): void
    {
        $term = $this->makeTerm();
        $coordinator = $this->coordinator();
        $outOfScope = $this->makeActiveGroup($term, 12, 'feasibility', 'Exception Group'); // grade 12 + feasibility matches neither the grade_levels:[10] nor research_type:thesis scope below

        $this->actingAs($coordinator)->post(route('faculty-loading.research-requirements.store'), [
            'academic_term_id' => $term->id, 'title' => 'Chapter 1', 'grade_levels' => [10], 'research_type' => 'thesis',
            'due_at' => now()->addDays(14)->toDateTimeString(),
        ]);
        $requirement = ResearchRequirement::first();
        $this->assertSame(0, $requirement->assignments()->count());

        $this->actingAs($coordinator)->post(route('faculty-loading.research-requirements.assignments.store', $requirement->id), [
            'research_group_id' => $outOfScope->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, $requirement->assignments()->count());
    }

    public function test_can_toggle_exclude_on_an_assignment(): void
    {
        $term = $this->makeTerm();
        $this->makeActiveGroup($term, 10, 'thesis', 'Group A');
        $coordinator = $this->coordinator();

        $this->actingAs($coordinator)->post(route('faculty-loading.research-requirements.store'), [
            'academic_term_id' => $term->id, 'title' => 'Chapter 1', 'grade_levels' => [10], 'research_type' => 'thesis',
            'due_at' => now()->addDays(14)->toDateTimeString(),
        ]);
        $assignment = ResearchRequirement::first()->assignments()->first();

        $this->actingAs($coordinator)->patch(route('faculty-loading.research-requirements.assignments.toggle-exclude', $assignment->id))
            ->assertSessionHasNoErrors();
        $this->assertTrue($assignment->fresh()->excluded);

        $this->actingAs($coordinator)->patch(route('faculty-loading.research-requirements.assignments.toggle-exclude', $assignment->id))
            ->assertSessionHasNoErrors();
        $this->assertFalse($assignment->fresh()->excluded);
    }

    public function test_show_includes_the_latest_submission_for_an_assignment(): void
    {
        $term = $this->makeTerm();
        $this->makeActiveGroup($term, 10, 'thesis', 'Group A');
        $coordinator = $this->coordinator();
        $adviser = User::factory()->create();

        $this->actingAs($coordinator)->post(route('faculty-loading.research-requirements.store'), [
            'academic_term_id' => $term->id, 'title' => 'Chapter 1', 'grade_levels' => [10], 'research_type' => 'thesis',
            'due_at' => now()->addDays(14)->toDateTimeString(),
        ]);
        $assignment = ResearchRequirement::first()->assignments()->first();
        \App\Models\FacultyLoading\ResearchRequirementSubmission::create([
            'research_requirement_assignment_id' => $assignment->id, 'submitted_by' => $adviser->id,
            'notes' => 'Draft attached.', 'submitted_at' => now(), 'is_late' => false,
        ]);

        $response = $this->actingAs($coordinator)->get(route('faculty-loading.research-requirements.show', ResearchRequirement::first()->id));
        $assignments = $response->viewData('page')['props']['assignments'];

        $this->assertNotNull($assignments[0]['latest_submission']);
        $this->assertSame('Draft attached.', $assignments[0]['latest_submission']['notes']);
        $this->assertSame($adviser->name, $assignments[0]['latest_submission']['submitted_by']);
    }

    public function test_coordinator_can_accept_a_submission(): void
    {
        $term = $this->makeTerm();
        $group = $this->makeActiveGroup($term, 10, 'thesis', 'Group A');
        $adviser = User::factory()->create();
        $coordinator = $this->coordinator();

        $this->actingAs($coordinator)->post(route('faculty-loading.research-requirements.store'), [
            'academic_term_id' => $term->id, 'title' => 'Chapter 1', 'grade_levels' => [10], 'research_type' => 'thesis',
            'due_at' => now()->addDays(14)->toDateTimeString(),
        ]);
        $assignment = ResearchRequirement::first()->assignments()->first();
        $submission = \App\Models\FacultyLoading\ResearchRequirementSubmission::create([
            'research_requirement_assignment_id' => $assignment->id, 'submitted_by' => $adviser->id, 'submitted_at' => now(), 'is_late' => false,
        ]);
        $assignment->update(['status' => 'submitted']);

        $this->actingAs($coordinator)->post(route('faculty-loading.research-requirements.submissions.review', $submission->id), [
            'decision' => 'accepted',
        ])->assertSessionHasNoErrors();

        $this->assertSame('accepted', $submission->fresh()->review_status);
        $this->assertSame('accepted', $assignment->fresh()->status);
        $this->assertSame($coordinator->id, $submission->fresh()->reviewed_by);
    }

    public function test_return_for_revision_requires_a_comment(): void
    {
        $term = $this->makeTerm();
        $group = $this->makeActiveGroup($term, 10, 'thesis', 'Group A');
        $coordinator = $this->coordinator();

        $this->actingAs($coordinator)->post(route('faculty-loading.research-requirements.store'), [
            'academic_term_id' => $term->id, 'title' => 'Chapter 1', 'grade_levels' => [10], 'research_type' => 'thesis',
            'due_at' => now()->addDays(14)->toDateTimeString(),
        ]);
        $assignment = ResearchRequirement::first()->assignments()->first();
        $submission = \App\Models\FacultyLoading\ResearchRequirementSubmission::create([
            'research_requirement_assignment_id' => $assignment->id, 'submitted_by' => User::factory()->create()->id, 'submitted_at' => now(), 'is_late' => false,
        ]);

        $this->actingAs($coordinator)->post(route('faculty-loading.research-requirements.submissions.review', $submission->id), [
            'decision' => 'returned',
        ])->assertSessionHasErrors('comment');

        $this->actingAs($coordinator)->post(route('faculty-loading.research-requirements.submissions.review', $submission->id), [
            'decision' => 'returned', 'comment' => 'Please expand the literature review.',
        ])->assertSessionHasNoErrors();

        $this->assertSame('returned', $submission->fresh()->review_status);
        $this->assertSame('returned', $assignment->fresh()->status);
    }

    public function test_reviewer_cannot_review_their_own_submission(): void
    {
        $term = $this->makeTerm();
        $group = $this->makeActiveGroup($term, 10, 'thesis', 'Group A');
        $coordinator = $this->coordinator();

        $this->actingAs($coordinator)->post(route('faculty-loading.research-requirements.store'), [
            'academic_term_id' => $term->id, 'title' => 'Chapter 1', 'grade_levels' => [10], 'research_type' => 'thesis',
            'due_at' => now()->addDays(14)->toDateTimeString(),
        ]);
        $assignment = ResearchRequirement::first()->assignments()->first();
        $submission = \App\Models\FacultyLoading\ResearchRequirementSubmission::create([
            'research_requirement_assignment_id' => $assignment->id, 'submitted_by' => $coordinator->id, 'submitted_at' => now(), 'is_late' => false,
        ]);

        $this->actingAs($coordinator)->post(route('faculty-loading.research-requirements.submissions.review', $submission->id), [
            'decision' => 'accepted',
        ])->assertForbidden();
    }
}
