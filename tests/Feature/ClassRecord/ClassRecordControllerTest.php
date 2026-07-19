<?php

namespace Tests\Feature\ClassRecord;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\FacultyLoad;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers ClassRecordController::myTeachingLoad() — the "Select a teaching
 * assignment" list a faculty sees when creating a class record. A faculty
 * can legitimately hold the same subject+section across both terms of one
 * school year (load_assignments' uniqueness is scoped per term), but
 * ClassRecord itself has no term concept, so the endpoint must collapse
 * those into one card rather than showing an apparent duplicate.
 */
class ClassRecordControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_my_teaching_load_collapses_the_same_subject_and_section_held_across_two_terms(): void
    {
        $sy = SchoolYear::create([
            'name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30',
            'is_current' => true, 'status' => 'active',
        ]);
        $term1 = AcademicTerm::create([
            'school_year_id' => $sy->id, 'name' => '1st Semester', 'term_type' => '1st_semester',
            'start_date' => '2025-08-01', 'end_date' => '2025-12-31', 'is_current' => true,
        ]);
        $term2 = AcademicTerm::create([
            'school_year_id' => $sy->id, 'name' => '2nd Semester', 'term_type' => '2nd_semester',
            'start_date' => '2026-01-01', 'end_date' => '2026-05-31', 'is_current' => false,
        ]);
        $subject = Subject::create([
            'school_year_id' => $sy->id, 'code' => 'MATH6L1', 'name' => 'Mathematics 6 Level 1',
            'credit_units' => 3, 'lecture_hours' => 3, 'load_units' => 3, 'subject_type' => 'lecture',
            'grade_level' => 6, 'sessions_per_week' => 5, 'minutes_per_session' => 60, 'is_active' => true,
        ]);
        $section = Section::create([
            'levelid' => 6, 'sectionname' => 'Del Mundo', 'syid' => $sy->id, 'school_year_id' => $sy->id, 'is_active' => true,
        ]);
        $faculty = User::factory()->create(['email_verified_at' => now()]);

        foreach ([$term1, $term2] as $i => $term) {
            $load = FacultyLoad::create([
                'user_id' => $faculty->id, 'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
                'teaching_units' => 0, 'total_units' => 0, 'full_load_threshold' => 18, 'load_status' => 'underload',
            ]);
            LoadAssignment::create([
                'faculty_load_id' => $load->id, 'user_id' => $faculty->id,
                'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
                'assignment_type' => 'teaching', 'subject_id' => $subject->id, 'section_id' => $section->id,
                'load_units' => $i === 0 ? 2 : 1,
            ]);
        }

        $response = $this->actingAs($faculty)->getJson(route('class-records.my-teaching-load'));

        $response->assertOk();
        $assignments = $response->json('assignments');
        $this->assertCount(1, $assignments);
        $this->assertSame('Mathematics 6 Level 1', $assignments[0]['subject_name']);
        $this->assertSame('Del Mundo', $assignments[0]['section_name']);
    }

    public function test_my_teaching_load_still_lists_distinct_subjects_separately(): void
    {
        $sy = SchoolYear::create([
            'name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30',
            'is_current' => true, 'status' => 'active',
        ]);
        $term = AcademicTerm::create([
            'school_year_id' => $sy->id, 'name' => '1st Semester', 'term_type' => '1st_semester',
            'start_date' => '2025-08-01', 'end_date' => '2025-12-31', 'is_current' => true,
        ]);
        $subjectA = Subject::create([
            'school_year_id' => $sy->id, 'code' => 'MATH6L1', 'name' => 'Mathematics 6 Level 1',
            'credit_units' => 3, 'lecture_hours' => 3, 'load_units' => 3, 'subject_type' => 'lecture',
            'grade_level' => 6, 'sessions_per_week' => 5, 'minutes_per_session' => 60, 'is_active' => true,
        ]);
        $subjectB = Subject::create([
            'school_year_id' => $sy->id, 'code' => 'SCI6', 'name' => 'Science 6',
            'credit_units' => 3, 'lecture_hours' => 3, 'load_units' => 3, 'subject_type' => 'lecture',
            'grade_level' => 6, 'sessions_per_week' => 5, 'minutes_per_session' => 60, 'is_active' => true,
        ]);
        $section = Section::create([
            'levelid' => 6, 'sectionname' => 'Del Mundo', 'syid' => $sy->id, 'school_year_id' => $sy->id, 'is_active' => true,
        ]);
        $faculty = User::factory()->create(['email_verified_at' => now()]);
        $load = FacultyLoad::create([
            'user_id' => $faculty->id, 'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
            'teaching_units' => 0, 'total_units' => 0, 'full_load_threshold' => 18, 'load_status' => 'underload',
        ]);
        foreach ([$subjectA, $subjectB] as $subject) {
            LoadAssignment::create([
                'faculty_load_id' => $load->id, 'user_id' => $faculty->id,
                'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
                'assignment_type' => 'teaching', 'subject_id' => $subject->id, 'section_id' => $section->id,
                'load_units' => 3,
            ]);
        }

        $response = $this->actingAs($faculty)->getJson(route('class-records.my-teaching-load'));

        $response->assertOk();
        $this->assertCount(2, $response->json('assignments'));
    }
}
