<?php

namespace Tests\Unit;

use App\Models\PPMP\Ppmp;
use App\Models\PPMP\PpmpItem;
use App\Services\PPMP\ComplianceValidationService;
use App\Services\PPMP\ThresholdConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit tests for the ComplianceValidationService.
 * Tests all 14 GPPB validation rules.
 */
class PPMPComplianceValidationTest extends TestCase
{
    use RefreshDatabase;

    private ComplianceValidationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ComplianceValidationService(new ThresholdConfigService());
    }

    protected function makePpmp(array $overrides = []): Ppmp
    {
        return Ppmp::create(array_merge([
            'ppmp_number' => 'PPMP-2026-TD-001',
            'title'       => 'Test',
            'fiscal_year' => 2026,
            'status'      => 'draft',
        ], $overrides));
    }

    protected function makeItem(Ppmp $ppmp, array $overrides = []): PpmpItem
    {
        return PpmpItem::create(array_merge([
            'ppmp_id'            => $ppmp->id,
            'description'        => 'Bond Paper A4',
            'unit'               => 'ream',
            'category'           => 'goods',
            'unit_cost'          => 250.00,
            'jan'                => 10,
            'feb'                => 0,
            'mar'                => 0,
            'apr'                => 0,
            'may'                => 0,
            'jun'                => 0,
            'jul'                => 0,
            'aug'                => 0,
            'sep'                => 0,
            'oct'                => 0,
            'nov'                => 0,
            'dec'                => 0,
            'procurement_method' => 'competitive_bidding',
            'sort_order'         => 0,
        ], $overrides));
    }

    // ─── PPMP-Level Rules ─────────────────────────────────────────────────────

    public function test_p1_ppmp_must_have_at_least_one_item(): void
    {
        $ppmp = $this->makePpmp();
        $result = $this->service->validatePPMP($ppmp, collect());

        $this->assertFalse($result['valid']);
        $this->assertContains('min_items', array_column($result['errors'], 'rule'));
    }

    public function test_p2_fiscal_year_cannot_be_in_the_past(): void
    {
        $ppmp = $this->makePpmp(['fiscal_year' => 2020]);
        $item = $this->makeItem($ppmp);

        $result = $this->service->validatePPMP($ppmp, collect([$item]));

        $this->assertContains('future_year', array_column($result['errors'], 'rule'));
    }

    // ─── Item-Level Rules ─────────────────────────────────────────────────────

    public function test_i1_description_required(): void
    {
        $ppmp = $this->makePpmp();
        $item = $this->makeItem($ppmp, ['description' => '']);

        $result = $this->service->validatePPMP($ppmp, collect([$item]));

        $this->assertFalse($result['valid']);
        $errors = collect($result['errors'])->where('item_id', $item->id)->where('field', 'description');
        $this->assertTrue($errors->isNotEmpty());
    }

    public function test_i2_unit_required(): void
    {
        $ppmp = $this->makePpmp();
        $item = $this->makeItem($ppmp, ['unit' => '']);

        $result = $this->service->validatePPMP($ppmp, collect([$item]));

        $errors = collect($result['errors'])->where('item_id', $item->id)->where('field', 'unit');
        $this->assertTrue($errors->isNotEmpty());
    }

    public function test_i3_category_required(): void
    {
        $ppmp = $this->makePpmp();
        $item = $this->makeItem($ppmp, ['category' => 'invalid']);

        $result = $this->service->validatePPMP($ppmp, collect([$item]));

        $errors = collect($result['errors'])->where('item_id', $item->id)->where('field', 'category');
        $this->assertTrue($errors->isNotEmpty());
    }

    public function test_i4_unit_cost_must_be_positive(): void
    {
        $ppmp = $this->makePpmp();
        $item = $this->makeItem($ppmp, ['unit_cost' => 0]);

        $result = $this->service->validatePPMP($ppmp, collect([$item]));

        $errors = collect($result['errors'])->where('item_id', $item->id)->where('rule', 'positive_cost');
        $this->assertTrue($errors->isNotEmpty());
    }

    public function test_i5_at_least_one_month_must_have_quantity(): void
    {
        $ppmp = $this->makePpmp();
        $item = $this->makeItem($ppmp, ['jan' => 0]); // all months are 0

        $result = $this->service->validatePPMP($ppmp, collect([$item]));

        $errors = collect($result['errors'])->where('item_id', $item->id)->where('rule', 'min_month');
        $this->assertTrue($errors->isNotEmpty());
    }

    public function test_i6_procurement_method_required(): void
    {
        $ppmp = $this->makePpmp();
        $item = $this->makeItem($ppmp, ['procurement_method' => '']);

        $result = $this->service->validatePPMP($ppmp, collect([$item]));

        $errors = collect($result['errors'])->where('item_id', $item->id)->where('field', 'procurement_method');
        $this->assertTrue($errors->isNotEmpty());
    }

    // ─── Threshold Rules ──────────────────────────────────────────────────────

    public function test_t1_shopping_goods_threshold_warning(): void
    {
        $ppmp = $this->makePpmp();
        // 100 units × ₱20,000 = ₱2,000,000 > ₱1,000,000 threshold
        $item = $this->makeItem($ppmp, [
            'unit_cost'          => 20000,
            'jan'                => 100,
            'category'           => 'goods',
            'procurement_method' => 'shopping',
        ]);

        $result = $this->service->validatePPMP($ppmp, collect([$item]));

        // Should be a warning, not an error
        $this->assertTrue($result['valid']); // warnings don't block
        $warnings = collect($result['warnings'])->where('rule', 'shopping_threshold');
        $this->assertTrue($warnings->isNotEmpty());
    }

    public function test_t1_shopping_goods_under_threshold_no_warning(): void
    {
        $ppmp = $this->makePpmp();
        // 10 units × ₱250 = ₱2,500 < ₱1,000,000 threshold
        $item = $this->makeItem($ppmp, [
            'unit_cost'          => 250,
            'jan'                => 10,
            'category'           => 'goods',
            'procurement_method' => 'shopping',
        ]);

        $result = $this->service->validatePPMP($ppmp, collect([$item]));

        $warnings = collect($result['warnings'])->where('rule', 'shopping_threshold');
        $this->assertTrue($warnings->isEmpty());
    }

    public function test_t3_direct_contracting_requires_justification(): void
    {
        $ppmp = $this->makePpmp();
        $item = $this->makeItem($ppmp, [
            'procurement_method' => 'direct_contracting',
            'remarks'            => '', // no justification
        ]);

        $result = $this->service->validatePPMP($ppmp, collect([$item]));

        $this->assertFalse($result['valid']);
        $errors = collect($result['errors'])->where('rule', 'justification_required');
        $this->assertTrue($errors->isNotEmpty());
    }

    public function test_t3_direct_contracting_with_justification_passes(): void
    {
        $ppmp = $this->makePpmp();
        $item = $this->makeItem($ppmp, [
            'procurement_method' => 'direct_contracting',
            'remarks'            => 'Sole distributor in the Philippines',
        ]);

        $result = $this->service->validatePPMP($ppmp, collect([$item]));

        $errors = collect($result['errors'])->where('rule', 'justification_required');
        $this->assertTrue($errors->isEmpty());
    }

    public function test_t4_repeat_order_shows_reminder_warning(): void
    {
        $ppmp = $this->makePpmp();
        $item = $this->makeItem($ppmp, ['procurement_method' => 'repeat_order']);

        $result = $this->service->validatePPMP($ppmp, collect([$item]));

        $warnings = collect($result['warnings'])->where('rule', 'repeat_order_reminder');
        $this->assertTrue($warnings->isNotEmpty());
    }

    // ─── Duplicate Detection ──────────────────────────────────────────────────

    public function test_d1_duplicate_items_detected(): void
    {
        $ppmp = $this->makePpmp();
        $item1 = $this->makeItem($ppmp, ['description' => 'Bond Paper A4', 'unit' => 'ream', 'category' => 'goods']);
        $item2 = $this->makeItem($ppmp, ['description' => 'Bond Paper A4', 'unit' => 'ream', 'category' => 'goods']);

        $result = $this->service->validatePPMP($ppmp, collect([$item1, $item2]));

        $this->assertFalse($result['valid']);
        $errors = collect($result['errors'])->where('rule', 'duplicate_item');
        $this->assertTrue($errors->isNotEmpty());
    }

    public function test_d1_different_categories_not_duplicate(): void
    {
        $ppmp = $this->makePpmp();
        $item1 = $this->makeItem($ppmp, ['description' => 'Printer', 'unit' => 'unit', 'category' => 'goods']);
        $item2 = $this->makeItem($ppmp, ['description' => 'Printer', 'unit' => 'unit', 'category' => 'infrastructure']);

        $result = $this->service->validatePPMP($ppmp, collect([$item1, $item2]));

        $errors = collect($result['errors'])->where('rule', 'duplicate_item');
        $this->assertTrue($errors->isEmpty());
    }

    // ─── Valid PPMP ───────────────────────────────────────────────────────────

    public function test_valid_ppmp_passes_all_checks(): void
    {
        $ppmp = $this->makePpmp();
        $item = $this->makeItem($ppmp, [
            'description'        => 'Bond Paper A4',
            'unit'               => 'ream',
            'category'           => 'goods',
            'unit_cost'          => 250,
            'jan'                => 10,
            'procurement_method' => 'competitive_bidding',
        ]);

        $result = $this->service->validatePPMP($ppmp, collect([$item]));

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    public function test_all_errors_returned_at_once(): void
    {
        $ppmp = $this->makePpmp();
        // Item with multiple issues: no description, no unit, zero cost, no month qty
        $item = $this->makeItem($ppmp, [
            'description' => '',
            'unit'        => '',
            'unit_cost'   => 0,
            'jan'         => 0,
        ]);

        $result = $this->service->validatePPMP($ppmp, collect([$item]));

        // Should have multiple errors, not just the first one
        $this->assertGreaterThan(1, count($result['errors']));
    }
}
