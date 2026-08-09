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
use App\Models\Learn\Course;
use App\Models\Learn\Discussion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiscussionClassRecordPushTest extends TestCase
{
    use RefreshDatabase;

    public function test_link_and_push_work_for_a_discussion_and_never_touch_wat_scheduling_fields(): void
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
        $teacher = User::factory()->create();
        $facultyLoad = FacultyLoad::create([
            'user_id' => $teacher->id, 'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
        ]);
        LoadAssignment::create([
            'faculty_load_id' => $facultyLoad->id, 'user_id' => $teacher->id,
            'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
            'assignment_type' => 'teaching', 'subject_id' => $subject->id,
            'section_id' => $section->id, 'load_units' => 3,
        ]);
        $course = Course::create([
            'subject_id' => $subject->id, 'section_id' => $section->id,
            'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
        ]);
        $module = $course->modules()->create(['title' => 'Week 1', 'position' => 0]);
        $discussion = Discussion::create(['title' => 'D', 'prompt' => 'P', 'points_possible' => 20]);
        $discussion->moduleItem()->create(['learn_module_id' => $module->id, 'position' => 0]);

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
        $plottedAt = now()->subDays(3);
        $activityDate = now()->subDays(2)->toDateString();
        $assessment = ClassRecordAssessment::create([
            'class_record_quarter_id' => $quarter->id, 'grading_category_id' => $category->id,
            'assessment_type' => 'formative', 'is_graded' => true, 'is_major' => false,
            'assessment_number' => 1, 'title' => 'Discussion 1', 'max_score' => 20, 'sort_order' => 1,
            'plotted_at' => $plottedAt, 'activity_date' => $activityDate,
        ]);

        $studentId = mt_rand(1, 999999999);
        \Illuminate\Support\Facades\DB::table('students')->insert([
            'id' => $studentId, 'pisaysystemID' => "PS{$studentId}", 'firstname' => 'Test', 'lastname' => 'Student',
        ]);
        ClassRecordStudent::create([
            'class_record_quarter_id' => $quarter->id, 'student_id' => $studentId,
            'family_name' => 'Student', 'given_name' => 'Test', 'sex' => 'M', 'sequence_number' => 1,
        ]);
        $discussion->grades()->create(['student_id' => $studentId, 'points_earned' => 18, 'graded_at' => now()]);

        $this->actingAs($teacher)->put(route('learn.discussions.link', $discussion), [
            'class_record_assessment_id' => $assessment->id,
        ])->assertRedirect();
        $this->assertSame($assessment->id, $discussion->fresh()->class_record_assessment_id);

        $this->actingAs($teacher)->post(route('learn.discussions.push', $discussion))->assertRedirect();

        $this->assertDatabaseHas('class_record_scores', [
            'class_record_student_id' => ClassRecordStudent::where('student_id', $studentId)->first()->id,
            'class_record_assessment_id' => $assessment->id,
            'score' => 18,
        ]);
        $this->assertNotNull($discussion->fresh()->pushed_at);

        // WAT invariant: linking/pushing never touches the assessment's own scheduling fields.
        $assessment->refresh();
        $this->assertSame($plottedAt->toDateTimeString(), $assessment->plotted_at->toDateTimeString());
        $this->assertSame($activityDate, $assessment->activity_date->toDateString());
    }
}
