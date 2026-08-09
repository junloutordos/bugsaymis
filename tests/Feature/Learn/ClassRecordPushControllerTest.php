<?php

namespace Tests\Feature\Learn;

use App\Models\ClassRecord\ClassRecord;
use App\Models\ClassRecord\ClassRecordAssessment;
use App\Models\ClassRecord\ClassRecordQuarter;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ClassRecordPushControllerTest extends TestCase
{
    use RefreshDatabase;

    private Course $course;
    private User $teacher;
    private User $stranger;
    private ClassRecordAssessment $assessment;
    private ClassRecordQuarter $quarter;
    private Assignment $assignment;

    protected function setUp(): void
    {
        parent::setUp();

        $sy = SchoolYear::create([
            'name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30',
            'is_current' => true, 'status' => 'active',
        ]);
        $term = AcademicTerm::create([
            'school_year_id' => $sy->id, 'name' => '1st Semester', 'term_type' => '1st_semester',
            'start_date' => '2025-08-01', 'end_date' => '2025-12-31', 'is_current' => true,
        ]);
        $section = Section::create([
            'levelid' => 8, 'sectionname' => 'Emerald', 'syid' => $sy->id,
            'school_year_id' => $sy->id, 'is_active' => true,
        ]);
        $subject = Subject::create([
            'school_year_id' => $sy->id, 'code' => 'SCI8', 'name' => 'Science 8',
            'credit_units' => 3, 'lecture_hours' => 3, 'load_units' => 3, 'subject_type' => 'lecture',
            'grade_level' => 8, 'sessions_per_week' => 4, 'minutes_per_session' => 60, 'is_active' => true,
        ]);

        $this->teacher = User::factory()->create();
        $this->stranger = User::factory()->create();
        $facultyLoad = FacultyLoad::create([
            'user_id' => $this->teacher->id, 'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
        ]);
        LoadAssignment::create([
            'faculty_load_id' => $facultyLoad->id, 'user_id' => $this->teacher->id,
            'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
            'assignment_type' => 'teaching', 'subject_id' => $subject->id,
            'section_id' => $section->id, 'load_units' => 3,
        ]);

        $this->course = Course::create([
            'subject_id' => $subject->id, 'section_id' => $section->id,
            'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
        ]);
        $module = $this->course->modules()->create(['title' => 'Week 1', 'position' => 0]);
        $this->assignment = Assignment::create(['title' => 'Essay', 'submission_type' => 'text', 'points_possible' => 40]);
        $this->assignment->moduleItem()->create(['learn_module_id' => $module->id, 'position' => 0]);

        $option = GradingOption::create(['name' => 'Standard', 'is_active' => true]);
        $classRecord = ClassRecord::create([
            'subject_id' => $subject->id, 'section_id' => $section->id,
            'grading_option_id' => $option->id, 'school_year_id' => $sy->id,
            'school_year' => $sy->name, 'subject_name' => $subject->name,
            'year_level_section' => "G-{$section->levelid} {$section->sectionname}",
            'teacher_id' => $this->teacher->id, 'status' => 'draft',
        ]);
        $this->quarter = ClassRecordQuarter::create([
            'class_record_id' => $classRecord->id, 'grading_option_id' => $option->id,
            'quarter' => 1, 'is_locked' => false,
        ]);
        $category = GradingCategory::create([
            'grading_option_id' => $option->id, 'name' => 'Written Work', 'code' => 'WW',
            'weight' => 0.5, 'max_assessments' => 5, 'sort_order' => 1,
        ]);
        $this->assessment = ClassRecordAssessment::create([
            'class_record_quarter_id' => $this->quarter->id, 'grading_category_id' => $category->id,
            'assessment_type' => 'formative', 'is_graded' => true, 'is_major' => false,
            'assessment_number' => 1, 'title' => 'Essay 1', 'max_score' => 40, 'sort_order' => 1,
        ]);
    }

    public function test_instructor_can_link_and_push_stranger_cannot(): void
    {
        $this->actingAs($this->teacher)
            ->put(route('learn.assignments.link', $this->assignment), ['class_record_assessment_id' => $this->assessment->id])
            ->assertRedirect();
        $this->assertSame($this->assessment->id, $this->assignment->fresh()->class_record_assessment_id);

        $studentId = DB::table('students')->insertGetId([
            'pisaysystemID' => 'PS' . str_pad((string) mt_rand(1, 999999999), 9, '0', STR_PAD_LEFT),
            'lastname' => 'Cruz', 'firstname' => 'Juan', 'sex' => 'M',
        ]);
        ClassRecordStudent::create([
            'class_record_quarter_id' => $this->quarter->id, 'student_id' => $studentId,
            'family_name' => 'Cruz', 'given_name' => 'Juan', 'sequence_number' => 1, 'is_active' => true,
        ]);
        Submission::create([
            'learn_assignment_id' => $this->assignment->id, 'student_id' => $studentId,
            'text_body' => 'x', 'submitted_at' => now(), 'score' => 35, 'graded_at' => now(),
        ]);

        $this->actingAs($this->teacher)
            ->post(route('learn.assignments.push', $this->assignment))
            ->assertRedirect();
        $this->assertNotNull($this->assignment->fresh()->pushed_at);

        $newAssessment = ClassRecordAssessment::create([
            'class_record_quarter_id' => $this->quarter->id, 'grading_category_id' => $this->assessment->grading_category_id,
            'assessment_type' => 'formative', 'is_graded' => true, 'is_major' => false,
            'assessment_number' => 2, 'title' => 'Essay 2', 'max_score' => 40, 'sort_order' => 2,
        ]);
        $this->actingAs($this->stranger)
            ->put(route('learn.assignments.link', $this->assignment), ['class_record_assessment_id' => $newAssessment->id])
            ->assertForbidden();
        $this->actingAs($this->stranger)
            ->post(route('learn.assignments.push', $this->assignment))
            ->assertForbidden();
    }

    public function test_link_returns_validation_error_on_max_score_mismatch(): void
    {
        $mismatched = ClassRecordAssessment::create([
            'class_record_quarter_id' => $this->quarter->id, 'grading_category_id' => $this->assessment->grading_category_id,
            'assessment_type' => 'formative', 'is_graded' => true, 'is_major' => false,
            'assessment_number' => 3, 'title' => 'Quiz', 'max_score' => 10, 'sort_order' => 3,
        ]);

        $this->actingAs($this->teacher)
            ->put(route('learn.assignments.link', $this->assignment), ['class_record_assessment_id' => $mismatched->id])
            ->assertSessionHasErrors('class_record_assessment_id');
    }

    public function test_push_without_a_link_returns_422(): void
    {
        $this->actingAs($this->teacher)
            ->post(route('learn.assignments.push', $this->assignment))
            ->assertStatus(422);
    }
}
