<?php

namespace Tests\Feature\StudentPortal;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\Learn\Course;
use App\Models\Learn\Discussion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LearnDiscussionSerializationTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_serializes_discussion_item_without_the_full_post_tree(): void
    {
        $sy = SchoolYear::create([
            'name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30',
            'is_current' => true, 'status' => 'active',
        ]);
        AcademicTerm::create([
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
        $course = Course::create([
            'subject_id' => $subject->id, 'section_id' => $section->id,
            'school_year_id' => $sy->id, 'academic_term_id' => AcademicTerm::first()->id, 'status' => 'published',
        ]);
        $module = $course->modules()->create(['title' => 'Week 1', 'position' => 0, 'published_at' => now()]);
        $discussion = Discussion::create(['title' => 'Discuss', 'prompt' => 'P']);
        $discussion->moduleItem()->create(['learn_module_id' => $module->id, 'position' => 0, 'published_at' => now()]);
        $discussion->posts()->create(['author_type' => 'faculty', 'author_id' => 1, 'body' => 'Hi']);

        $studentId = mt_rand(1, 999999999);
        \Illuminate\Support\Facades\DB::table('students')->insert([
            'id' => $studentId, 'pisaysystemID' => "PS{$studentId}", 'firstname' => 'Test', 'lastname' => 'Student',
        ]);
        \App\Models\Registrar\StudentEnrollment::create([
            'student_id' => $studentId, 'school_year_id' => $sy->id, 'section_id' => $section->id,
            'grade_level' => 8, 'status' => 'enrolled', 'enrollment_date' => now()->toDateString(),
        ]);
        session(['student_pisaysystemID' => "PS{$studentId}"]);

        $response = $this->get(route('student-portal.learn.show', $course));

        $response->assertInertia(fn ($page) => $page
            ->where('course.modules.0.items.0.type', 'discussion')
            ->where('course.modules.0.items.0.discussion.post_count', 1)
            ->missing('course.modules.0.items.0.discussion.posts')
        );
    }
}
