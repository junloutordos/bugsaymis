<?php

namespace Tests\Feature\ClassRecord;

use App\Models\ClassRecord\GradingCategory;
use App\Models\ClassRecord\GradingOption;
use App\Services\ClassRecord\WatRuleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression coverage for WatRuleService::isMajor().
 *
 * Before this fix, a QE/SE/PE category's assessment #1/#2 (type
 * long_test_1/long_test_2) was ALWAYS major, regardless of its actual
 * per-assessment weight share — bypassing the 10% floor that every other
 * category (formative, alternative, etc.) is held to. A QE/SE/PE category
 * split into enough pieces (or given a low enough weight) that each single
 * assessment is worth less than 10% of the quarter grade was still forced
 * to major, incorrectly counting against the WAT daily/weekly major caps.
 */
class WatRuleServiceIsMajorTest extends TestCase
{
    use RefreshDatabase;

    private GradingOption $option;

    protected function setUp(): void
    {
        parent::setUp();
        $this->option = GradingOption::create(['name' => 'Opt '.uniqid(), 'is_active' => true]);
    }

    private function makeCategory(string $code, float $weight, int $maxAssessments): GradingCategory
    {
        return GradingCategory::create([
            'grading_option_id' => $this->option->id,
            'name' => "Category {$code}",
            'code' => $code,
            'weight' => $weight,
            'max_assessments' => $maxAssessments,
            'sort_order' => 0,
        ]);
    }

    // ── QE/SE/PE categories under the 10% floor must NOT be major ──────────

    public function test_qe_category_split_below_10_percent_share_is_not_major(): void
    {
        // 0.30 / 4 = 7.5% per assessment — below the 10% floor.
        $category = $this->makeCategory('QE', 0.30, 4);
        $type = WatRuleService::deriveType($category->code, 1);

        $this->assertSame('long_test_1', $type);
        $this->assertFalse(WatRuleService::isMajor($type, $category));
    }

    public function test_se_category_split_below_10_percent_share_is_not_major(): void
    {
        // 0.20 / 3 = 6.67% per assessment — below the 10% floor.
        $category = $this->makeCategory('SE', 0.20, 3);
        $type = WatRuleService::deriveType($category->code, 2);

        $this->assertSame('long_test_2', $type);
        $this->assertFalse(WatRuleService::isMajor($type, $category));
    }

    public function test_pe_category_split_below_10_percent_share_is_not_major(): void
    {
        // 0.083 weight / 1 max_assessments = 8.3% — below the 10% floor.
        // Mirrors the real production categories (FA/PE/Health/Music split)
        // that surfaced this bug, applied here to a PE-coded category.
        $category = $this->makeCategory('PE', 0.083, 1);
        $type = WatRuleService::deriveType($category->code, 1);

        $this->assertSame('long_test_1', $type);
        $this->assertFalse(WatRuleService::isMajor($type, $category));
    }

    // ── QE/SE/PE categories at/above the 10% floor remain major (no regression) ──

    public function test_qe_category_at_exactly_10_percent_share_remains_major(): void
    {
        // 0.30 / 3 = 10% per assessment exactly — the float-guard boundary
        // case explicitly called out in the method's own comment.
        $category = $this->makeCategory('QE', 0.30, 3);
        $type = WatRuleService::deriveType($category->code, 1);

        $this->assertSame('long_test_1', $type);
        $this->assertTrue(WatRuleService::isMajor($type, $category));
    }

    public function test_se_category_above_10_percent_share_remains_major(): void
    {
        // 0.30 / 1 = 30% per assessment — the typical seeded configuration.
        $category = $this->makeCategory('SE', 0.30, 1);
        $type = WatRuleService::deriveType($category->code, 1);

        $this->assertSame('long_test_1', $type);
        $this->assertTrue(WatRuleService::isMajor($type, $category));
    }

    public function test_pe_category_second_assessment_above_10_percent_share_remains_major(): void
    {
        $category = $this->makeCategory('PE', 0.25, 1);
        $type = WatRuleService::deriveType($category->code, 2);

        $this->assertSame('long_test_2', $type);
        $this->assertTrue(WatRuleService::isMajor($type, $category));
    }

    // ── Non-long-test categories are unaffected by this change ─────────────

    public function test_formative_category_below_10_percent_share_is_not_major(): void
    {
        $category = $this->makeCategory('FA', 0.083, 1);
        $type = WatRuleService::deriveType($category->code, 1);

        $this->assertSame('formative', $type);
        $this->assertFalse(WatRuleService::isMajor($type, $category));
    }

    public function test_formative_category_at_or_above_10_percent_share_is_major(): void
    {
        // 0.30 / 1 = 30% — formative categories can still cross the floor
        // and count as major; the rule is about weight share, not the
        // category's usual role.
        $category = $this->makeCategory('FA', 0.30, 1);
        $type = WatRuleService::deriveType($category->code, 1);

        $this->assertSame('formative', $type);
        $this->assertTrue(WatRuleService::isMajor($type, $category));
    }
}
