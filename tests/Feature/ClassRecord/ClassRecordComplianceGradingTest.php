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
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * End-to-end coverage for compliance-mode grading (e.g. Values Education):
 * a grading option created with grading_mode=compliance, checkbox score
 * entry, quarter-level compliance %/remark via the grades() endpoint, and
 * the school-year-end remark via the final-grades() endpoint under both
 * configured SY rules.
 */
class ClassRecordComplianceGradingTest extends TestCase
{
    use RefreshDatabase;

    private SchoolYear $sy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sy = SchoolYear::create([
            'name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30',
            'is_current' => true, 'status' => 'active',
        ]);
    }

    private function makeComplianceOption(float $passThreshold = 0.75, string $syRule = 'all_quarters_pass'): GradingOption
    {
        return GradingOption::create([
            'name' => 'Values Education Grading',
            'is_active' => true,
            'grading_mode' => 'compliance',
            'compliance_pass_threshold' => $passThreshold,
            'compliance_sy_rule' => $syRule,
            'compliance_pass_label' => 'Completed',
            'compliance_fail_label' => 'Not Completed',
        ]);
    }

    private function makeRecord(User $teacher, GradingOption $option): ClassRecord
    {
        return ClassRecord::create([
            'grading_option_id' => $option->id,
            'school_year_id' => $this->sy->id,
            'school_year' => $this->sy->name,
            'subject_name' => 'Values Education 1',
            'year_level_section' => 'G-7 Diamond',
            'teacher_id' => $teacher->id,
            'status' => 'draft',
        ]);
    }

    private function makeQuarter(ClassRecord $record, int $q, GradingOption $option): ClassRecordQuarter
    {
        return ClassRecordQuarter::create([
            'class_record_id' => $record->id, 'grading_option_id' => $option->id,
            'quarter' => $q, 'is_locked' => false,
        ]);
    }

    // ── Creating a compliance grading option via the API ────────────────────

    public function test_admin_can_create_a_compliance_grading_option(): void
    {
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->postJson(route('grading-options.store'), [
            'name' => 'Values Education Grading',
            'is_active' => true,
            'grading_mode' => 'compliance',
            'compliance_pass_threshold' => 0.75,
            'compliance_sy_rule' => 'all_quarters_pass',
            'compliance_pass_label' => 'Completed',
            'compliance_fail_label' => 'Not Completed',
            'categories' => [
                ['name' => 'Activities', 'code' => 'ACT', 'weight' => 1, 'max_assessments' => 5, 'sort_order' => 1],
            ],
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('grading_options', [
            'name' => 'Values Education Grading',
            'grading_mode' => 'compliance',
            'compliance_sy_rule' => 'all_quarters_pass',
            'compliance_pass_label' => 'Completed',
            'compliance_fail_label' => 'Not Completed',
        ]);
    }

    public function test_creating_a_compliance_option_without_a_threshold_is_rejected(): void
    {
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->postJson(route('grading-options.store'), [
            'name' => 'Values Education Grading',
            'is_active' => true,
            'grading_mode' => 'compliance',
            'categories' => [
                ['name' => 'Activities', 'code' => 'ACT', 'weight' => 1, 'max_assessments' => 5, 'sort_order' => 1],
            ],
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('grading_options', ['name' => 'Values Education Grading']);
    }

    public function test_grading_mode_cannot_be_changed_once_assessments_exist(): void
    {
        $admin = $this->adminUser();
        $option = $this->makeComplianceOption();
        $category = GradingCategory::create([
            'grading_option_id' => $option->id, 'subject_id' => null,
            'name' => 'Activities', 'code' => 'ACT', 'weight' => 1.0, 'max_assessments' => 5, 'sort_order' => 1,
        ]);
        $record = $this->makeRecord($admin, $option);
        $quarter = $this->makeQuarter($record, 1, $option);
        ClassRecordAssessment::create([
            'class_record_quarter_id' => $quarter->id, 'grading_category_id' => $category->id,
            'assessment_number' => 1, 'title' => 'Activity 1', 'assessment_type' => 'formative',
            'is_graded' => true, 'is_major' => false, 'max_score' => 1, 'sort_order' => 1,
        ]);

        $response = $this->actingAs($admin)->putJson(route('grading-options.update', $option->id), [
            'name' => $option->name,
            'grading_mode' => 'numeric',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseHas('grading_options', ['id' => $option->id, 'grading_mode' => 'compliance']);
    }

    // ── Checkbox score entry + quarter compliance % ──────────────────────────

    public function test_checkbox_score_entry_computes_correct_quarter_compliance_percentage(): void
    {
        $teacher = User::factory()->create();
        $option = $this->makeComplianceOption(passThreshold: 0.75);
        $category = GradingCategory::create([
            'grading_option_id' => $option->id, 'subject_id' => null,
            'name' => 'Activities', 'code' => 'ACT', 'weight' => 1.0, 'max_assessments' => 4, 'sort_order' => 1,
        ]);
        $record = $this->makeRecord($teacher, $option);
        $quarter = $this->makeQuarter($record, 1, $option);
        $student = ClassRecordStudent::create([
            'class_record_quarter_id' => $quarter->id, 'family_name' => 'Doe', 'given_name' => 'Jane',
            'sequence_number' => 1, 'is_active' => true,
        ]);

        $assessments = [];
        for ($i = 1; $i <= 4; $i++) {
            $assessments[] = ClassRecordAssessment::create([
                'class_record_quarter_id' => $quarter->id, 'grading_category_id' => $category->id,
                'assessment_number' => $i, 'title' => "Activity {$i}", 'assessment_type' => 'formative',
                'is_graded' => true, 'is_major' => false, 'max_score' => 1, 'sort_order' => $i,
            ]);
        }
        $nonGraded = ClassRecordAssessment::create([
            'class_record_quarter_id' => $quarter->id, 'grading_category_id' => $category->id,
            'assessment_number' => 5, 'title' => 'Unscored Reflection', 'assessment_type' => 'formative',
            'is_graded' => false, 'is_major' => false, 'max_score' => 0, 'sort_order' => 5,
        ]);
        // Simulate stale legacy data from before non-graded score writes were
        // blocked. It must not affect the 3-of-4 compliance calculation.
        ClassRecordScore::create([
            'class_record_student_id' => $student->id,
            'class_record_assessment_id' => $nonGraded->id,
            'score' => 0,
        ]);

        // Complied with 3 of 4 (checkbox checked = score equals max_score = 1).
        $this->actingAs($teacher)
            ->postJson(route('class-records.scores.upsert', ['classRecord' => $record->id, 'q' => 1]), [
                'scores' => [
                    ['student_id' => $student->id, 'assessment_id' => $assessments[0]->id, 'score' => 1],
                    ['student_id' => $student->id, 'assessment_id' => $assessments[1]->id, 'score' => 1],
                    ['student_id' => $student->id, 'assessment_id' => $assessments[2]->id, 'score' => 1],
                    ['student_id' => $student->id, 'assessment_id' => $assessments[3]->id, 'score' => 0],
                ],
            ])
            ->assertOk();

        $response = $this->actingAs($teacher)
            ->getJson(route('class-records.quarters.grades', ['classRecord' => $record->id, 'q' => 1]));

        $response->assertOk();
        $response->assertJson(['gradingMode' => 'compliance']);
        $row = collect($response->json('students'))->firstWhere('studentId', $student->id);
        $this->assertEquals(75.0, $row['compliancePercentage']);
        $this->assertTrue($row['passed']);
        $this->assertEquals('Completed', $row['remark']);
    }

    public function test_quarter_compliance_below_threshold_shows_fail_remark(): void
    {
        $teacher = User::factory()->create();
        $option = $this->makeComplianceOption(passThreshold: 0.75);
        $category = GradingCategory::create([
            'grading_option_id' => $option->id, 'subject_id' => null,
            'name' => 'Activities', 'code' => 'ACT', 'weight' => 1.0, 'max_assessments' => 4, 'sort_order' => 1,
        ]);
        $record = $this->makeRecord($teacher, $option);
        $quarter = $this->makeQuarter($record, 1, $option);
        $student = ClassRecordStudent::create([
            'class_record_quarter_id' => $quarter->id, 'family_name' => 'Doe', 'given_name' => 'Jane',
            'sequence_number' => 1, 'is_active' => true,
        ]);
        $assessment = ClassRecordAssessment::create([
            'class_record_quarter_id' => $quarter->id, 'grading_category_id' => $category->id,
            'assessment_number' => 1, 'title' => 'Activity 1', 'assessment_type' => 'formative',
            'is_graded' => true, 'is_major' => false, 'max_score' => 1, 'sort_order' => 1,
        ]);

        ClassRecordScore::create([
            'class_record_student_id' => $student->id, 'class_record_assessment_id' => $assessment->id, 'score' => 0,
        ]);

        $response = $this->actingAs($teacher)
            ->getJson(route('class-records.quarters.grades', ['classRecord' => $record->id, 'q' => 1]));

        $row = collect($response->json('students'))->firstWhere('studentId', $student->id);
        $this->assertEquals(0.0, $row['compliancePercentage']);
        $this->assertFalse($row['passed']);
        $this->assertEquals('Not Completed', $row['remark']);
    }

    // ── School-year-end remark ────────────────────────────────────────────────

    public function test_school_year_remark_completed_when_all_quarters_pass(): void
    {
        $teacher = User::factory()->create();
        $option = $this->makeComplianceOption(passThreshold: 0.75, syRule: 'all_quarters_pass');
        $category = GradingCategory::create([
            'grading_option_id' => $option->id, 'subject_id' => null,
            'name' => 'Activities', 'code' => 'ACT', 'weight' => 1.0, 'max_assessments' => 1, 'sort_order' => 1,
        ]);
        $record = $this->makeRecord($teacher, $option);

        // All 4 quarters: student complies (score = max_score = 1) -> 100% each quarter.
        for ($q = 1; $q <= 4; $q++) {
            $quarter = $this->makeQuarter($record, $q, $option);
            $student = ClassRecordStudent::create([
                'class_record_quarter_id' => $quarter->id, 'student_id' => 501,
                'family_name' => 'Doe', 'given_name' => 'Jane', 'sequence_number' => 1, 'is_active' => true,
            ]);
            $assessment = ClassRecordAssessment::create([
                'class_record_quarter_id' => $quarter->id, 'grading_category_id' => $category->id,
                'assessment_number' => 1, 'title' => 'Activity 1', 'assessment_type' => 'formative',
                'is_graded' => true, 'is_major' => false, 'max_score' => 1, 'sort_order' => 1,
            ]);
            ClassRecordScore::create([
                'class_record_student_id' => $student->id, 'class_record_assessment_id' => $assessment->id, 'score' => 1,
            ]);
        }

        $response = $this->actingAs($teacher)
            ->getJson(route('class-records.final-grades', $record->id));

        $response->assertOk();
        $response->assertJson(['gradingMode' => 'compliance']);
        $row = $response->json('students.0');
        $this->assertEquals(100.0, $row['q1Compliance']);
        $this->assertEquals(100.0, $row['q4Compliance']);
        $this->assertEquals(100.0, $row['averageCompliance']);
        $this->assertEquals('Completed', $row['remark']);
    }

    public function test_school_year_remark_not_completed_when_one_quarter_fails_under_all_quarters_pass_rule(): void
    {
        $teacher = User::factory()->create();
        $option = $this->makeComplianceOption(passThreshold: 0.75, syRule: 'all_quarters_pass');
        $category = GradingCategory::create([
            'grading_option_id' => $option->id, 'subject_id' => null,
            'name' => 'Activities', 'code' => 'ACT', 'weight' => 1.0, 'max_assessments' => 1, 'sort_order' => 1,
        ]);
        $record = $this->makeRecord($teacher, $option);

        for ($q = 1; $q <= 4; $q++) {
            $quarter = $this->makeQuarter($record, $q, $option);
            $student = ClassRecordStudent::create([
                'class_record_quarter_id' => $quarter->id, 'student_id' => 502,
                'family_name' => 'Doe', 'given_name' => 'Jane', 'sequence_number' => 1, 'is_active' => true,
            ]);
            $assessment = ClassRecordAssessment::create([
                'class_record_quarter_id' => $quarter->id, 'grading_category_id' => $category->id,
                'assessment_number' => 1, 'title' => 'Activity 1', 'assessment_type' => 'formative',
                'is_graded' => true, 'is_major' => false, 'max_score' => 1, 'sort_order' => 1,
            ]);
            // Q3 (the 3rd iteration) fails to comply — every other quarter passes.
            $score = $q === 3 ? 0 : 1;
            ClassRecordScore::create([
                'class_record_student_id' => $student->id, 'class_record_assessment_id' => $assessment->id, 'score' => $score,
            ]);
        }

        $response = $this->actingAs($teacher)->getJson(route('class-records.final-grades', $record->id));

        $row = $response->json('students.0');
        $this->assertEquals(0.0, $row['q3Compliance']);
        $this->assertEquals('Not Completed', $row['remark']);
    }

    public function test_school_year_remark_uses_average_threshold_rule_when_configured(): void
    {
        $teacher = User::factory()->create();
        // average_threshold: one weak quarter is tolerated as long as the average clears the bar.
        $option = $this->makeComplianceOption(passThreshold: 0.75, syRule: 'average_threshold');
        $category = GradingCategory::create([
            'grading_option_id' => $option->id, 'subject_id' => null,
            'name' => 'Activities', 'code' => 'ACT', 'weight' => 1.0, 'max_assessments' => 1, 'sort_order' => 1,
        ]);
        $record = $this->makeRecord($teacher, $option);

        // Q1,Q2,Q4 fully comply (100%); Q3 partially complies at 50% via 2 assessments.
        for ($q = 1; $q <= 4; $q++) {
            $quarter = $this->makeQuarter($record, $q, $option);
            $student = ClassRecordStudent::create([
                'class_record_quarter_id' => $quarter->id, 'student_id' => 503,
                'family_name' => 'Doe', 'given_name' => 'Jane', 'sequence_number' => 1, 'is_active' => true,
            ]);

            if ($q === 3) {
                $a1 = ClassRecordAssessment::create([
                    'class_record_quarter_id' => $quarter->id, 'grading_category_id' => $category->id,
                    'assessment_number' => 1, 'title' => 'Activity 1', 'assessment_type' => 'formative',
                    'is_graded' => true, 'is_major' => false, 'max_score' => 1, 'sort_order' => 1,
                ]);
                $a2 = ClassRecordAssessment::create([
                    'class_record_quarter_id' => $quarter->id, 'grading_category_id' => $category->id,
                    'assessment_number' => 2, 'title' => 'Activity 2', 'assessment_type' => 'formative',
                    'is_graded' => true, 'is_major' => false, 'max_score' => 1, 'sort_order' => 2,
                ]);
                ClassRecordScore::create(['class_record_student_id' => $student->id, 'class_record_assessment_id' => $a1->id, 'score' => 1]);
                ClassRecordScore::create(['class_record_student_id' => $student->id, 'class_record_assessment_id' => $a2->id, 'score' => 0]);
            } else {
                $a = ClassRecordAssessment::create([
                    'class_record_quarter_id' => $quarter->id, 'grading_category_id' => $category->id,
                    'assessment_number' => 1, 'title' => 'Activity 1', 'assessment_type' => 'formative',
                    'is_graded' => true, 'is_major' => false, 'max_score' => 1, 'sort_order' => 1,
                ]);
                ClassRecordScore::create(['class_record_student_id' => $student->id, 'class_record_assessment_id' => $a->id, 'score' => 1]);
            }
        }

        $response = $this->actingAs($teacher)->getJson(route('class-records.final-grades', $record->id));

        $row = $response->json('students.0');
        $this->assertEquals(50.0, $row['q3Compliance']);
        // Average of (100, 100, 50, 100) / 4 = 87.5% — above the 75% threshold.
        $this->assertEqualsWithDelta(87.5, $row['averageCompliance'], 0.01);
        $this->assertEquals('Completed', $row['remark']);
    }

    // ── Backward compatibility: numeric options are unaffected ─────────────────

    public function test_numeric_grading_option_still_returns_numeric_shape(): void
    {
        $teacher = User::factory()->create();
        $option = GradingOption::create(['name' => 'Standard Numeric', 'is_active' => true]);
        $category = GradingCategory::create([
            'grading_option_id' => $option->id, 'name' => 'Written Works', 'code' => 'WW',
            'weight' => 1.0, 'max_assessments' => 5, 'sort_order' => 1,
        ]);
        $record = $this->makeRecord($teacher, $option);
        $quarter = $this->makeQuarter($record, 1, $option);
        $student = ClassRecordStudent::create([
            'class_record_quarter_id' => $quarter->id, 'family_name' => 'Doe', 'given_name' => 'Jane',
            'sequence_number' => 1, 'is_active' => true,
        ]);
        $graded = ClassRecordAssessment::create([
            'class_record_quarter_id' => $quarter->id, 'grading_category_id' => $category->id,
            'assessment_number' => 1, 'title' => 'Quiz 1', 'assessment_type' => 'formative',
            'is_graded' => true, 'is_major' => false, 'max_score' => 20, 'sort_order' => 1,
        ]);
        $nonGraded = ClassRecordAssessment::create([
            'class_record_quarter_id' => $quarter->id, 'grading_category_id' => $category->id,
            'assessment_number' => 2, 'title' => 'Practice Only', 'assessment_type' => 'formative',
            'is_graded' => false, 'is_major' => false, 'max_score' => 100, 'sort_order' => 2,
        ]);
        ClassRecordScore::create([
            'class_record_student_id' => $student->id,
            'class_record_assessment_id' => $graded->id,
            'score' => 20,
        ]);
        ClassRecordScore::create([
            'class_record_student_id' => $student->id,
            'class_record_assessment_id' => $nonGraded->id,
            'score' => 0,
        ]);

        $response = $this->actingAs($teacher)
            ->getJson(route('class-records.quarters.grades', ['classRecord' => $record->id, 'q' => 1]));

        $response->assertOk();
        $response->assertJson(['gradingMode' => 'numeric']);
        $this->assertEquals(100.0, $response->json('students.0.percentageScore'));
        $this->assertArrayHasKey('gradeEquivalent', $response->json('students.0'));
        $this->assertArrayNotHasKey('compliancePercentage', $response->json('students.0'));
    }

    private function adminUser(): User
    {
        $permission = \App\Models\Permission::firstOrCreate(
            ['name' => 'class-records.admin'],
            ['module' => 'Class Records', 'description' => 'Admin'],
        );
        $role = \App\Models\Role::create(['name' => 'ClassRecordAdmin_'.uniqid()]);
        $role->permissions()->attach($permission->id);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        return $user->fresh();
    }
}
