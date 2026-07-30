<?php

namespace Tests\Feature\ClassRecord;

use App\Models\ClassRecord\ClassRecord;
use App\Models\ClassRecord\ClassRecordAssessment;
use App\Models\ClassRecord\ClassRecordAssessmentDeletionRequest;
use App\Models\ClassRecord\ClassRecordQuarter;
use App\Models\ClassRecord\ClassRecordScore;
use App\Models\ClassRecord\ClassRecordStudent;
use App\Models\ClassRecord\GradingCategory;
use App\Models\ClassRecord\GradingOption;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\Designation;
use App\Models\FacultyLoading\DesignationCategory;
use App\Models\FacultyLoading\FacultyLoad;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Once an assessment is plotted (dated) it's considered announced to
 * students — it can no longer be silently removed from the Setup tab.
 * Deletion becomes a request that only the ACIDAA (Assistant CID Chief for
 * Academic Affairs) can approve, via the centralized Approval Inbox.
 */
class ClassRecordAssessmentDeletionTest extends TestCase
{
    use RefreshDatabase;

    private SchoolYear $sy;
    private GradingOption $option;
    private GradingCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sy = SchoolYear::create([
            'name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30',
            'is_current' => true, 'status' => 'active',
        ]);
        $this->option = GradingOption::create(['name' => 'Default', 'is_active' => true]);
        $this->category = GradingCategory::create([
            'grading_option_id' => $this->option->id, 'name' => 'Formative', 'code' => 'FA',
            'weight' => 1.0, 'max_assessments' => 10, 'sort_order' => 1,
        ]);
    }

    private function makeSection(): Section
    {
        return Section::create([
            'levelid' => 8, 'sectionname' => 'Emerald', 'syid' => $this->sy->id,
            'school_year_id' => $this->sy->id, 'is_active' => true,
        ]);
    }

    private function makeSubject(): Subject
    {
        return Subject::create([
            'school_year_id' => $this->sy->id, 'code' => 'SUBJ1', 'name' => 'Subject 1',
            'credit_units' => 3, 'lecture_hours' => 3, 'load_units' => 3, 'subject_type' => 'lecture',
            'grade_level' => 8, 'sessions_per_week' => 5, 'minutes_per_session' => 60, 'is_active' => true,
        ]);
    }

    private function makeRecordAndQuarter(User $teacher, Section $section, Subject $subject): ClassRecordQuarter
    {
        $record = ClassRecord::create([
            'subject_id' => $subject->id, 'section_id' => $section->id,
            'grading_option_id' => $this->option->id, 'school_year_id' => $this->sy->id,
            'school_year' => $this->sy->name, 'subject_name' => $subject->name,
            'year_level_section' => "G-{$section->levelid} {$section->sectionname}",
            'teacher_id' => $teacher->id, 'status' => 'draft',
        ]);

        return ClassRecordQuarter::create([
            'class_record_id' => $record->id, 'grading_option_id' => $this->option->id, 'quarter' => 1,
        ]);
    }

    private function makeAssessment(ClassRecordQuarter $quarter, int $number, string $title, ?string $activityDate = null): ClassRecordAssessment
    {
        return ClassRecordAssessment::create([
            'class_record_quarter_id' => $quarter->id, 'grading_category_id' => $this->category->id,
            'assessment_number' => $number, 'title' => $title, 'assessment_type' => 'formative',
            'is_graded' => true, 'is_major' => false, 'activity_date' => $activityDate,
            'plotted_at' => $activityDate ? now() : null, 'max_score' => 20, 'sort_order' => $number,
        ]);
    }

    /** Creates a user holding the ACIDAA designation for the current school year. */
    private function makeAcidaaUser(): User
    {
        $term = AcademicTerm::firstOrCreate(
            ['school_year_id' => $this->sy->id, 'is_current' => true],
            ['name' => '1st Semester', 'term_type' => '1st_semester', 'start_date' => '2025-08-01', 'end_date' => '2025-12-31'],
        );
        $category = DesignationCategory::firstOrCreate(
            ['code' => 'SUPV'],
            ['name' => 'Supervisory', 'is_active' => true],
        );
        $designation = Designation::create([
            'designation_category_id' => $category->id,
            'code' => 'ACIDAA',
            'name' => 'Assistant CID Chief for Academic Affairs',
            'load_units' => 9, 'assignment_type' => 'admin', 'is_active' => true,
        ]);

        $user = User::factory()->create(['email_verified_at' => now()]);
        $facultyLoad = FacultyLoad::create([
            'user_id' => $user->id, 'school_year_id' => $this->sy->id, 'academic_term_id' => $term->id,
        ]);
        LoadAssignment::create([
            'faculty_load_id' => $facultyLoad->id, 'user_id' => $user->id,
            'school_year_id' => $this->sy->id, 'academic_term_id' => $term->id,
            'assignment_type' => 'admin', 'load_units' => 9,
            'description' => $designation->name, 'designation_id' => $designation->id,
        ]);

        return $user;
    }

    private function payloadFor(ClassRecordAssessment $a): array
    {
        return [
            'id' => $a->id, 'grading_category_id' => $a->grading_category_id,
            'assessment_number' => $a->assessment_number, 'title' => $a->title,
            'is_graded' => true, 'activity_date' => $a->activity_date?->toDateString() ?? now()->addMonth()->toDateString(),
            'max_score' => $a->max_score,
        ];
    }

    // ── upsert() — plotted rows can't be silently dropped ──────────────────

    public function test_upsert_still_freely_removes_an_unplotted_assessment(): void
    {
        $teacher = User::factory()->create();
        $quarter = $this->makeRecordAndQuarter($teacher, $this->makeSection(), $this->makeSubject());
        $keep = $this->makeAssessment($quarter, 1, 'Quiz 1', now()->addMonth()->toDateString());
        $remove = $this->makeAssessment($quarter, 2, 'Quiz 2', null); // never plotted

        $this->actingAs($teacher)
            ->postJson(route('class-records.assessments.upsert', ['classRecord' => $quarter->class_record_id, 'q' => 1]), [
                'assessments' => [$this->payloadFor($keep)],
            ])
            ->assertOk();

        $this->assertDatabaseMissing('class_record_assessments', ['id' => $remove->id]);
    }

    public function test_upsert_rejects_dropping_a_plotted_assessment_within_its_scheduled_week(): void
    {
        $teacher = User::factory()->create();
        $quarter = $this->makeRecordAndQuarter($teacher, $this->makeSection(), $this->makeSubject());
        $keep = $this->makeAssessment($quarter, 1, 'Quiz 1', now()->addMonth()->toDateString());
        $plotted = $this->makeAssessment($quarter, 2, 'Quiz 2', now()->startOfWeek()->toDateString());

        $response = $this->actingAs($teacher)
            ->postJson(route('class-records.assessments.upsert', ['classRecord' => $quarter->class_record_id, 'q' => 1]), [
                'assessments' => [$this->payloadFor($keep)],
            ])
            ->assertStatus(422);

        $this->assertStringContainsString('plotted for this week', $response->json('message'));
        $this->assertDatabaseHas('class_record_assessments', ['id' => $plotted->id]);
    }

    public function test_upsert_freely_removes_a_plotted_assessment_whose_scheduled_week_has_passed(): void
    {
        $teacher = User::factory()->create();
        $quarter = $this->makeRecordAndQuarter($teacher, $this->makeSection(), $this->makeSubject());
        $keep = $this->makeAssessment($quarter, 1, 'Quiz 1', now()->addMonth()->toDateString());
        $pastPlotted = $this->makeAssessment($quarter, 2, 'Quiz 2', now()->subMonth()->toDateString());

        $this->actingAs($teacher)
            ->postJson(route('class-records.assessments.upsert', ['classRecord' => $quarter->class_record_id, 'q' => 1]), [
                'assessments' => [$this->payloadFor($keep)],
            ])
            ->assertOk();

        $this->assertDatabaseMissing('class_record_assessments', ['id' => $pastPlotted->id]);
    }

    public function test_upsert_freely_removes_a_plotted_assessment_whose_scheduled_week_has_not_arrived(): void
    {
        $teacher = User::factory()->create();
        $quarter = $this->makeRecordAndQuarter($teacher, $this->makeSection(), $this->makeSubject());
        $keep = $this->makeAssessment($quarter, 1, 'Quiz 1', now()->addMonth()->toDateString());
        $futurePlotted = $this->makeAssessment($quarter, 2, 'Quiz 2', now()->addMonth()->addDay()->toDateString());

        $this->actingAs($teacher)
            ->postJson(route('class-records.assessments.upsert', ['classRecord' => $quarter->class_record_id, 'q' => 1]), [
                'assessments' => [$this->payloadFor($keep)],
            ])
            ->assertOk();

        $this->assertDatabaseMissing('class_record_assessments', ['id' => $futurePlotted->id]);
    }

    // ── requestDeletion() ────────────────────────────────────────────────────

    public function test_request_deletion_rejects_an_unplotted_assessment(): void
    {
        $teacher = User::factory()->create();
        $quarter = $this->makeRecordAndQuarter($teacher, $this->makeSection(), $this->makeSubject());
        $assessment = $this->makeAssessment($quarter, 1, 'Quiz 1', null);

        $this->actingAs($teacher)
            ->postJson(route('class-records.assessments.request-deletion', [
                'classRecord' => $quarter->class_record_id, 'q' => 1, 'assessment' => $assessment->id,
            ]), ['reason' => 'No longer needed'])
            ->assertStatus(422);

        $this->assertDatabaseMissing('class_record_assessment_deletion_requests', ['class_record_assessment_id' => $assessment->id]);
    }

    public function test_request_deletion_rejects_an_assessment_with_scores(): void
    {
        $teacher = User::factory()->create();
        $quarter = $this->makeRecordAndQuarter($teacher, $this->makeSection(), $this->makeSubject());
        $assessment = $this->makeAssessment($quarter, 1, 'Quiz 1', now()->addMonth()->toDateString());

        $student = ClassRecordStudent::create([
            'class_record_quarter_id' => $quarter->id, 'family_name' => 'Dela Cruz', 'given_name' => 'Juan',
            'sex' => 'M', 'sequence_number' => 1, 'is_active' => true,
        ]);
        ClassRecordScore::create([
            'class_record_student_id' => $student->id, 'class_record_assessment_id' => $assessment->id, 'score' => 18,
        ]);

        $this->actingAs($teacher)
            ->postJson(route('class-records.assessments.request-deletion', [
                'classRecord' => $quarter->class_record_id, 'q' => 1, 'assessment' => $assessment->id,
            ]), ['reason' => 'Mistake'])
            ->assertStatus(422);
    }

    public function test_request_deletion_rejects_a_duplicate_pending_request(): void
    {
        $teacher = User::factory()->create();
        $quarter = $this->makeRecordAndQuarter($teacher, $this->makeSection(), $this->makeSubject());
        $assessment = $this->makeAssessment($quarter, 1, 'Quiz 1', now()->addMonth()->toDateString());

        $this->actingAs($teacher)
            ->postJson(route('class-records.assessments.request-deletion', [
                'classRecord' => $quarter->class_record_id, 'q' => 1, 'assessment' => $assessment->id,
            ]), ['reason' => 'First reason'])
            ->assertOk();

        $this->actingAs($teacher)
            ->postJson(route('class-records.assessments.request-deletion', [
                'classRecord' => $quarter->class_record_id, 'q' => 1, 'assessment' => $assessment->id,
            ]), ['reason' => 'Second reason'])
            ->assertStatus(422);

        $this->assertSame(1, ClassRecordAssessmentDeletionRequest::where('class_record_assessment_id', $assessment->id)->count());
    }

    public function test_request_deletion_happy_path_creates_a_pending_request_without_deleting_the_assessment(): void
    {
        $teacher = User::factory()->create();
        $quarter = $this->makeRecordAndQuarter($teacher, $this->makeSection(), $this->makeSubject());
        $assessment = $this->makeAssessment($quarter, 1, 'Quiz 1', now()->addMonth()->toDateString());

        $this->actingAs($teacher)
            ->postJson(route('class-records.assessments.request-deletion', [
                'classRecord' => $quarter->class_record_id, 'q' => 1, 'assessment' => $assessment->id,
            ]), ['reason' => 'Cancelled due to school event'])
            ->assertOk();

        $this->assertDatabaseHas('class_record_assessment_deletion_requests', [
            'class_record_assessment_id' => $assessment->id,
            'status' => 'pending',
            'requested_by_id' => $teacher->id,
            'reason' => 'Cancelled due to school event',
        ]);
        $this->assertDatabaseHas('class_record_assessments', ['id' => $assessment->id]);
    }

    // ── Approval Inbox integration ───────────────────────────────────────────

    public function test_acidaa_can_approve_a_deletion_request_and_the_assessment_is_deleted(): void
    {
        $teacher = User::factory()->create();
        $acidaa = $this->makeAcidaaUser();
        $quarter = $this->makeRecordAndQuarter($teacher, $this->makeSection(), $this->makeSubject());
        $assessment = $this->makeAssessment($quarter, 1, 'Quiz 1', now()->addMonth()->toDateString());

        $this->actingAs($teacher)
            ->postJson(route('class-records.assessments.request-deletion', [
                'classRecord' => $quarter->class_record_id, 'q' => 1, 'assessment' => $assessment->id,
            ]), ['reason' => 'Cancelled'])
            ->assertOk();

        $deletionRequest = ClassRecordAssessmentDeletionRequest::where('class_record_assessment_id', $assessment->id)->firstOrFail();

        $this->actingAs($acidaa)
            ->postJson(route('approvals.approve', ['type' => 'assessment_deletion_requests', 'id' => $deletionRequest->id]))
            ->assertRedirect();

        $this->assertDatabaseMissing('class_record_assessments', ['id' => $assessment->id]);
        $this->assertSame('approved', $deletionRequest->fresh()->status);
    }

    public function test_non_acidaa_user_cannot_approve_a_deletion_request(): void
    {
        $teacher = User::factory()->create();
        $bystander = User::factory()->create();
        $quarter = $this->makeRecordAndQuarter($teacher, $this->makeSection(), $this->makeSubject());
        $assessment = $this->makeAssessment($quarter, 1, 'Quiz 1', now()->addMonth()->toDateString());

        $this->actingAs($teacher)
            ->postJson(route('class-records.assessments.request-deletion', [
                'classRecord' => $quarter->class_record_id, 'q' => 1, 'assessment' => $assessment->id,
            ]), ['reason' => 'Cancelled'])
            ->assertOk();

        $deletionRequest = ClassRecordAssessmentDeletionRequest::where('class_record_assessment_id', $assessment->id)->firstOrFail();

        $this->actingAs($bystander)
            ->postJson(route('approvals.approve', ['type' => 'assessment_deletion_requests', 'id' => $deletionRequest->id]))
            ->assertForbidden();

        $this->assertDatabaseHas('class_record_assessments', ['id' => $assessment->id]);
    }

    public function test_acidaa_decline_leaves_the_assessment_intact_and_allows_a_new_request(): void
    {
        $teacher = User::factory()->create();
        $acidaa = $this->makeAcidaaUser();
        $quarter = $this->makeRecordAndQuarter($teacher, $this->makeSection(), $this->makeSubject());
        $assessment = $this->makeAssessment($quarter, 1, 'Quiz 1', now()->addMonth()->toDateString());

        $this->actingAs($teacher)
            ->postJson(route('class-records.assessments.request-deletion', [
                'classRecord' => $quarter->class_record_id, 'q' => 1, 'assessment' => $assessment->id,
            ]), ['reason' => 'Cancelled'])
            ->assertOk();

        $deletionRequest = ClassRecordAssessmentDeletionRequest::where('class_record_assessment_id', $assessment->id)->firstOrFail();

        $this->actingAs($acidaa)
            ->postJson(route('approvals.decline', ['type' => 'assessment_deletion_requests', 'id' => $deletionRequest->id]), [
                'reason' => 'Assessment is still needed for grading this quarter.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('class_record_assessments', ['id' => $assessment->id]);
        $this->assertSame('rejected', $deletionRequest->fresh()->status);

        // A new request can now be filed since the prior one is no longer pending.
        $this->actingAs($teacher)
            ->postJson(route('class-records.assessments.request-deletion', [
                'classRecord' => $quarter->class_record_id, 'q' => 1, 'assessment' => $assessment->id,
            ]), ['reason' => 'Trying again'])
            ->assertOk();
    }

    public function test_acidaa_sees_the_pending_request_in_the_approval_inbox(): void
    {
        $teacher = User::factory()->create();
        $acidaa = $this->makeAcidaaUser();
        $quarter = $this->makeRecordAndQuarter($teacher, $this->makeSection(), $this->makeSubject());
        $assessment = $this->makeAssessment($quarter, 1, 'Quiz 1', now()->addMonth()->toDateString());

        $this->actingAs($teacher)
            ->postJson(route('class-records.assessments.request-deletion', [
                'classRecord' => $quarter->class_record_id, 'q' => 1, 'assessment' => $assessment->id,
            ]), ['reason' => 'Cancelled'])
            ->assertOk();

        $response = $this->actingAs($acidaa)->get(route('approvals.inbox'));
        $response->assertOk();
        $tabs = $response->viewData('page')['props']['tabs'];
        $this->assertTrue(collect($tabs)->contains('type', 'assessment_deletion_requests'));
    }
}
