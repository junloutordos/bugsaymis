<?php

namespace Tests\Feature\SPMS;

use App\Models\SPMS\FiscalPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FiscalPeriodTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_scope_returns_only_current_period(): void
    {
        FiscalPeriod::factory()->create(['is_current' => false, 'label' => 'Q1 2025']);
        $current = FiscalPeriod::factory()->create(['is_current' => true, 'label' => 'Q1 2026']);

        $result = FiscalPeriod::current()->get();

        $this->assertCount(1, $result);
        $this->assertSame($current->id, $result->first()->id);
    }

    public function test_quarter_links_to_parent_semester(): void
    {
        $semester = FiscalPeriod::factory()->create(['cadence' => 'semester', 'label' => '1st Semester 2026']);
        $quarter = FiscalPeriod::factory()->create([
            'cadence' => 'quarter',
            'label' => 'Q1 2026',
            'parent_period_id' => $semester->id,
        ]);

        $this->assertSame($semester->id, $quarter->parent->id);
        $this->assertTrue($semester->children->contains('id', $quarter->id));
    }

    public function test_of_cadence_scope_filters_by_cadence(): void
    {
        FiscalPeriod::factory()->create(['cadence' => 'quarter']);
        FiscalPeriod::factory()->create(['cadence' => 'annual']);

        $result = FiscalPeriod::ofCadence('annual')->get();

        $this->assertCount(1, $result);
        $this->assertSame('annual', $result->first()->cadence);
    }
}
