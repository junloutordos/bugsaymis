<?php

namespace Tests\Feature\OPCR;

use App\Models\AgencyOutcome;
use App\Models\DostPillar;
use App\Models\DostStrategy;
use App\Models\OPCR\OpcrIndicator;
use App\Models\PerformanceIndicator;
use App\Services\OPCR\OpcrPdfRowGrouper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpcrPdfRowGrouperTest extends TestCase
{
    use RefreshDatabase;

    private function indicator(AgencyOutcome $program, string $description, ?PerformanceIndicator $sourcePi = null): OpcrIndicator
    {
        return OpcrIndicator::create([
            'fiscal_year' => 2026,
            'agency_outcome_id' => $program->id,
            'performance_indicator_id' => $sourcePi?->id,
            'description' => $description,
        ]);
    }

    private function loaded(iterable $ids)
    {
        return OpcrIndicator::with([
            'agencyOutcome.dostStrategies.pillar',
            'agencyOutcome.dostStrategies.subStrategies',
            'performanceIndicator.agencyOutcome.dostStrategies.pillar',
            'performanceIndicator.agencyOutcome.dostStrategies.subStrategies',
            'performanceIndicator.agencyOutcome.parent.dostStrategies.pillar',
            'performanceIndicator.agencyOutcome.parent.dostStrategies.subStrategies',
            'divisions',
            'actuals',
        ])->whereIn('id', $ids)->get();
    }

    public function test_sorts_by_program_outcome_text_ascending(): void
    {
        $programD = AgencyOutcome::create(['outcome' => 'D. Leadership']);
        $programA = AgencyOutcome::create(['outcome' => 'A. STEM']);
        $indicatorD = $this->indicator($programD, 'D indicator');
        $indicatorA = $this->indicator($programA, 'A indicator');

        $rows = (new OpcrPdfRowGrouper)->group($this->loaded([$indicatorD->id, $indicatorA->id]));

        $this->assertSame('A indicator', $rows[0]['indicator']->description);
        $this->assertSame('D indicator', $rows[1]['indicator']->description);
    }

    public function test_rowspans_the_program_column_across_consecutive_matching_rows(): void
    {
        $program = AgencyOutcome::create(['outcome' => 'A. STEM']);
        $other = AgencyOutcome::create(['outcome' => 'B. Promotion']);
        $i1 = $this->indicator($program, 'First');
        $i2 = $this->indicator($program, 'Second');
        $i3 = $this->indicator($other, 'Third');

        $rows = (new OpcrPdfRowGrouper)->group($this->loaded([$i1->id, $i2->id, $i3->id]));

        $this->assertSame(2, $rows[0]['rowspan']['program']);
        $this->assertTrue($rows[0]['show']['program']);
        $this->assertFalse($rows[1]['show']['program']);
        $this->assertSame(1, $rows[2]['rowspan']['program']);
        $this->assertTrue($rows[2]['show']['program']);
    }

    public function test_pillar_strategy_and_sub_strategy_text_is_derived_from_the_programs_own_dost_tags(): void
    {
        $pillar = DostPillar::create(['name' => 'Pillar 1']);
        $strategy = DostStrategy::create(['dost_pillar_id' => $pillar->id, 'name' => 'Strategy 1']);
        $program = AgencyOutcome::create(['outcome' => 'A. STEM']);
        $program->dostStrategies()->attach($strategy->id);
        $i1 = $this->indicator($program, 'First');

        $rows = (new OpcrPdfRowGrouper)->group($this->loaded([$i1->id]));

        $this->assertSame('Pillar 1', $rows[0]['pillar_text']);
        $this->assertSame('Strategy 1', $rows[0]['strategy_text']);
    }

    public function test_multiple_tagged_pillars_and_strategies_are_joined_with_semicolons(): void
    {
        $pillarA = DostPillar::create(['name' => 'Pillar A']);
        $pillarB = DostPillar::create(['name' => 'Pillar B']);
        $strategyA = DostStrategy::create(['dost_pillar_id' => $pillarA->id, 'name' => 'Strategy A']);
        $strategyB = DostStrategy::create(['dost_pillar_id' => $pillarB->id, 'name' => 'Strategy B']);
        $program = AgencyOutcome::create(['outcome' => 'B. Promotion']);
        $program->dostStrategies()->attach([$strategyA->id, $strategyB->id]);
        $i1 = $this->indicator($program, 'First');

        $rows = (new OpcrPdfRowGrouper)->group($this->loaded([$i1->id]));

        $this->assertSame('Pillar A; Pillar B', $rows[0]['pillar_text']);
        $this->assertSame('Strategy A; Strategy B', $rows[0]['strategy_text']);
    }

    public function test_dost_columns_rowspan_across_consecutive_rows_sharing_the_same_program(): void
    {
        $pillar = DostPillar::create(['name' => 'Pillar 1']);
        $strategy = DostStrategy::create(['dost_pillar_id' => $pillar->id, 'name' => 'Strategy 1']);
        $program = AgencyOutcome::create(['outcome' => 'A. STEM']);
        $program->dostStrategies()->attach($strategy->id);
        $i1 = $this->indicator($program, 'First');
        $i2 = $this->indicator($program, 'Second');

        $rows = (new OpcrPdfRowGrouper)->group($this->loaded([$i1->id, $i2->id]));

        $this->assertSame(2, $rows[0]['rowspan']['pillar']);
        $this->assertSame(2, $rows[0]['rowspan']['strategy']);
        $this->assertFalse($rows[1]['show']['pillar']);
        $this->assertFalse($rows[1]['show']['strategy']);
    }

    public function test_uses_the_source_performance_indicators_own_child_outcome_tagging_not_the_shared_parent_programs(): void
    {
        $pillar17 = DostPillar::create(['name' => 'DOST Pillar 5: Governance']);
        $strategy17 = DostStrategy::create(['dost_pillar_id' => $pillar17->id, 'name' => 'Strategy 17']);
        $pillar1 = DostPillar::create(['name' => 'DOST Pillar 1: Human Well-Being']);
        $strategy1 = DostStrategy::create(['dost_pillar_id' => $pillar1->id, 'name' => 'Strategy 1']);

        $programB = AgencyOutcome::create(['outcome' => 'B. STEM Promotion Program']);
        $childNce = AgencyOutcome::create(['outcome' => 'B. STEM Promotion Program', 'sub_outcome' => 'B.1. NCE applicants', 'parent_id' => $programB->id]);
        $childGwa = AgencyOutcome::create(['outcome' => 'B. STEM Promotion Program', 'sub_outcome' => 'B.2. Freshmen GWA', 'parent_id' => $programB->id]);
        $childNce->dostStrategies()->attach($strategy17->id);
        $childGwa->dostStrategies()->attach($strategy1->id);

        $piNce = PerformanceIndicator::create(['agency_outcome_id' => $childNce->id, 'description' => 'NCE indicator', 'target' => '63%']);
        $piGwa = PerformanceIndicator::create(['agency_outcome_id' => $childGwa->id, 'description' => 'GWA indicator', 'target' => '95%']);

        // Both OpcrIndicators store the shared top-level Program (as today),
        // but each is linked back to a source PI tagged to a DIFFERENT child.
        $nceIndicator = $this->indicator($programB, 'NCE indicator', $piNce);
        $gwaIndicator = $this->indicator($programB, 'GWA indicator', $piGwa);

        $rows = (new OpcrPdfRowGrouper)->group($this->loaded([$nceIndicator->id, $gwaIndicator->id]));

        $nceRow = collect($rows)->firstWhere(fn ($r) => $r['indicator']->id === $nceIndicator->id);
        $gwaRow = collect($rows)->firstWhere(fn ($r) => $r['indicator']->id === $gwaIndicator->id);

        $this->assertSame('DOST Pillar 5: Governance', $nceRow['pillar_text']);
        $this->assertSame('Strategy 17', $nceRow['strategy_text']);
        $this->assertSame('DOST Pillar 1: Human Well-Being', $gwaRow['pillar_text']);
        $this->assertSame('Strategy 1', $gwaRow['strategy_text']);
    }

    public function test_a_child_with_no_tags_of_its_own_inherits_the_parent_programs_tags(): void
    {
        $pillar = DostPillar::create(['name' => 'Pillar 1']);
        $strategy = DostStrategy::create(['dost_pillar_id' => $pillar->id, 'name' => 'Strategy 1']);
        $program = AgencyOutcome::create(['outcome' => 'A. STEM']);
        $program->dostStrategies()->attach($strategy->id);
        $child = AgencyOutcome::create(['outcome' => 'A. STEM', 'sub_outcome' => 'A.1', 'parent_id' => $program->id]);
        $pi = PerformanceIndicator::create(['agency_outcome_id' => $child->id, 'description' => 'A.1 indicator', 'target' => '90%']);
        $i1 = $this->indicator($program, 'A.1 indicator', $pi);

        $rows = (new OpcrPdfRowGrouper)->group($this->loaded([$i1->id]));

        $this->assertSame('Pillar 1', $rows[0]['pillar_text']);
        $this->assertSame('Strategy 1', $rows[0]['strategy_text']);
    }

    public function test_a_program_boundary_resets_dost_columns_even_if_their_tagging_repeats(): void
    {
        $pillar = DostPillar::create(['name' => 'Pillar 1']);
        $strategy = DostStrategy::create(['dost_pillar_id' => $pillar->id, 'name' => 'Strategy 1']);
        $programA = AgencyOutcome::create(['outcome' => 'A. STEM']);
        $programB = AgencyOutcome::create(['outcome' => 'B. Promotion']);
        // Same Pillar/Strategy tagged to two DIFFERENT programs — since
        // Program sorts first and is the outer boundary, these must NOT
        // merge into one rowspan across the program change.
        $programA->dostStrategies()->attach($strategy->id);
        $programB->dostStrategies()->attach($strategy->id);
        $i1 = $this->indicator($programA, 'A indicator');
        $i2 = $this->indicator($programB, 'B indicator');

        $rows = (new OpcrPdfRowGrouper)->group($this->loaded([$i1->id, $i2->id]));

        $this->assertSame(1, $rows[0]['rowspan']['pillar']);
        $this->assertTrue($rows[1]['show']['pillar']);
        $this->assertSame(1, $rows[1]['rowspan']['pillar']);
    }
}
