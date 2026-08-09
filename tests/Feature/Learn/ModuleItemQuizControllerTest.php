<?php

namespace Tests\Feature\Learn;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\FacultyLoad;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\Learn\Course;
use App\Models\Learn\ModuleItem;
use App\Models\Learn\Quiz;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModuleItemQuizControllerTest extends TestCase
{
    use RefreshDatabase;

    private $module;
    private User $teacher;

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
        $this->module = $course->modules()->create(['title' => 'Week 1', 'position' => 0]);
    }

    public function test_instructor_can_add_a_quiz_with_mixed_question_types(): void
    {
        $response = $this->actingAs($this->teacher)->post(route('learn.items.store-quiz', $this->module), [
            'title' => 'Chapter 1 Quiz',
            'time_limit_minutes' => 20,
            'max_attempts' => 2,
            'shuffle_questions' => true,
            'questions' => [
                [
                    'question_type' => 'multiple_choice', 'prompt' => 'What is 2+2?', 'points' => 5,
                    'options' => [
                        ['option_text' => '4', 'is_correct' => true],
                        ['option_text' => '5', 'is_correct' => false],
                    ],
                ],
                [
                    'question_type' => 'short_answer', 'prompt' => 'Capital of the Philippines?', 'points' => 5,
                    'accepted_answers' => ['Manila', 'City of Manila'],
                ],
                [
                    'question_type' => 'essay', 'prompt' => 'Explain photosynthesis.', 'points' => 10,
                ],
            ],
        ]);

        $response->assertRedirect();

        $quiz = Quiz::where('title', 'Chapter 1 Quiz')->firstOrFail();
        $this->assertSame(20, $quiz->time_limit_minutes);
        $this->assertCount(3, $quiz->questions);
        $this->assertCount(2, $quiz->questions[0]->options);
        $this->assertCount(2, $quiz->questions[1]->acceptedAnswers);
        $this->assertSame(20.0, $quiz->maxScore());

        $item = ModuleItem::where('itemable_type', Quiz::class)->where('itemable_id', $quiz->id)->first();
        $this->assertNotNull($item);
    }

    public function test_questions_to_draw_requires_equal_points_across_questions(): void
    {
        $response = $this->actingAs($this->teacher)->post(route('learn.items.store-quiz', $this->module), [
            'title' => 'Pool Quiz', 'questions_to_draw' => 2,
            'questions' => [
                ['question_type' => 'essay', 'prompt' => 'A', 'points' => 5],
                ['question_type' => 'essay', 'prompt' => 'B', 'points' => 10],
            ],
        ]);

        $response->assertSessionHasErrors('questions_to_draw');
        $this->assertDatabaseCount('learn_quizzes', 0);
    }

    public function test_stranger_cannot_add_a_quiz(): void
    {
        $stranger = User::factory()->create();
        $this->actingAs($stranger)->post(route('learn.items.store-quiz', $this->module), [
            'title' => 'X',
        ])->assertForbidden();
    }
}
