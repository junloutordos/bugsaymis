<?php

namespace Tests\Feature\IPCRV2;

use App\Models\AgencyOutcome;
use App\Models\IPCRRatingPeriod;
use App\Models\IPCRV2\IpcrV2Record;
use App\Models\OPCR\OpcrIndicator;
use App\Models\User;
use App\Services\IPCRV2\IpcrV2SummaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IpcrV2SummaryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_builds_strategic_core_and_support_summary_rows(): void
    {
        IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open', 'is_current' => true]);
        $program = AgencyOutcome::create(['outcome' => 'A. STEM Secondary Education']);
        OpcrIndicator::create([
            'fiscal_year' => 2026, 'agency_outcome_id' => $program->id, 'description' => 'x',
            'rating_quality' => 5, 'rating_efficiency' => 4, 'rating_timeliness' => 5, 'rating_average' => 4.67,
        ]);

        $user = User::factory()->create();
        $period = IPCRRatingPeriod::first();
        $record = IpcrV2Record::create(['user_id' => $user->id, 'rating_period_id' => $period->id]);
        $record->coreItems()->create(['label' => 'Subject 1', 'weight_percent' => 100, 'row_average' => 4.0]);
        $record->coreItems()->create([
            'label' => 'IT Management', 'weight_percent' => 50, 'success_indicator' => 'x',
            'quality_rating' => 5, 'efficiency_rating' => 4, 'timeliness_rating' => 3, 'row_average' => 4.0,
        ]);
        $record->supportItems()->create([
            'label' => 'Committee', 'quality_rating' => 5, 'efficiency_rating' => 4, 'timeliness_rating' => 3, 'row_average' => 4.0,
        ]);

        $rows = (new IpcrV2SummaryService())->buildRows($record->fresh(['coreItems', 'supportItems']));

        $this->assertSame('A. STEM Secondary Education', $rows['strategic'][0]['label']);
        $this->assertEquals(5, $rows['strategic'][0]['quality']);
        $this->assertEquals(4.67, $rows['strategic'][0]['average']);
        $this->assertSame('Outstanding', $rows['strategic'][0]['equivalent']);

        $this->assertSame('Subject 1', $rows['core'][0]['label']);
        $this->assertNull($rows['core'][0]['quality']); // untagged/legacy row never carries quality_rating
        $this->assertEquals(4.0, $rows['core'][0]['average']);
        $this->assertSame('Very Satisfactory', $rows['core'][0]['equivalent']);

        $this->assertSame('IT Management', $rows['core'][1]['label']);
        $this->assertEquals(5, $rows['core'][1]['quality']); // WDP-tagged row's own rating now surfaces (previously hardcoded null)

        $this->assertSame('Committee', $rows['support'][0]['label']);
        $this->assertEquals(5, $rows['support'][0]['quality']);
        $this->assertEquals(4.0, $rows['support'][0]['average']);
    }
}
