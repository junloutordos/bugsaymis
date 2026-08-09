<?php

namespace Tests\Feature\Learn;

use App\Models\ClassRecord\ClassRecord;
use App\Models\ClassRecord\ClassRecordAssessment;
use App\Models\ClassRecord\ClassRecordQuarter;
use App\Models\ClassRecord\GradingCategory;
use App\Models\ClassRecord\GradingOption;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\Learn\Assignment;
use App\Models\Learn\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssignmentClassRecordRelationTest extends TestCase
{
    use RefreshDatabase;

    public function test_assignment_resolves_its_linked_class_record_assessment(): void
    {
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
        $course = Course::create([
            'subject_id' => $subject->id, 'section_id' => $section->id,
            'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
        ]);
        $module = $course->modules()->create(['title' => 'Week 1', 'position' => 0]);
        $assignment = Assignment::create(['title' => 'Essay', 'submission_type' => 'text', 'points_possible' => 40]);
        $assignment->moduleItem()->create(['learn_module_id' => $module->id, 'position' => 0]);

        $teacher = User::factory()->create();
        $option = GradingOption::create(['name' => 'Standard', 'is_active' => true]);
        $classRecord = ClassRecord::create([
            'subject_id' => $subject->id, 'section_id' => $section->id,
            'grading_option_id' => $option->id, 'school_year_id' => $sy->id,
            'school_year' => $sy->name, 'subject_name' => $subject->name,
            'year_level_section' => "G-{$section->levelid} {$section->sectionname}",
            'teacher_id' => $teacher->id, 'status' => 'draft',
        ]);
        $quarter = ClassRecordQuarter::create([
            'class_record_id' => $classRecord->id, 'grading_option_id' => $option->id,
            'quarter' => 1, 'is_locked' => false,
        ]);
        $category = GradingCategory::create([
            'grading_option_id' => $option->id, 'name' => 'Written Work', 'code' => 'WW',
            'weight' => 0.5, 'max_assessments' => 5, 'sort_order' => 1,
        ]);
        $assessment = ClassRecordAssessment::create([
            'class_record_quarter_id' => $quarter->id, 'grading_category_id' => $category->id,
            'assessment_type' => 'formative', 'is_graded' => true, 'is_major' => false,
            'assessment_number' => 1, 'title' => 'Essay 1', 'max_score' => 40, 'sort_order' => 1,
        ]);

        $assignment->update(['class_record_assessment_id' => $assessment->id, 'pushed_at' => now()]);

        $this->assertTrue($assignment->fresh()->classRecordAssessment->is($assessment));
        $this->assertNotNull($assignment->fresh()->pushed_at);
    }
}
