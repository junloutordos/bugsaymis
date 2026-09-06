<?php

namespace Tests\Unit;

use App\Models\AgencyOutcome;
use App\Models\OPCR\OpcrIndicator;
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
