<?php

namespace Tests\Feature\IPCRV2;

use App\Models\AgencyOutcome;
use App\Models\IPCRRatingPeriod;
use App\Models\IPCRV2\IpcrV2Record;
use App\Models\IPCRWeightDistribution;
use App\Models\OPCR\OpcrIndicator;
use App\Models\User;
use App\Services\IPCRV2\IpcrV2RatingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IpcrV2RatingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_computes_weighted_final_rating_using_default_30_50_20_split(): void
    {
        IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open', 'is_current' => true]);
        $outcome = AgencyOutcome::create(['outcome' => 'A. Program']);
        OpcrIndicator::create(['fiscal_year' => 2026, 'agency_outcome_id' => $outcome->id, 'description' => 'x', 'rating_average' => 5.0]);

        $user = User::factory()->create();
        $period = IPCRRatingPeriod::first();
        $record = IpcrV2Record::create(['user_id' => $user->id, 'rating_period_id' => $period->id]);
        $record->coreItems()->create(['label' => 'Subject 1', 'weight_percent' => 100, 'row_average' => 4.0]);
        $record->supportItems()->create(['label' => 'Committee', 'row_average' => 3.0]);

        $rating = (new IpcrV2RatingService())->computeFinalRating($record->fresh(['coreItems', 'supportItems']));

        // 0.30*5.0 + 0.50*4.0 + 0.20*3.0 = 1.5 + 2.0 + 0.6 = 4.1
        $this->assertEqualsWithDelta(4.1, $rating, 0.01);
    }

    public function test_uses_division_specific_weight_distribution_when_configured(): void
    {
        IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open', 'is_current' => true]);
        $outcome = AgencyOutcome::create(['outcome' => 'A. Program']);
        OpcrIndicator::create(['fiscal_year' => 2026, 'agency_outcome_id' => $outcome->id, 'description' => 'x', 'rating_average' => 5.0]);

        $division = \App\Models\Division::create(['division_name' => 'CID', 'acronym' => 'CID']);
        IPCRWeightDistribution::create(['division_id' => $division->id, 'strategic' => 20, 'core' => 60, 'support' => 20]);

        $user = User::factory()->create(['division_id' => $division->id]);
        $period = IPCRRatingPeriod::first();
        $record = IpcrV2Record::create(['user_id' => $user->id, 'rating_period_id' => $period->id]);
        $record->coreItems()->create(['label' => 'Subject 1', 'weight_percent' => 100, 'row_average' => 4.0]);
        $record->supportItems()->create(['label' => 'Committee', 'row_average' => 3.0]);

        $rating = (new IpcrV2RatingService())->computeFinalRating($record->fresh(['coreItems', 'supportItems', 'user']));

        // 0.20*5.0 + 0.60*4.0 + 0.20*3.0 = 1.0 + 2.4 + 0.6 = 4.0
        $this->assertEqualsWithDelta(4.0, $rating, 0.01);
    }
}
