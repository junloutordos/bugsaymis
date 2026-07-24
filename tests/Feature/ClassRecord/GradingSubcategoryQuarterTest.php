<?php

namespace Tests\Feature\ClassRecord;

use App\Models\ClassRecord\ClassRecord;
use App\Models\ClassRecord\ClassRecordAssessment;
use App\Models\ClassRecord\ClassRecordQuarter;
use App\Models\ClassRecord\GradingCategory;
use App\Models\ClassRecord\GradingOption;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GradingSubcategoryQuarterTest extends TestCase
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

    private function option(array $attrs = []): GradingOption
    {
        return GradingOption::create(array_merge(['name' => 'Opt '.uniqid(), 'is_active' => true], $attrs));
    }

    private function record(User $teacher, GradingOption $option): ClassRecord
    {
        return ClassRecord::create([
            'grading_option_id' => $option->id,
            'school_year_id' => $this->sy->id,
            'school_year' => $this->sy->name,
            'subject_name' => 'Science 7',
            'year_level_section' => 'G-7 Rizal',
            'teacher_id' => $teacher->id,
            'status' => 'draft',
        ]);
    }

    // ── Model behaviour ───────────────────────────────────────────────────────

    public function test_leaf_categories_excludes_parents(): void
    {
        $option = $this->option();
        $parent = GradingCategory::create(['grading_option_id' => $option->id, 'name' => 'Written', 'code' => 'WW', 'weight' => 0.6, 'max_assessments' => 0, 'sort_order' => 0]);
        $childA = GradingCategory::create(['grading_option_id' => $option->id, 'parent_id' => $parent->id, 'name' => 'Quiz', 'code' => 'QZ', 'weight' => 0.3, 'max_assessments' => 5, 'sort_order' => 0]);
        $childB = GradingCategory::create(['grading_option_id' => $option->id, 'parent_id' => $parent->id, 'name' => 'Long Test', 'code' => 'LT', 'weight' => 0.3, 'max_assessments' => 2, 'sort_order' => 1]);
        $standalone = GradingCategory::create(['grading_option_id' => $option->id, 'name' => 'Performance', 'code' => 'PT', 'weight' => 0.4, 'max_assessments' => 3, 'sort_order' => 1]);

        $leafIds = $option->leafCategories()->pluck('id')->all();

        $this->assertContains($childA->id, $leafIds);
        $this->assertContains($childB->id, $leafIds);
        $this->assertContains($standalone->id, $leafIds);
        $this->assertNotContains($parent->id, $leafIds);
    }

    public function test_applies_to_quarter(): void
    {
        $all = $this->option(['applicable_quarters' => null]);
        $q2q3 = $this->option(['applicable_quarters' => [2, 3]]);

        $this->assertTrue($all->appliesToQuarter(1));
        $this->assertTrue($all->appliesToQuarter(4));
        $this->assertFalse($q2q3->appliesToQuarter(1));
        $this->assertTrue($q2q3->appliesToQuarter(2));
        $this->assertTrue($q2q3->appliesToQuarter(3));
        $this->assertFalse($q2q3->appliesToQuarter(4));
    }

    // ── Create option with sub-categories ──────────────────────────────────────

    public function test_admin_creates_option_with_subcategories_and_quarter_scope(): void
    {
        $admin = $this->admin();

        $payload = [
            'name' => 'SY Subcat Option',
            'is_active' => true,
            'applicable_quarters' => [1, 2],
            'categories' => [
                [
                    'name' => 'Written Works', 'code' => 'WW', 'sort_order' => 0,
                    'children' => [
                        ['name' => 'Quizzes', 'code' => 'QZ', 'weight' => 0.3, 'max_assessments' => 5, 'sort_order' => 0],
                        ['name' => 'Long Tests', 'code' => 'LT', 'weight' => 0.3, 'max_assessments' => 2, 'sort_order' => 1],
                    ],
                ],
                ['name' => 'Performance', 'code' => 'PT', 'weight' => 0.4, 'max_assessments' => 3, 'sort_order' => 1],
            ],
        ];

        $response = $this->actingAs($admin)->postJson(route('grading-options.store'), $payload);
        $response->assertCreated();

        $option = GradingOption::latest('id')->first();
        $this->assertSame([1, 2], $option->applicable_quarters);

        $parent = GradingCategory::where('grading_option_id', $option->id)->whereNull('parent_id')->where('code', 'WW')->first();
        $this->assertNotNull($parent);
        $children = GradingCategory::where('parent_id', $parent->id)->pluck('code')->all();
        $this->assertEqualsCanonicalizing(['QZ', 'LT'], $children);

        // Leaves = QZ + LT + PT, weights 0.3+0.3+0.4 = 1.0
        $this->assertCount(3, $option->leafCategories());
    }

    public function test_create_option_rejects_leaf_weights_not_summing_to_100(): void
    {
        $admin = $this->admin();

        $payload = [
            'name' => 'Bad Weights',
            'categories' => [
                ['name' => 'A', 'code' => 'A', 'weight' => 0.3, 'max_assessments' => 3, 'sort_order' => 0],
                ['name' => 'B', 'code' => 'B', 'weight' => 0.3, 'max_assessments' => 3, 'sort_order' => 1],
            ],
        ];

        $this->actingAs($admin)->postJson(route('grading-options.store'), $payload)->assertStatus(422);
    }

    public function test_update_categories_succeeds_without_option_name(): void
    {
        $admin = $this->admin();
        $option = $this->option();
        GradingCategory::create(['grading_option_id' => $option->id, 'name' => 'Old', 'code' => 'OLD', 'weight' => 1.0, 'max_assessments' => 3, 'sort_order' => 0]);

        // The edit flow posts ONLY categories to categories.update (name goes
        // to grading-options.update separately) — must not require `name`.
        $response = $this->actingAs($admin)->putJson(route('grading-options.categories.update', $option->id), [
            'categories' => [
                ['name' => 'Written', 'code' => 'WW', 'weight' => 0.6, 'max_assessments' => 5, 'sort_order' => 0],
                ['name' => 'Performance', 'code' => 'PT', 'weight' => 0.4, 'max_assessments' => 3, 'sort_order' => 1],
            ],
        ]);

        $response->assertOk();
        $this->assertEqualsCanonicalizing(
            ['WW', 'PT'],
            GradingCategory::where('grading_option_id', $option->id)->pluck('code')->all(),
        );
    }

    // ── Per-quarter grading option change ───────────────────────────────────────

    public function test_teacher_can_set_quarter_option_applicable_to_that_quarter(): void
    {
        $teacher = User::factory()->create();
        $default = $this->option(['applicable_quarters' => [1]]);
        $q2Option = $this->option(['applicable_quarters' => [2]]);
        GradingCategory::create(['grading_option_id' => $q2Option->id, 'name' => 'All', 'code' => 'AL', 'weight' => 1.0, 'max_assessments' => 5, 'sort_order' => 0]);

        $record = $this->record($teacher, $default);

        $this->actingAs($teacher)
            ->putJson(route('class-records.quarters.grading-option', ['classRecord' => $record->id, 'q' => 2]), [
                'grading_option_id' => $q2Option->id,
            ])
            ->assertOk();

        $quarter = ClassRecordQuarter::where('class_record_id', $record->id)->where('quarter', 2)->first();
        $this->assertSame($q2Option->id, $quarter->grading_option_id);
        $this->assertSame($q2Option->id, $quarter->effectiveGradingOptionId());
    }

    public function test_quarter_option_rejected_when_not_applicable_to_quarter(): void
    {
        $teacher = User::factory()->create();
        $default = $this->option();
        $q2Only = $this->option(['applicable_quarters' => [2]]);
        $record = $this->record($teacher, $default);

        $this->actingAs($teacher)
            ->putJson(route('class-records.quarters.grading-option', ['classRecord' => $record->id, 'q' => 1]), [
                'grading_option_id' => $q2Only->id,
            ])
            ->assertStatus(422);
    }

    public function test_quarter_option_blocked_when_quarter_has_assessments(): void
    {
        $teacher = User::factory()->create();
        $default = $this->option();
        $cat = GradingCategory::create(['grading_option_id' => $default->id, 'name' => 'All', 'code' => 'AL', 'weight' => 1.0, 'max_assessments' => 5, 'sort_order' => 0]);
        $other = $this->option();
        GradingCategory::create(['grading_option_id' => $other->id, 'name' => 'All', 'code' => 'AL', 'weight' => 1.0, 'max_assessments' => 5, 'sort_order' => 0]);

        $record = $this->record($teacher, $default);
        $quarter = ClassRecordQuarter::create(['class_record_id' => $record->id, 'quarter' => 1, 'grading_option_id' => $default->id, 'is_locked' => false]);
        ClassRecordAssessment::create([
            'class_record_quarter_id' => $quarter->id,
            'grading_category_id' => $cat->id,
            'assessment_type' => 'formative',
            'assessment_number' => 1,
            'title' => 'Quiz 1',
            'max_score' => 10,
            'sort_order' => 0,
        ]);

        $this->actingAs($teacher)
            ->putJson(route('class-records.quarters.grading-option', ['classRecord' => $record->id, 'q' => 1]), [
                'grading_option_id' => $other->id,
            ])
            ->assertStatus(422);

        $this->assertSame($default->id, $quarter->fresh()->grading_option_id);
    }

    public function test_non_owner_cannot_change_quarter_option(): void
    {
        $teacher = User::factory()->create();
        $stranger = $this->option(); // dummy
        $option = $this->option();
        $record = $this->record($teacher, $option);
        $other = User::factory()->create();

        $this->actingAs($other)
            ->putJson(route('class-records.quarters.grading-option', ['classRecord' => $record->id, 'q' => 2]), [
                'grading_option_id' => $option->id,
            ])
            ->assertForbidden();
    }
}
