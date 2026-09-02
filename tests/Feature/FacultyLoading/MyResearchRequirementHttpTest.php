<?php

namespace Tests\Feature\FacultyLoading;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\ResearchAdvisory;
use App\Models\FacultyLoading\ResearchGroup;
use App\Models\FacultyLoading\ResearchRequirement;
use App\Models\FacultyLoading\ResearchRequirementAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MyResearchRequirementHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('s3');
    }

    private function adviser(): User
    {
        $role = Role::create(['name' => 'TestAdviser_'.uniqid()]);
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

    public function test_adviser_only_sees_requirements_for_their_own_groups(): void
    {
        $term = $this->makeTerm();
        $group = ResearchGroup::create(['academic_term_id' => $term->id, 'grade_level' => 10, 'title' => 'My Group', 'research_type' => 'thesis']);
        $otherGroup = ResearchGroup::create(['academic_term_id' => $term->id, 'grade_level' => 10, 'title' => 'Other Group', 'research_type' => 'thesis']);

        $mine = $this->adviser();
        ResearchAdvisory::create(['user_id' => $mine->id, 'school_year_id' => $term->school_year_id, 'academic_term_id' => $term->id, 'research_title' => 'My Group', 'grade_level' => 10, 'advisory_role' => 'lead', 'research_type' => 'thesis', 'load_units' => 1.0, 'status' => 'active', 'research_group_id' => $group->id]);
        ResearchAdvisory::create(['user_id' => User::factory()->create()->id, 'school_year_id' => $term->school_year_id, 'academic_term_id' => $term->id, 'research_title' => 'Other Group', 'grade_level' => 10, 'advisory_role' => 'lead', 'research_type' => 'thesis', 'load_units' => 1.0, 'status' => 'active', 'research_group_id' => $otherGroup->id]);

        $requirement = ResearchRequirement::create(['created_by' => User::factory()->create()->id, 'academic_term_id' => $term->id, 'title' => 'Chapter 1', 'due_at' => now()->addDays(14), 'status' => 'active']);
        ResearchRequirementAssignment::create(['research_requirement_id' => $requirement->id, 'research_group_id' => $group->id]);
        ResearchRequirementAssignment::create(['research_requirement_id' => $requirement->id, 'research_group_id' => $otherGroup->id]);

        $response = $this->actingAs($mine)->get(route('faculty-loading.my-research-requirements.index'));
        $response->assertOk();
        $assignments = $response->viewData('page')['props']['assignments'];
        $this->assertCount(1, $assignments);
        $this->assertSame('My Group', $assignments[0]['research_group']['title']);
    }

    public function test_co_adviser_also_sees_the_shared_requirement(): void
    {
        $term = $this->makeTerm();
        $group = ResearchGroup::create(['academic_term_id' => $term->id, 'grade_level' => 10, 'title' => 'Shared Group', 'research_type' => 'thesis']);
        $lead = $this->adviser();
        $co   = $this->adviser();
        ResearchAdvisory::create(['user_id' => $lead->id, 'school_year_id' => $term->school_year_id, 'academic_term_id' => $term->id, 'research_title' => 'Shared Group', 'grade_level' => 10, 'advisory_role' => 'lead', 'research_type' => 'thesis', 'load_units' => 1.0, 'status' => 'active', 'research_group_id' => $group->id]);
        ResearchAdvisory::create(['user_id' => $co->id, 'school_year_id' => $term->school_year_id, 'academic_term_id' => $term->id, 'research_title' => 'Shared Group', 'grade_level' => 10, 'advisory_role' => 'co_adviser', 'research_type' => 'thesis', 'load_units' => 0.5, 'status' => 'active', 'research_group_id' => $group->id]);

        $requirement = ResearchRequirement::create(['created_by' => User::factory()->create()->id, 'academic_term_id' => $term->id, 'title' => 'Chapter 1', 'due_at' => now()->addDays(14), 'status' => 'active']);
        ResearchRequirementAssignment::create(['research_requirement_id' => $requirement->id, 'research_group_id' => $group->id]);

        $this->actingAs($co)->get(route('faculty-loading.my-research-requirements.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('assignments', 1));
    }

    private function pdfDataUri(string $content = 'fake pdf content'): string
    {
        return 'data:application/pdf;base64,' . base64_encode($content);
    }

    private function assignmentFor(User $adviser, AcademicTerm $term, array $requirementOverrides = []): ResearchRequirementAssignment
    {
        $group = ResearchGroup::create(['academic_term_id' => $term->id, 'grade_level' => 10, 'title' => 'X'.uniqid(), 'research_type' => 'thesis']);
        ResearchAdvisory::create(['user_id' => $adviser->id, 'school_year_id' => $term->school_year_id, 'academic_term_id' => $term->id, 'research_title' => $group->title, 'grade_level' => 10, 'advisory_role' => 'lead', 'research_type' => 'thesis', 'load_units' => 1.0, 'status' => 'active', 'research_group_id' => $group->id]);
        $requirement = ResearchRequirement::create(array_merge([
            'created_by' => User::factory()->create()->id, 'academic_term_id' => $term->id, 'title' => 'Chapter 1',
            'due_at' => now()->addDays(14), 'status' => 'active', 'max_files' => 5,
        ], $requirementOverrides));
        return ResearchRequirementAssignment::create(['research_requirement_id' => $requirement->id, 'research_group_id' => $group->id]);
    }

    public function test_adviser_can_submit_files_and_notes(): void
    {
        $term = $this->makeTerm();
        $adviser = $this->adviser();
        $assignment = $this->assignmentFor($adviser, $term);

        $response = $this->actingAs($adviser)->post(route('faculty-loading.my-research-requirements.submit', $assignment->id), [
            'notes' => 'Here is our draft.',
            'files' => [['data' => $this->pdfDataUri(), 'name' => 'chapter1.pdf']],
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame('submitted', $assignment->fresh()->status);
        $this->assertDatabaseHas('research_requirement_submissions', ['research_requirement_assignment_id' => $assignment->id, 'notes' => 'Here is our draft.']);
        $this->assertSame(1, \App\Models\FacultyLoading\ResearchRequirementSubmissionFile::count());
    }

    public function test_non_member_cannot_submit_for_a_group_they_do_not_belong_to(): void
    {
        $term = $this->makeTerm();
        $owner  = $this->adviser();
        $intruder = $this->adviser();
        $assignment = $this->assignmentFor($owner, $term);

        $this->actingAs($intruder)->post(route('faculty-loading.my-research-requirements.submit', $assignment->id), [
            'files' => [['data' => $this->pdfDataUri(), 'name' => 'x.pdf']],
        ])->assertForbidden();
    }

    public function test_late_submission_blocked_when_not_allowed(): void
    {
        $term = $this->makeTerm();
        $adviser = $this->adviser();
        $assignment = $this->assignmentFor($adviser, $term, ['due_at' => now()->subDay(), 'allow_late_submission' => false]);

        $this->actingAs($adviser)->post(route('faculty-loading.my-research-requirements.submit', $assignment->id), [
            'files' => [['data' => $this->pdfDataUri(), 'name' => 'x.pdf']],
        ])->assertSessionHasErrors('due_at');

        $this->assertSame('pending', $assignment->fresh()->status);
    }

    public function test_returned_assignment_can_be_resubmitted_past_deadline(): void
    {
        $term = $this->makeTerm();
        $adviser = $this->adviser();
        $assignment = $this->assignmentFor($adviser, $term, ['due_at' => now()->subDay(), 'allow_late_submission' => false]);
        $assignment->update(['status' => 'returned']);

        $this->actingAs($adviser)->post(route('faculty-loading.my-research-requirements.submit', $assignment->id), [
            'files' => [['data' => $this->pdfDataUri(), 'name' => 'x.pdf']],
        ])->assertSessionHasNoErrors();

        $this->assertSame('submitted', $assignment->fresh()->status);
    }

    public function test_exceeding_max_files_is_rejected(): void
    {
        $term = $this->makeTerm();
        $adviser = $this->adviser();
        $assignment = $this->assignmentFor($adviser, $term, ['max_files' => 1]);

        $this->actingAs($adviser)->post(route('faculty-loading.my-research-requirements.submit', $assignment->id), [
            'files' => [
                ['data' => $this->pdfDataUri('a'), 'name' => 'a.pdf'],
                ['data' => $this->pdfDataUri('b'), 'name' => 'b.pdf'],
            ],
        ])->assertSessionHasErrors('files');
    }

    public function test_group_member_can_download_their_own_submitted_file(): void
    {
        \Illuminate\Support\Facades\Storage::fake('s3');
        $term = $this->makeTerm();
        $adviser = $this->adviser();
        $assignment = $this->assignmentFor($adviser, $term);

        $this->actingAs($adviser)->post(route('faculty-loading.my-research-requirements.submit', $assignment->id), [
            'files' => [['data' => $this->pdfDataUri(), 'name' => 'chapter1.pdf']],
        ]);
        $file = \App\Models\FacultyLoading\ResearchRequirementSubmissionFile::first();
        $fileId = (new \App\Services\FacultyLoading\ResearchSubmissionFileService())->encodeKey($file->s3_key);

        $this->actingAs($adviser)->get(route('faculty-loading.research-requirements.files.show', $fileId))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_unrelated_faculty_cannot_download_someone_elses_submitted_file(): void
    {
        \Illuminate\Support\Facades\Storage::fake('s3');
        $term = $this->makeTerm();
        $owner = $this->adviser();
        $stranger = $this->adviser();
        $assignment = $this->assignmentFor($owner, $term);

        $this->actingAs($owner)->post(route('faculty-loading.my-research-requirements.submit', $assignment->id), [
            'files' => [['data' => $this->pdfDataUri(), 'name' => 'chapter1.pdf']],
        ]);
        $file = \App\Models\FacultyLoading\ResearchRequirementSubmissionFile::first();
        $fileId = (new \App\Services\FacultyLoading\ResearchSubmissionFileService())->encodeKey($file->s3_key);

        $this->actingAs($stranger)->get(route('faculty-loading.research-requirements.files.show', $fileId))
            ->assertForbidden();
    }
}
