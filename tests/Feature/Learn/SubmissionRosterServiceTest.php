<?php

namespace Tests\Feature\Learn;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\Learn\Assignment;
use App\Models\Learn\Course;
use App\Models\Learn\Submission;
use App\Models\Registrar\StudentEnrollment;
use App\Services\Learn\SubmissionRosterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SubmissionRosterServiceTest extends TestCase
{
    use RefreshDatabase;

    private Course $course;
    private Assignment $assignment;
    private SchoolYear $sy;
    private Section $section;

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
        $subject = Subject::create([
            'school_year_id' => $this->sy->id, 'code' => 'SCI8', 'name' => 'Science 8',
            'credit_units' => 3, 'lecture_hours' => 3, 'load_units' => 3, 'subject_type' => 'lecture',
            'grade_level' => 8, 'sessions_per_week' => 4, 'minutes_per_session' => 60, 'is_active' => true,
        ]);
        $this->course = Course::create([
            'subject_id' => $subject->id, 'section_id' => $this->section->id,
            'school_year_id' => $this->sy->id, 'academic_term_id' => $term->id,
        ]);
        $module = $this->course->modules()->create(['title' => 'Week 1', 'position' => 0]);
        $this->assignment = Assignment::create([
            'title' => 'Essay', 'submission_type' => 'text', 'points_possible' => 40,
            'due_at' => '2026-01-10 23:59:00',
        ]);
        $this->assignment->moduleItem()->create(['learn_module_id' => $module->id, 'position' => 0]);
    }

    private function enrollStudent(string $lastname): int
    {
        $studentId = DB::table('students')->insertGetId([
            'pisaysystemID' => 'PS' . str_pad((string) rand(1, 999999999), 9, '0', STR_PAD_LEFT),
            'lastname' => $lastname, 'firstname' => 'Test', 'sex' => 'M',
        ]);
        StudentEnrollment::create([
            'student_id' => $studentId, 'school_year_id' => $this->sy->id, 'section_id' => $this->section->id,
            'grade_level' => 8, 'status' => 'enrolled', 'enrollment_date' => now()->toDateString(),
        ]);

        return $studentId;
    }

    public function test_roster_shows_not_submitted_submitted_late_and_graded_statuses(): void
    {
        $notSubmitted = $this->enrollStudent('Alpha');
        $submittedOnTime = $this->enrollStudent('Bravo');
        $submittedLate = $this->enrollStudent('Charlie');
        $graded = $this->enrollStudent('Delta');

        Submission::create([
            'learn_assignment_id' => $this->assignment->id, 'student_id' => $submittedOnTime,
            'text_body' => 'x', 'submitted_at' => '2026-01-10 08:00:00',
        ]);
        Submission::create([
            'learn_assignment_id' => $this->assignment->id, 'student_id' => $submittedLate,
            'text_body' => 'x', 'submitted_at' => '2026-01-11 08:00:00',
        ]);
        Submission::create([
            'learn_assignment_id' => $this->assignment->id, 'student_id' => $graded,
            'text_body' => 'x', 'submitted_at' => '2026-01-09 08:00:00',
            'score' => 35, 'graded_at' => now(),
        ]);

        $roster = app(SubmissionRosterService::class)->rosterFor($this->assignment);

        $this->assertCount(4, $roster);
        $byStudent = $roster->keyBy('student_id');
        $this->assertSame('not_submitted', $byStudent[$notSubmitted]['status']);
        $this->assertSame('submitted', $byStudent[$submittedOnTime]['status']);
        $this->assertSame('late', $byStudent[$submittedLate]['status']);
        $this->assertSame('graded', $byStudent[$graded]['status']);
    }

    public function test_roster_excludes_students_not_enrolled_in_the_course_section(): void
    {
        $enrolled = $this->enrollStudent('Enrolled');

        $otherSection = Section::create([
            'levelid' => 8, 'sectionname' => 'Ruby', 'syid' => $this->sy->id,
            'school_year_id' => $this->sy->id, 'is_active' => true,
        ]);
        $notEnrolledId = DB::table('students')->insertGetId([
            'pisaysystemID' => 'PS000000999', 'lastname' => 'Outside', 'firstname' => 'Test', 'sex' => 'F',
        ]);
        StudentEnrollment::create([
            'student_id' => $notEnrolledId, 'school_year_id' => $this->sy->id, 'section_id' => $otherSection->id,
            'grade_level' => 8, 'status' => 'enrolled', 'enrollment_date' => now()->toDateString(),
        ]);

        $roster = app(SubmissionRosterService::class)->rosterFor($this->assignment);

        $this->assertCount(1, $roster);
        $this->assertSame($enrolled, $roster->first()['student_id']);
    }
}
