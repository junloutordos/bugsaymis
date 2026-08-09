<?php

namespace Tests\Feature\Learn;

use App\Models\ClassRecord\ClassRecord;
use App\Models\ClassRecord\ClassRecordAssessment;
use App\Models\ClassRecord\ClassRecordQuarter;
use App\Models\ClassRecord\ClassRecordScore;
use App\Models\ClassRecord\ClassRecordStudent;
use App\Models\ClassRecord\GradingCategory;
use App\Models\ClassRecord\GradingOption;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\FacultyLoad;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\Learn\Assignment;
use App\Models\Learn\Course;
use App\Models\Learn\Submission;
use App\Models\User;
use App\Services\Learn\ClassRecordPushService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ClassRecordPushServiceTest extends TestCase
{
    use RefreshDatabase;

    private SchoolYear $sy;
    private Section $section;
    private Subject $subject;
    private Course $course;
    private User $teacher;
    private GradingOption $option;
    private ClassRecord $classRecord;
    private ClassRecordQuarter $quarter;
    private GradingCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sy = SchoolYear::create([
            'name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30',
            'is_current' => true, 'status' => 'active',
        ]);
        $term = AcademicTerm::create([
            'school_year_id' => $this->sy->id, 'name' => '1st Semester', 'term_type' => '1st_semester',
            'start_date' => '2025-08-01', 'end_date' => '2025-12-31', 'is_current' => true,
        ]);
        $this->section = Section::create([
            'levelid' => 8, 'sectionname' => 'Emerald', 'syid' => $this->sy->id,
            'school_year_id' => $this->sy->id, 'is_active' => true,
        ]);
        $this->subject = Subject::create([
            'school_year_id' => $this->sy->id, 'code' => 'SCI8', 'name' => 'Science 8',
            'credit_units' => 3, 'lecture_hours' => 3, 'load_units' => 3, 'subject_type' => 'lecture',
            'grade_level' => 8, 'sessions_per_week' => 4, 'minutes_per_session' => 60, 'is_active' => true,
        ]);
        $this->teacher = User::factory()->create();

        $facultyLoad = FacultyLoad::create([
            'user_id' => $this->teacher->id, 'school_year_id' => $this->sy->id, 'academic_term_id' => $term->id,
        ]);
        LoadAssignment::create([
            'faculty_load_id' => $facultyLoad->id, 'user_id' => $this->teacher->id,
            'school_year_id' => $this->sy->id, 'academic_term_id' => $term->id,
            'assignment_type' => 'teaching', 'subject_id' => $this->subject->id,
            'section_id' => $this->section->id, 'load_units' => 3,
        ]);

        $this->course = Course::create([
            'subject_id' => $this->subject->id, 'section_id' => $this->section->id,
            'school_year_id' => $this->sy->id, 'academic_term_id' => $term->id,
        ]);

        $this->option = GradingOption::create(['name' => 'Standard', 'is_active' => true]);
        $this->classRecord = ClassRecord::create([
            'subject_id' => $this->subject->id, 'section_id' => $this->section->id,
            'grading_option_id' => $this->option->id, 'school_year_id' => $this->sy->id,
            'school_year' => $this->sy->name, 'subject_name' => $this->subject->name,
            'year_level_section' => "G-{$this->section->levelid} {$this->section->sectionname}",
            'teacher_id' => $this->teacher->id, 'status' => 'draft',
        ]);
        $this->quarter = ClassRecordQuarter::create([
            'class_record_id' => $this->classRecord->id, 'grading_option_id' => $this->option->id,
            'quarter' => 1, 'is_locked' => false,
        ]);
        $this->category = GradingCategory::create([
            'grading_option_id' => $this->option->id, 'name' => 'Written Work', 'code' => 'WW',
            'weight' => 0.5, 'max_assessments' => 5, 'sort_order' => 1,
        ]);
    }

    private function makeAssessment(float $maxScore = 40): ClassRecordAssessment
    {
        return ClassRecordAssessment::create([
            'class_record_quarter_id' => $this->quarter->id, 'grading_category_id' => $this->category->id,
            'assessment_type' => 'formative', 'is_graded' => true, 'is_major' => false,
            'assessment_number' => 1, 'title' => 'Essay 1', 'max_score' => $maxScore, 'sort_order' => 1,
        ]);
    }

    private function makeAssignment(float $pointsPossible = 40): Assignment
    {
        $module = $this->course->modules()->create(['title' => 'Week 1', 'position' => 0]);
        $assignment = Assignment::create(['title' => 'Essay', 'submission_type' => 'text', 'points_possible' => $pointsPossible]);
        $assignment->moduleItem()->create(['learn_module_id' => $module->id, 'position' => 0]);

        return $assignment;
    }

    private function enrollAndRoster(): int
    {
        $studentId = DB::table('students')->insertGetId([
            'pisaysystemID' => 'PS' . str_pad((string) mt_rand(1, 999999999), 9, '0', STR_PAD_LEFT),
            'lastname' => 'Cruz', 'firstname' => 'Juan', 'sex' => 'M',
        ]);
        ClassRecordStudent::create([
            'class_record_quarter_id' => $this->quarter->id, 'student_id' => $studentId,
            'family_name' => 'Cruz', 'given_name' => 'Juan', 'sequence_number' => 1, 'is_active' => true,
        ]);

        return $studentId;
    }

    public function test_candidate_class_records_resolves_matching_records_with_graded_assessments(): void
    {
        $this->makeAssessment();
        $assignment = $this->makeAssignment();

        $candidates = app(ClassRecordPushService::class)->candidateClassRecords($assignment);

        $this->assertCount(1, $candidates);
        $this->assertTrue($candidates->first()->is($this->classRecord));
        $this->assertCount(1, $candidates->first()->quarters->first()->assessments);
    }

    public function test_candidate_class_records_includes_sibling_records_with_different_category_labels(): void
    {
        $this->makeAssessment();

        $siblingRecord = ClassRecord::create([
            'subject_id' => $this->subject->id, 'section_id' => $this->section->id,
            'grading_option_id' => $this->option->id, 'school_year_id' => $this->sy->id,
            'school_year' => $this->sy->name, 'subject_name' => $this->subject->name,
            'category_label' => 'Completed', 'year_level_section' => "G-{$this->section->levelid} {$this->section->sectionname}",
            'teacher_id' => $this->teacher->id, 'status' => 'draft',
        ]);
        $siblingQuarter = ClassRecordQuarter::create([
            'class_record_id' => $siblingRecord->id, 'grading_option_id' => $this->option->id,
            'quarter' => 1, 'is_locked' => false,
        ]);
        ClassRecordAssessment::create([
            'class_record_quarter_id' => $siblingQuarter->id, 'grading_category_id' => $this->category->id,
            'assessment_type' => 'formative', 'is_graded' => true, 'is_major' => false,
            'assessment_number' => 1, 'title' => 'Sibling Essay', 'max_score' => 40, 'sort_order' => 1,
        ]);
        $assignment = $this->makeAssignment();

        $candidates = app(ClassRecordPushService::class)->candidateClassRecords($assignment);

        $this->assertCount(2, $candidates);
        $this->assertEqualsCanonicalizing(
            ['', 'Completed'],
            $candidates->pluck('category_label')->map(fn ($v) => $v ?? '')->all()
        );
    }

    public function test_link_succeeds_when_max_scores_match(): void
    {
        $assessment = $this->makeAssessment(40);
        $assignment = $this->makeAssignment(40);

        app(ClassRecordPushService::class)->link($assignment, $assessment->id, $this->teacher);

        $this->assertSame($assessment->id, $assignment->fresh()->class_record_assessment_id);
    }

    public function test_link_rejects_a_max_score_mismatch(): void
    {
        $assessment = $this->makeAssessment(20);
        $assignment = $this->makeAssignment(40);

        $this->expectException(ValidationException::class);

        app(ClassRecordPushService::class)->link($assignment, $assessment->id, $this->teacher);
    }

    public function test_link_403s_a_stranger_with_no_learn_edit_access(): void
    {
        $assessment = $this->makeAssessment();
        $assignment = $this->makeAssignment();
        $stranger = User::factory()->create();

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        app(ClassRecordPushService::class)->link($assignment, $assessment->id, $stranger);
    }

    public function test_link_403s_when_learn_instructor_lacks_class_record_access_to_the_specific_category(): void
    {
        $assessment = $this->makeAssessment(40);

        $otherTeacher = User::factory()->create();
        $this->category->update(['subject_id' => $this->subject->id]);
        \App\Models\ClassRecord\ClassRecordTeacher::create([
            'class_record_id' => $this->classRecord->id, 'subject_id' => $this->subject->id,
            'user_id' => $otherTeacher->id, 'is_primary' => true,
        ]);
        $assignment = $this->makeAssignment(40);

        $this->assertTrue($assignment->canEdit($this->teacher), 'sanity check: Learn-side access is fine');

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        app(ClassRecordPushService::class)->link($assignment, $assessment->id, $this->teacher);
    }

    public function test_push_copies_graded_scores_and_overwrites_on_repush(): void
    {
        $assessment = $this->makeAssessment(40);
        $assignment = $this->makeAssignment(40);
        $studentId = $this->enrollAndRoster();

        Submission::create([
            'learn_assignment_id' => $assignment->id, 'student_id' => $studentId,
            'text_body' => 'x', 'submitted_at' => now(), 'score' => 35, 'graded_at' => now(),
        ]);

        $service = app(ClassRecordPushService::class);
        $service->link($assignment, $assessment->id, $this->teacher);
        $result = $service->push($assignment, $this->teacher);

        $this->assertSame(1, $result['pushed']);
        $this->assertEmpty($result['skipped']);

        $classRecordStudent = ClassRecordStudent::where('student_id', $studentId)->first();
        $this->assertSame('35.00', ClassRecordScore::where([
            'class_record_student_id' => $classRecordStudent->id, 'class_record_assessment_id' => $assessment->id,
        ])->value('score'));

        Submission::where('learn_assignment_id', $assignment->id)->update(['score' => 38]);
        $service->push($assignment, $this->teacher);

        $this->assertSame(1, ClassRecordScore::where([
            'class_record_student_id' => $classRecordStudent->id, 'class_record_assessment_id' => $assessment->id,
        ])->count());
        $this->assertSame('38.00', ClassRecordScore::where([
            'class_record_student_id' => $classRecordStudent->id, 'class_record_assessment_id' => $assessment->id,
        ])->value('score'));
    }

    public function test_push_skips_and_reports_a_student_missing_from_the_quarter_roster(): void
    {
        $assessment = $this->makeAssessment(40);
        $assignment = $this->makeAssignment(40);

        $unrosteredStudentId = DB::table('students')->insertGetId([
            'pisaysystemID' => 'PS' . str_pad((string) mt_rand(1, 999999999), 9, '0', STR_PAD_LEFT),
            'lastname' => 'Reyes', 'firstname' => 'Ana', 'sex' => 'F',
        ]);
        Submission::create([
            'learn_assignment_id' => $assignment->id, 'student_id' => $unrosteredStudentId,
            'text_body' => 'x', 'submitted_at' => now(), 'score' => 30, 'graded_at' => now(),
        ]);

        $service = app(ClassRecordPushService::class);
        $service->link($assignment, $assessment->id, $this->teacher);
        $result = $service->push($assignment, $this->teacher);

        $this->assertSame(0, $result['pushed']);
        $this->assertCount(1, $result['skipped']);
        $this->assertStringContainsString('Reyes', $result['skipped'][0]);
    }

    public function test_push_never_pushes_ungraded_submissions(): void
    {
        $assessment = $this->makeAssessment(40);
        $assignment = $this->makeAssignment(40);
        $studentId = $this->enrollAndRoster();

        Submission::create([
            'learn_assignment_id' => $assignment->id, 'student_id' => $studentId,
            'text_body' => 'x', 'submitted_at' => now(),
        ]);

        $service = app(ClassRecordPushService::class);
        $service->link($assignment, $assessment->id, $this->teacher);
        $result = $service->push($assignment, $this->teacher);

        $this->assertSame(0, $result['pushed']);
        $this->assertDatabaseCount('class_record_scores', 0);
    }

    public function test_push_sets_pushed_at_and_never_touches_wat_governed_columns(): void
    {
        $assessment = $this->makeAssessment(40);
        $originalActivityDate = $assessment->activity_date;
        $originalPlottedAt = $assessment->plotted_at;
        $assignment = $this->makeAssignment(40);
        $this->enrollAndRoster();

        $service = app(ClassRecordPushService::class);
        $service->link($assignment, $assessment->id, $this->teacher);
        $service->push($assignment, $this->teacher);

        $this->assertNotNull($assignment->fresh()->pushed_at);
        $assessment->refresh();
        $this->assertEquals($originalActivityDate, $assessment->activity_date);
        $this->assertEquals($originalPlottedAt, $assessment->plotted_at);
    }
}
