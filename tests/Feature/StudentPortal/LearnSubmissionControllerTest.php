<?php

namespace Tests\Feature\StudentPortal;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\Learn\Assignment;
use App\Models\Learn\Course;
use App\Models\Learn\Submission;
use App\Models\Registrar\StudentEnrollment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LearnSubmissionControllerTest extends TestCase
{
    use RefreshDatabase;

    private static int $studentCounter = 0;

    private Course $course;
    private int $studentId;
    private string $studentPisaysystemID;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('s3');

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
        $this->course = Course::create([
            'subject_id' => $subject->id, 'section_id' => $section->id,
            'school_year_id' => $sy->id, 'academic_term_id' => $term->id, 'status' => 'published',
        ]);

        // students is ENGINE=MyISAM and ignores transactions — RefreshDatabase's
        // per-test rollback never applies to it, so a fixed ID collides across
        // this file's test methods within one process run.
        self::$studentCounter++;
        $this->studentPisaysystemID = 'PS' . str_pad((string) self::$studentCounter, 9, '0', STR_PAD_LEFT);
        $this->studentId = DB::table('students')->insertGetId([
            'pisaysystemID' => $this->studentPisaysystemID, 'lastname' => 'Cruz', 'firstname' => 'Juan', 'sex' => 'M',
        ]);
        StudentEnrollment::create([
            'student_id' => $this->studentId, 'school_year_id' => $sy->id, 'section_id' => $section->id,
            'grade_level' => 8, 'status' => 'enrolled', 'enrollment_date' => now()->toDateString(),
        ]);

        session(['student_pisaysystemID' => $this->studentPisaysystemID]);
    }

    private function makeAssignment(string $submissionType): Assignment
    {
        $module = $this->course->modules()->create(['title' => 'Week 1', 'position' => 0, 'published_at' => now()]);
        $assignment = Assignment::create(['title' => 'Work', 'submission_type' => $submissionType, 'points_possible' => 40]);
        $assignment->moduleItem()->create(['learn_module_id' => $module->id, 'position' => 0, 'published_at' => now()]);

        return $assignment;
    }

    public function test_student_can_submit_a_text_assignment(): void
    {
        $assignment = $this->makeAssignment('text');

        $this->post(route('student-portal.learn.assignments.submit', $assignment), [
            'text_body' => 'My essay text',
        ])->assertRedirect();

        $this->assertDatabaseHas('learn_submissions', [
            'learn_assignment_id' => $assignment->id, 'student_id' => $this->studentId, 'text_body' => 'My essay text',
        ]);
    }

    public function test_student_can_submit_a_link_assignment(): void
    {
        $assignment = $this->makeAssignment('link');

        $this->post(route('student-portal.learn.assignments.submit', $assignment), [
            'link_url' => 'https://docs.google.com/document/d/abc123',
        ])->assertRedirect();

        $this->assertDatabaseHas('learn_submissions', [
            'learn_assignment_id' => $assignment->id, 'link_url' => 'https://docs.google.com/document/d/abc123',
        ]);
    }

    public function test_student_can_submit_a_file_assignment(): void
    {
        $assignment = $this->makeAssignment('file');
        $dataUri = 'data:application/pdf;base64,' . base64_encode('fake pdf');

        $this->post(route('student-portal.learn.assignments.submit', $assignment), [
            'title' => 'homework.pdf', 'file_base64' => $dataUri,
        ])->assertRedirect();

        $submission = Submission::where('learn_assignment_id', $assignment->id)->firstOrFail();
        $this->assertNotNull($submission->learn_file_id);
    }

    public function test_resubmission_overwrites_the_same_row(): void
    {
        $assignment = $this->makeAssignment('text');

        $this->post(route('student-portal.learn.assignments.submit', $assignment), ['text_body' => 'First draft']);
        $this->post(route('student-portal.learn.assignments.submit', $assignment), ['text_body' => 'Final draft']);

        $this->assertSame(1, Submission::where('learn_assignment_id', $assignment->id)->count());
        $this->assertSame('Final draft', Submission::where('learn_assignment_id', $assignment->id)->first()->text_body);
    }

    public function test_graded_submission_cannot_be_resubmitted(): void
    {
        $assignment = $this->makeAssignment('text');
        Submission::create([
            'learn_assignment_id' => $assignment->id, 'student_id' => $this->studentId,
            'text_body' => 'Graded already', 'submitted_at' => now(), 'score' => 30, 'graded_at' => now(),
        ]);

        $this->post(route('student-portal.learn.assignments.submit', $assignment), [
            'text_body' => 'Trying to change it',
        ])->assertForbidden();
    }

    public function test_student_cannot_submit_to_an_assignment_outside_their_enrolled_section(): void
    {
        $otherSection = Section::create([
            'levelid' => 8, 'sectionname' => 'Ruby', 'syid' => $this->course->school_year_id,
            'school_year_id' => $this->course->school_year_id, 'is_active' => true,
        ]);
        $otherSubject = Subject::create([
            'school_year_id' => $this->course->school_year_id, 'code' => 'MATH8', 'name' => 'Math 8',
            'credit_units' => 3, 'lecture_hours' => 3, 'load_units' => 3, 'subject_type' => 'lecture',
            'grade_level' => 8, 'sessions_per_week' => 4, 'minutes_per_session' => 60, 'is_active' => true,
        ]);
        $otherCourse = Course::create([
            'subject_id' => $otherSubject->id, 'section_id' => $otherSection->id,
            'school_year_id' => $this->course->school_year_id, 'academic_term_id' => $this->course->academic_term_id,
            'status' => 'published',
        ]);
        $module = $otherCourse->modules()->create(['title' => 'Week 1', 'position' => 0, 'published_at' => now()]);
        $assignment = Assignment::create(['title' => 'Not mine', 'submission_type' => 'text']);
        $assignment->moduleItem()->create(['learn_module_id' => $module->id, 'position' => 0, 'published_at' => now()]);

        $this->post(route('student-portal.learn.assignments.submit', $assignment), [
            'text_body' => 'x',
        ])->assertForbidden();
    }

    public function test_student_cannot_view_another_students_submission_file_by_guessing_the_id(): void
    {
        $assignment = $this->makeAssignment('file');
        $dataUri = 'data:application/pdf;base64,' . base64_encode('fake pdf');
        $this->post(route('student-portal.learn.assignments.submit', $assignment), [
            'title' => 'homework.pdf', 'file_base64' => $dataUri,
        ]);
        $submission = Submission::where('learn_assignment_id', $assignment->id)->firstOrFail();

        self::$studentCounter++;
        $otherPisaysystemID = 'PS' . str_pad((string) self::$studentCounter, 9, '0', STR_PAD_LEFT);
        DB::table('students')->insert([
            'pisaysystemID' => $otherPisaysystemID, 'lastname' => 'Reyes', 'firstname' => 'Ana', 'sex' => 'F',
        ]);
        session(['student_pisaysystemID' => $otherPisaysystemID]);

        $this->get(route('student-portal.learn.submissions.file', $submission))->assertForbidden();
    }
}
