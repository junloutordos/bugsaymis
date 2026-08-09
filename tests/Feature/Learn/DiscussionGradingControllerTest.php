<?php

namespace Tests\Feature\Learn;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\FacultyLoad;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\Learn\Course;
use App\Models\Learn\Discussion;
use App\Models\Registrar\StudentEnrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DiscussionGradingControllerTest extends TestCase
{
    use RefreshDatabase;

    private Discussion $discussion;
    private User $teacher;
    private int $studentId;

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
        $facultyLoad = FacultyLoad::create([
            'user_id' => $this->teacher->id, 'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
        ]);
        LoadAssignment::create([
            'faculty_load_id' => $facultyLoad->id, 'user_id' => $this->teacher->id,
            'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
            'assignment_type' => 'teaching', 'subject_id' => $subject->id,
            'section_id' => $section->id, 'load_units' => 3,
        ]);
        $course = Course::create([
            'subject_id' => $subject->id, 'section_id' => $section->id,
            'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
        ]);
        $module = $course->modules()->create(['title' => 'Week 1', 'position' => 0]);
        $this->discussion = Discussion::create(['title' => 'D', 'prompt' => 'P', 'points_possible' => 10]);
        $this->discussion->moduleItem()->create(['learn_module_id' => $module->id, 'position' => 0]);

        $this->studentId = mt_rand(1, 999999999);
        DB::table('students')->insert(['id' => $this->studentId, 'pisaysystemID' => "PS{$this->studentId}", 'firstname' => 'Ana', 'lastname' => 'Cruz']);
        StudentEnrollment::create([
            'student_id' => $this->studentId, 'school_year_id' => $sy->id, 'section_id' => $section->id,
            'grade_level' => 8, 'status' => 'enrolled', 'enrollment_date' => now()->toDateString(),
        ]);
    }

    public function test_index_lists_every_enrolled_student_even_those_who_never_posted(): void
    {
        $response = $this->actingAs($this->teacher)->get(route('learn.discussions.grades', $this->discussion));

        $response->assertInertia(fn ($page) => $page
            ->where('roster.0.student_id', $this->studentId)
            ->where('roster.0.name', 'Cruz, Ana')
            ->where('roster.0.points_earned', null)
        );
    }

    public function test_grading_a_student_persists_and_is_reflected_in_the_roster(): void
    {
        $this->actingAs($this->teacher)->put(route('learn.discussions.grade', [$this->discussion, $this->studentId]), [
            'points_earned' => 8, 'feedback_comment' => 'Great participation.',
        ])->assertRedirect();

        $grade = $this->discussion->grades()->where('student_id', $this->studentId)->firstOrFail();
        $this->assertSame('8.00', $grade->points_earned);
        $this->assertSame('Great participation.', $grade->feedback_comment);
        $this->assertNotNull($grade->graded_at);
    }

    public function test_grading_rejects_a_score_above_points_possible(): void
    {
        $this->actingAs($this->teacher)->put(route('learn.discussions.grade', [$this->discussion, $this->studentId]), [
            'points_earned' => 999,
        ])->assertSessionHasErrors('points_earned');
    }

    public function test_stranger_cannot_view_roster_or_grade(): void
    {
        $stranger = User::factory()->create();
        $this->actingAs($stranger)->get(route('learn.discussions.grades', $this->discussion))->assertForbidden();
        $this->actingAs($stranger)->put(route('learn.discussions.grade', [$this->discussion, $this->studentId]), ['points_earned' => 5])->assertForbidden();
    }
}
