<?php

namespace Tests\Unit;

use App\Models\AgencyOutcome;
use App\Models\DostPillar;
use App\Models\DostStrategy;
use App\Models\OPCR\OpcrIndicator;
use App\Models\OPCR\OpcrIndicatorActual;
use App\Models\OPCR\OpcrSetting;
use App\Services\OPCR\OpcrPdfRowGrouper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpcrPdfViewTest extends TestCase
{
    use RefreshDatabase;

    private function render(iterable $indicators = [])
    {
        return view('opcr.pdf', [
            'fiscalYear' => 2026,
            'settings' => OpcrSetting::current(),
            'rows' => (new OpcrPdfRowGrouper)->group(collect($indicators)),
        ])->render();
    }

    public function test_it_renders_the_hardcoded_agency_organizational_outcome_banner(): void
    {
        $html = view('opcr.pdf', [
            'fiscalYear' => 2026,
            'settings' => OpcrSetting::current(),
            'rows' => [],
        ])->render();

        $this->assertStringContainsString('AGENCY ORGANIZATIONAL OUTCOME', $html);
        $this->assertStringContainsString('Increased Competitiveness of Filipinos in Science and Engineering', $html);
    }

    public function test_it_renders_the_rating_legend(): void
    {
        $html = view('opcr.pdf', [
            'fiscalYear' => 2026,
            'settings' => OpcrSetting::current(),
            'rows' => [],
        ])->render();

        $this->assertStringContainsString('5 - Outstanding', $html);
        $this->assertStringContainsString('4 - Very Satisfactory', $html);
        $this->assertStringContainsString('3 - Satisfactory', $html);
        $this->assertStringContainsString('2 - Unsatisfactory', $html);
        $this->assertStringContainsString('1 - Poor', $html);
    }

    public function test_it_no_longer_renders_an_oic_campus_director_signatory(): void
    {
        $settings = OpcrSetting::current();
        $settings->update(['campus_director_name' => 'RAMIL A. SANCHEZ']);

        $html = view('opcr.pdf', [
            'fiscalYear' => 2026,
            'settings' => $settings,
            'rows' => [],
        ])->render();

        $this->assertStringNotContainsString('OIC-Campus Director', $html);
        $this->assertStringContainsString('RAMIL A. SANCHEZ', $html);
        $this->assertStringContainsString('Campus Director', $html);
    }

    public function test_it_still_renders_the_executive_director_signatory(): void
    {
        $settings = OpcrSetting::current();
        $settings->update(['executive_director_name' => 'RONNALEE N. ORTEZA']);

        $html = view('opcr.pdf', [
            'fiscalYear' => 2026,
            'settings' => $settings,
            'rows' => [],
        ])->render();

        $this->assertStringContainsString('RONNALEE N. ORTEZA', $html);
        $this->assertStringContainsString('Executive Director, PSHS System', $html);
    }

    public function test_the_pillars_outcome_statement_renders_below_its_name_with_a_line_break(): void
    {
        $pillar = DostPillar::create(['name' => 'DOST Pillar 1: Human Well-Being', 'outcome_statement' => 'Outcome 1: Human well-being promoted']);
        $strategy = DostStrategy::create(['dost_pillar_id' => $pillar->id, 'name' => 'Strategy 1']);
        $program = AgencyOutcome::create(['outcome' => 'A. STEM']);
        $program->dostStrategies()->attach($strategy->id);
        $indicator = OpcrIndicator::create(['fiscal_year' => 2026, 'agency_outcome_id' => $program->id, 'description' => 'First']);
        $loaded = OpcrIndicator::with(['agencyOutcome.dostStrategies.pillar', 'agencyOutcome.dostStrategies.subStrategies', 'divisions', 'actuals'])
            ->whereKey($indicator->id)->get();

        $html = $this->render($loaded);

        $this->assertStringContainsString("DOST Pillar 1: Human Well-Being<br />\n<br />\nOutcome 1: Human well-being promoted", $html);
    }

    public function test_it_renders_a_single_actual_accomplishment_column_instead_of_separate_quarter_columns(): void
    {
        $program = AgencyOutcome::create(['outcome' => 'A. STEM']);
        $indicator = OpcrIndicator::create(['fiscal_year' => 2026, 'agency_outcome_id' => $program->id, 'description' => 'First']);
        OpcrIndicatorActual::create(['opcr_indicator_id' => $indicator->id, 'quarter' => 1, 'value' => '80%']);
        OpcrIndicatorActual::create(['opcr_indicator_id' => $indicator->id, 'quarter' => 3, 'value' => '90%']);
        $loaded = OpcrIndicator::with(['agencyOutcome', 'divisions', 'actuals'])->whereKey($indicator->id)->get();

        $html = $this->render($loaded);

        $this->assertStringContainsString('Actual Accomplishment', $html);
        $this->assertStringContainsString('Q1: 80%; Q3: 90%', $html);
        $this->assertStringNotContainsString('<th>Q1</th>', $html);
        $this->assertStringNotContainsString('<th>Q2</th>', $html);
        $this->assertStringNotContainsString('<th>Q3</th>', $html);
        $this->assertStringNotContainsString('<th>Q4</th>', $html);
    }

    public function test_it_prefers_the_manual_accomplishment_override_over_the_auto_summary(): void
    {
        $program = AgencyOutcome::create(['outcome' => 'A. STEM']);
        $indicator = OpcrIndicator::create([
            'fiscal_year' => 2026,
            'agency_outcome_id' => $program->id,
            'description' => 'First',
            'accomplishment' => 'Fully accomplished ahead of schedule',
        ]);
        OpcrIndicatorActual::create(['opcr_indicator_id' => $indicator->id, 'quarter' => 1, 'value' => '80%']);
        $loaded = OpcrIndicator::with(['agencyOutcome', 'divisions', 'actuals'])->whereKey($indicator->id)->get();

        $html = $this->render($loaded);

        $this->assertStringContainsString('Fully accomplished ahead of schedule', $html);
        $this->assertStringNotContainsString('Q1: 80%', $html);
    }

    public function test_program_column_rowspans_across_consecutive_matching_indicators(): void
    {
        $program = AgencyOutcome::create(['outcome' => 'A. STEM']);
        $i1 = OpcrIndicator::create(['fiscal_year' => 2026, 'agency_outcome_id' => $program->id, 'description' => 'First']);
        $i2 = OpcrIndicator::create(['fiscal_year' => 2026, 'agency_outcome_id' => $program->id, 'description' => 'Second']);
        $loaded = OpcrIndicator::with(['subStrategy.strategy.pillar', 'agencyOutcome', 'divisions', 'actuals'])
            ->whereIn('id', [$i1->id, $i2->id])->get();

        $html = $this->render($loaded);

        $this->assertStringContainsString('rowspan="2"', $html);
        $this->assertSame(1, substr_count($html, 'A. STEM'));
    }
}
