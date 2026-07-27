<?php

namespace Tests\Feature\ClassRecord;

use App\Models\ClassRecord\ClassRecord;
use App\Models\ClassRecord\ClassRecordAssessment;
use App\Models\ClassRecord\ClassRecordQuarter;
use App\Models\ClassRecord\ClassRecordScore;
use App\Models\ClassRecord\ClassRecordStudent;
use App\Models\ClassRecord\GradingCategory;
use App\Models\ClassRecord\GradingOption;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression coverage for the Setup tab's assessment "X" delete button.
 *
 * Before this fix, ClassRecordAssessmentController::upsert() only ever
 * inserted/updated rows present in the payload — a removed row silently
 * survived in the database and reappeared on the very next reload. Fixing
 * persistence also required switching identity matching from
 * (category, number) position to the stable row id, since position shifts
 * on every removal and would otherwise silently reassign one row's scores
 * to whatever content lands on its old slot.
 */
class ClassRecordAssessmentControllerTest extends TestCase
{
    use RefreshDatabase;

    private SchoolYear $sy;
    private GradingOption $option;
    private GradingCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sy = SchoolYear::create([
            'name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30',
            'is_current' => true, 'status' => 'active',
        ]);
        $this->option = GradingOption::create(['name' => 'Default', 'is_active' => true]);
        $this->category = GradingCategory::create([
            'grading_option_id' => $this->option->id, 'name' => 'Formative', 'code' => 'FA',
            'weight' => 1.0, 'max_assessments' => 10, 'sort_order' => 1,
        ]);
    }

    private function makeSection(): Section
    {
        return Section::create([
            'levelid' => 8, 'sectionname' => 'Emerald', 'syid' => $this->sy->id,
            'school_year_id' => $this->sy->id, 'is_active' => true,
        ]);
    }

    private function makeSubject(): Subject
    {
        return Subject::create([
            'school_year_id' => $this->sy->id, 'code' => 'SUBJ1', 'name' => 'Subject 1',
            'credit_units' => 3, 'lecture_hours' => 3, 'load_units' => 3, 'subject_type' => 'lecture',
            'grade_level' => 8, 'sessions_per_week' => 5, 'minutes_per_session' => 60, 'is_active' => true,
        ]);
    }

    private function makeRecordAndQuarter(User $teacher, Section $section, Subject $subject): ClassRecordQuarter
    {
        $record = ClassRecord::create([
            'subject_id' => $subject->id, 'section_id' => $section->id,
            'grading_option_id' => $this->option->id, 'school_year_id' => $this->sy->id,
            'school_year' => $this->sy->name, 'subject_name' => $subject->name,
            'year_level_section' => "G-{$section->levelid} {$section->sectionname}",
            'teacher_id' => $teacher->id, 'status' => 'draft',
        ]);

        return ClassRecordQuarter::create([
            'class_record_id' => $record->id, 'grading_option_id' => $this->option->id, 'quarter' => 1,
        ]);
    }

    private function makeAssessment(ClassRecordQuarter $quarter, int $number, string $title): ClassRecordAssessment
    {
        return ClassRecordAssessment::create([
            'class_record_quarter_id' => $quarter->id, 'grading_category_id' => $this->category->id,
            'assessment_number' => $number, 'title' => $title, 'assessment_type' => 'formative',
            'is_graded' => true, 'is_major' => false, 'max_score' => 20, 'sort_order' => $number,
        ]);
    }

    private function payloadFor(ClassRecordAssessment $a): array
    {
        return [
            'id' => $a->id, 'grading_category_id' => $a->grading_category_id,
            'assessment_number' => $a->assessment_number, 'title' => $a->title,
            'is_graded' => true, 'activity_date' => null, 'max_score' => $a->max_score,
        ];
    }

    public function test_removing_an_assessment_from_the_payload_deletes_it(): void
    {
        $teacher = User::factory()->create();
        $quarter = $this->makeRecordAndQuarter($teacher, $this->makeSection(), $this->makeSubject());
        $keep = $this->makeAssessment($quarter, 1, 'Quiz 1');
        $remove = $this->makeAssessment($quarter, 2, 'Quiz 2');

        $this->actingAs($teacher)
            ->postJson(route('class-records.assessments.upsert', ['classRecord' => $quarter->class_record_id, 'q' => 1]), [
                'assessments' => [$this->payloadFor($keep)],
            ])
            ->assertOk();

        $this->assertDatabaseHas('class_record_assessments', ['id' => $keep->id]);
        $this->assertDatabaseMissing('class_record_assessments', ['id' => $remove->id]);
    }

    public function test_removing_an_assessment_with_scores_is_blocked_and_nothing_is_deleted(): void
    {
        $teacher = User::factory()->create();
        $quarter = $this->makeRecordAndQuarter($teacher, $this->makeSection(), $this->makeSubject());
        $keep = $this->makeAssessment($quarter, 1, 'Quiz 1');
        $remove = $this->makeAssessment($quarter, 2, 'Quiz 2');

        $student = ClassRecordStudent::create([
            'class_record_quarter_id' => $quarter->id, 'family_name' => 'Dela Cruz', 'given_name' => 'Juan',
            'sex' => 'M', 'sequence_number' => 1, 'is_active' => true,
        ]);
        ClassRecordScore::create([
            'class_record_student_id' => $student->id, 'class_record_assessment_id' => $remove->id, 'score' => 18,
        ]);

        $this->actingAs($teacher)
            ->postJson(route('class-records.assessments.upsert', ['classRecord' => $quarter->class_record_id, 'q' => 1]), [
                'assessments' => [$this->payloadFor($keep)],
            ])
            ->assertStatus(422);

        $this->assertDatabaseHas('class_record_assessments', ['id' => $keep->id]);
        $this->assertDatabaseHas('class_record_assessments', ['id' => $remove->id]);
    }

    public function test_deleting_a_middle_row_and_renumbering_does_not_corrupt_a_surviving_rows_content_or_scores(): void
    {
        $teacher = User::factory()->create();
        $quarter = $this->makeRecordAndQuarter($teacher, $this->makeSection(), $this->makeSubject());
        $a = $this->makeAssessment($quarter, 1, 'Quiz 1');
        $b = $this->makeAssessment($quarter, 2, 'Quiz 2'); // will be removed — no scores
        $c = $this->makeAssessment($quarter, 3, 'Quiz 3'); // has scores, renumbers down to slot 2

        $student = ClassRecordStudent::create([
            'class_record_quarter_id' => $quarter->id, 'family_name' => 'Dela Cruz', 'given_name' => 'Juan',
            'sex' => 'M', 'sequence_number' => 1, 'is_active' => true,
        ]);
        ClassRecordScore::create([
            'class_record_student_id' => $student->id, 'class_record_assessment_id' => $c->id, 'score' => 15,
        ]);

        // Simulate the frontend: B removed, C renumbered from 3 down to 2 — but C's id is preserved.
        $cPayload = $this->payloadFor($c);
        $cPayload['assessment_number'] = 2;

        $this->actingAs($teacher)
            ->postJson(route('class-records.assessments.upsert', ['classRecord' => $quarter->class_record_id, 'q' => 1]), [
                'assessments' => [$this->payloadFor($a), $cPayload],
            ])
            ->assertOk();

        $this->assertDatabaseMissing('class_record_assessments', ['id' => $b->id]);

        $c->refresh();
        $this->assertSame(2, $c->assessment_number);
        $this->assertSame('Quiz 3', $c->title, 'The row that had scores must keep its own title, not adopt slot 2\'s old content.');

        // The score must still point at C's original assessment id.
        $this->assertDatabaseHas('class_record_scores', [
            'class_record_assessment_id' => $c->id,
            'class_record_student_id'    => $student->id,
        ]);
    }
}
