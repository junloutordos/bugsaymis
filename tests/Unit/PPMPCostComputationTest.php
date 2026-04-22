<?php

namespace Tests\Unit;

use App\Models\PPMP\Ppmp;
use App\Models\PPMP\PpmpItem;
use App\Services\PPMP\CostComputationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit tests for budget computation logic.
 */
class PPMPCostComputationTest extends TestCase
{
    use RefreshDatabase;

    private CostComputationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CostComputationService();
    }

    protected function makePpmp(array $overrides = []): Ppmp
    {
        return Ppmp::create(array_merge([
            'ppmp_number' => 'PPMP-2026-TD-' . str_pad(Ppmp::count() + 1, 3, '0', STR_PAD_LEFT),
            'title'       => 'Test',
            'fiscal_year' => 2026,
            'status'      => 'draft',
        ], $overrides));
    }

    protected function makeItem(Ppmp $ppmp, array $overrides = []): PpmpItem
    {
        return PpmpItem::create(array_merge([
            'ppmp_id'            => $ppmp->id,
            'description'        => 'Item',
            'unit'               => 'unit',
            'category'           => 'goods',
            'unit_cost'          => 100,
            'jan'                => 0, 'feb' => 0, 'mar' => 0, 'apr' => 0,
            'may'                => 0, 'jun' => 0, 'jul' => 0, 'aug' => 0,
            'sep'                => 0, 'oct' => 0, 'nov' => 0, 'dec' => 0,
            'procurement_method' => 'competitive_bidding',
            'sort_order'         => 0,
        ], $overrides));
    }

    // ─── Item-Level Computation ───────────────────────────────────────────────

    public function test_total_quantity_is_sum_of_months(): void
    {
        $ppmp = $this->makePpmp();
        $item = $this->makeItem($ppmp, [
            'jan' => 5, 'feb' => 10, 'mar' => 15, 'apr' => 0,
            'may' => 0, 'jun' => 0, 'jul' => 20, 'aug' => 0,
            'sep' => 0, 'oct' => 0, 'nov' => 0, 'dec' => 0,
        ]);

        $this->assertEquals(50, $item->total_quantity);
    }

    public function test_total_cost_is_quantity_times_unit_cost(): void
    {
        $ppmp = $this->makePpmp();
        $item = $this->makeItem($ppmp, [
            'unit_cost' => 250.50,
            'jan'       => 10,
            'feb'       => 10,
        ]);

        $this->assertEquals(20, $item->total_quantity);
        $this->assertEquals(5010.00, $item->total_cost);
    }

    public function test_zero_quantity_gives_zero_cost(): void
    {
        $ppmp = $this->makePpmp();
        $item = $this->makeItem($ppmp, ['unit_cost' => 1000]);

        $this->assertEquals(0, $item->total_quantity);
        $this->assertEquals(0, $item->total_cost);
    }

    public function test_totals_recompute_on_update(): void
    {
        $ppmp = $this->makePpmp();
        $item = $this->makeItem($ppmp, ['unit_cost' => 100, 'jan' => 10]);

        $this->assertEquals(1000, $item->total_cost);

        $item->update(['jan' => 20, 'unit_cost' => 200]);
        $item->refresh();

        $this->assertEquals(20, $item->total_quantity);
        $this->assertEquals(4000, $item->total_cost);
    }

    // ─── PPMP-Level Computation ───────────────────────────────────────────────

    public function test_ppmp_summary_groups_by_category(): void
    {
        $ppmp = $this->makePpmp();
        $this->makeItem($ppmp, ['category' => 'goods', 'unit_cost' => 100, 'jan' => 10]);
        $this->makeItem($ppmp, ['category' => 'goods', 'unit_cost' => 200, 'jan' => 5]);
        $this->makeItem($ppmp, ['category' => 'infrastructure', 'unit_cost' => 5000, 'jan' => 1]);

        $summary = $this->service->computePPMPSummary($ppmp->id);

        $goods = collect($summary)->firstWhere('category', 'goods');
        $infra = collect($summary)->firstWhere('category', 'infrastructure');

        $this->assertEquals(2, $goods['item_count']);
        $this->assertEquals(2000.00, $goods['subtotal']); // (10×100) + (5×200)
        $this->assertEquals(1, $infra['item_count']);
        $this->assertEquals(5000.00, $infra['subtotal']);
    }

    public function test_ppmp_grand_total(): void
    {
        $ppmp = $this->makePpmp();
        $this->makeItem($ppmp, ['unit_cost' => 100, 'jan' => 10]); // 1000
        $this->makeItem($ppmp, ['unit_cost' => 200, 'feb' => 5]);  // 1000

        $total = $this->service->computePPMPGrandTotal($ppmp->id);

        $this->assertEquals(2000.00, $total);
    }

    public function test_ppmp_grand_total_empty_ppmp(): void
    {
        $ppmp = $this->makePpmp();

        $total = $this->service->computePPMPGrandTotal($ppmp->id);

        $this->assertEquals(0, $total);
    }

    // ─── APP-Level Computation ────────────────────────────────────────────────

    public function test_app_totals_only_include_approved_ppmps(): void
    {
        $approved = $this->makePpmp(['status' => 'approved']);
        $draft = $this->makePpmp(['status' => 'draft']);

        $this->makeItem($approved, ['unit_cost' => 100, 'jan' => 10]); // 1000
        $this->makeItem($draft, ['unit_cost' => 500, 'jan' => 10]);    // 5000 (should be excluded)

        $totals = $this->service->computeAPPTotals(2026);

        $this->assertEquals(1000.00, $totals['grand_total']);
    }

    public function test_app_totals_include_consolidated_ppmps(): void
    {
        $approved = $this->makePpmp(['status' => 'approved']);
        $consolidated = $this->makePpmp(['status' => 'consolidated']);

        $this->makeItem($approved, ['unit_cost' => 100, 'jan' => 10]);     // 1000
        $this->makeItem($consolidated, ['unit_cost' => 200, 'jan' => 10]); // 2000

        $totals = $this->service->computeAPPTotals(2026);

        $this->assertEquals(3000.00, $totals['grand_total']);
    }

    // ─── Edge Cases ───────────────────────────────────────────────────────────

    public function test_large_budget_precision(): void
    {
        $ppmp = $this->makePpmp();
        // ₱50,000 × 1000 = ₱50,000,000
        $item = $this->makeItem($ppmp, ['unit_cost' => 50000, 'jan' => 500, 'jul' => 500]);

        $this->assertEquals(1000, $item->total_quantity);
        $this->assertEquals(50000000.00, $item->total_cost);
    }

    public function test_decimal_unit_cost_precision(): void
    {
        $ppmp = $this->makePpmp();
        $item = $this->makeItem($ppmp, ['unit_cost' => 99.99, 'jan' => 3]);

        $this->assertEquals(3, $item->total_quantity);
        $this->assertEquals(299.97, $item->total_cost);
    }
}
