<?php

namespace Tests\Feature\ClassRecord;

use App\Models\ClassRecord\ClassRecord;
use App\Models\ClassRecord\ClassRecordAssessment;
use App\Models\ClassRecord\ClassRecordQuarter;
use App\Models\ClassRecord\GradingCategory;
use App\Models\ClassRecord\GradingOption;
use App\Models\ClassRecord\QuarterExamWindow;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\ClassRecord\WatRuleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuarterExamWindowTest extends TestCase
{
    use RefreshDatabase;

    private SchoolYear $sy;
    private Section $section;
    private GradingOption $option;
    private GradingCategory $qeCategory;
    private GradingCategory $faCategory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sy = SchoolYear::create([
            'name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30',
            'is_current' => true, 'status' => 'active',
        ]);
        $this->section = Section::create(['levelid' => 7, 'sectionname' => 'Diamond']);
        $this->option = GradingOption::create(['name' => 'Opt '.uniqid(), 'is_active' => true]);
        $this->qeCategory = GradingCategory::create([
            'grading_option_id' => $this->option->id, 'name' => 'Quarterly Exam', 'code' => 'QE',
            'weight' => 0.3, 'max_assessments' => 1, 'sort_order' => 0,
        ]);
        $this->faCategory = GradingCategory::create([
            'grading_option_id' => $this->option->id, 'name' => 'Formative', 'code' => 'FA',
            'weight' => 0.7, 'max_assessments' => 5, 'sort_order' => 1,
        ]);
    }

    private function admin(): User
    {
        $permission = Permission::firstOrCreate(
            ['name' => 'class-records.admin'],
            ['module' => 'Class Records', 'description' => 'Admin'],
        );
        $role = Role::create(['name' => 'CRAdmin_'.uniqid()]);
        $role->permissions()->attach($permission->id);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        return $user->fresh();
    }

    private function recordWithExistingAssessment(string $date, string $type): ClassRecord
    {
        $teacher = User::factory()->create();
        $record = ClassRecord::create([
            'grading_option_id' => $this->option->id,
            'school_year_id' => $this->sy->id,
            'school_year' => $this->sy->name,
            'subject_name' => 'Subject '.uniqid(),
            'year_level_section' => 'G-7 Diamond',
            'section_id' => $this->section->id,
            'teacher_id' => $teacher->id,
            'status' => 'draft',
        ]);
        $quarter = ClassRecordQuarter::create(['class_record_id' => $record->id, 'quarter' => 1, 'is_locked' => false]);
        ClassRecordAssessment::create([
            'class_record_quarter_id' => $quarter->id,
            'grading_category_id' => $type === 'long_test_1' ? $this->qeCategory->id : $this->faCategory->id,
            'assessment_type' => $type,
            'assessment_number' => 1,
            'is_graded' => true,
            'is_major' => $type === 'long_test_1',
            'activity_date' => $date,
            'title' => 'Existing',
            'max_score' => 100,
            'sort_order' => 0,
        ]);

        return $record;
    }

    private function newRecord(): array
    {
        $teacher = User::factory()->create();
        $record = ClassRecord::create([
            'grading_option_id' => $this->option->id,
            'school_year_id' => $this->sy->id,
            'school_year' => $this->sy->name,
            'subject_name' => 'New Subject',
            'year_level_section' => 'G-7 Diamond',
            'section_id' => $this->section->id,
            'teacher_id' => $teacher->id,
            'status' => 'draft',
        ]);

        return [$teacher, $record];
    }

    // ── WatRuleService unit checks ─────────────────────────────────────────────

    public function test_is_within_exam_window_matches_configured_range(): void
    {
        QuarterExamWindow::create([
            'school_year_id' => $this->sy->id, 'quarter' => 1,
            'start_date' => '2026-09-21', 'end_date' => '2026-09-23',
        ]);

        $this->assertTrue(WatRuleService::isWithinExamWindow($this->sy->id, 1, '2026-09-22'));
        $this->assertFalse(WatRuleService::isWithinExamWindow($this->sy->id, 1, '2026-09-24'));
        $this->assertFalse(WatRuleService::isWithinExamWindow($this->sy->id, 2, '2026-09-22'));
    }

    public function test_is_exam_exempt_only_applies_to_long_test_types(): void
    {
        QuarterExamWindow::create([
            'school_year_id' => $this->sy->id, 'quarter' => 1,
            'start_date' => '2026-09-21', 'end_date' => '2026-09-23',
        ]);

        $this->assertTrue(WatRuleService::isExamExempt('long_test_1', $this->sy->id, 1, '2026-09-22'));
        $this->assertFalse(WatRuleService::isExamExempt('formative', $this->sy->id, 1, '2026-09-22'));
        $this->assertFalse(WatRuleService::isExamExempt('long_test_1', $this->sy->id, 1, '2026-09-25'));
    }

    // ── Daily cap enforcement + exam-window exemption (upsert endpoint) ────────

    public function test_daily_cap_blocks_a_fourth_long_test_without_exam_window(): void
    {
        $date = '2026-09-22';
        $this->recordWithExistingAssessment($date, 'long_test_1');
        $this->recordWithExistingAssessment($date, 'long_test_1');
        $this->recordWithExistingAssessment($date, 'long_test_1');

        [$teacher, $record] = $this->newRecord();

        $response = $this->actingAs($teacher)->postJson(
            route('class-records.assessments.upsert', ['classRecord' => $record->id, 'q' => 1]),
            ['assessments' => [[
                'grading_category_id' => $this->qeCategory->id,
                'assessment_number' => 1,
                'title' => 'Quarterly Exam',
                'activity_date' => $date,
                'max_score' => 100,
            ]]],
        );

        $response->assertStatus(422);
        $this->assertStringContainsString('WAT limit', $response->json('message'));
    }

    public function test_daily_cap_is_exempt_for_long_test_inside_configured_exam_window(): void
    {
        $date = '2026-09-22';
        QuarterExamWindow::create([
            'school_year_id' => $this->sy->id, 'quarter' => 1,
            'start_date' => '2026-09-21', 'end_date' => '2026-09-23',
        ]);
        $this->recordWithExistingAssessment($date, 'long_test_1');
        $this->recordWithExistingAssessment($date, 'long_test_1');
        $this->recordWithExistingAssessment($date, 'long_test_1');

        [$teacher, $record] = $this->newRecord();

        $response = $this->actingAs($teacher)->postJson(
            route('class-records.assessments.upsert', ['classRecord' => $record->id, 'q' => 1]),
            ['assessments' => [[
                'grading_category_id' => $this->qeCategory->id,
                'assessment_number' => 1,
                'title' => 'Quarterly Exam',
                'activity_date' => $date,
                'max_score' => 100,
            ]]],
        );

        $response->assertOk();
    }

    public function test_exam_window_does_not_exempt_non_long_test_assessments(): void
    {
        $date = '2026-09-22';
        QuarterExamWindow::create([
            'school_year_id' => $this->sy->id, 'quarter' => 1,
            'start_date' => '2026-09-21', 'end_date' => '2026-09-23',
        ]);
        $this->recordWithExistingAssessment($date, 'formative');
        $this->recordWithExistingAssessment($date, 'formative');
        $this->recordWithExistingAssessment($date, 'formative');

        [$teacher, $record] = $this->newRecord();

        $response = $this->actingAs($teacher)->postJson(
            route('class-records.assessments.upsert', ['classRecord' => $record->id, 'q' => 1]),
            ['assessments' => [[
                'grading_category_id' => $this->faCategory->id,
                'assessment_number' => 2,
                'title' => 'Quiz',
                'activity_date' => $date,
                'max_score' => 10,
            ]]],
        );

        $response->assertStatus(422);
    }

    // ── QuarterExamWindowController CRUD ────────────────────────────────────────

    public function test_admin_can_upsert_and_delete_exam_window(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->putJson(route('quarter-exam-windows.upsert'), [
            'school_year_id' => $this->sy->id, 'quarter' => 1,
            'start_date' => '2026-09-21', 'end_date' => '2026-09-23',
        ])->assertOk();

        $window = QuarterExamWindow::where('school_year_id', $this->sy->id)->where('quarter', 1)->first();
        $this->assertNotNull($window);
        $this->assertSame('2026-09-21', $window->start_date->toDateString());

        // Upsert again (same quarter) updates in place, doesn't duplicate.
        $this->actingAs($admin)->putJson(route('quarter-exam-windows.upsert'), [
            'school_year_id' => $this->sy->id, 'quarter' => 1,
            'start_date' => '2026-09-22', 'end_date' => '2026-09-24',
        ])->assertOk();
        $this->assertSame(1, QuarterExamWindow::where('school_year_id', $this->sy->id)->where('quarter', 1)->count());

        $this->actingAs($admin)->deleteJson(route('quarter-exam-windows.destroy', $window->id))->assertOk();
        $this->assertNull(QuarterExamWindow::find($window->id));
    }

    public function test_non_admin_cannot_upsert_exam_window(): void
    {
        $teacher = User::factory()->create();

        $this->actingAs($teacher)->putJson(route('quarter-exam-windows.upsert'), [
            'school_year_id' => $this->sy->id, 'quarter' => 1,
            'start_date' => '2026-09-21', 'end_date' => '2026-09-23',
        ])->assertForbidden();
    }
}
