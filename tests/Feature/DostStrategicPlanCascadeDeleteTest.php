<?php

namespace Tests\Feature;

use App\Models\AgencyOutcome;
use App\Models\DostPillar;
use App\Models\DostStrategy;
use App\Models\DostSubStrategy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DostStrategicPlanCascadeDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_a_pillar_cascades_to_its_strategies_and_sub_strategies(): void
    {
        $pillar = DostPillar::create(['name' => 'DOST Pillar 1: Human Well-Being']);
        $strategy = DostStrategy::create(['dost_pillar_id' => $pillar->id, 'name' => 'Strategy 1']);
        $sub = DostSubStrategy::create(['dost_strategy_id' => $strategy->id, 'description' => 'Sub 1']);

        $pillar->delete();

        $this->assertDatabaseMissing('dost_strategies', ['id' => $strategy->id]);
        $this->assertDatabaseMissing('dost_sub_strategies', ['id' => $sub->id]);
    }

    public function test_deleting_an_agency_outcome_cascades_to_linked_strategies_and_sub_strategies(): void
    {
        $outcome = AgencyOutcome::create(['outcome' => 'A. STEM Secondary Education Program']);
        $pillar = DostPillar::create(['name' => 'DOST Pillar 5: Governance']);
        $strategy = DostStrategy::create([
            'dost_pillar_id' => $pillar->id,
            'agency_outcome_id' => $outcome->id,
            'name' => 'Strategy 19: Roll-out enabled systems',
        ]);
        $sub = DostSubStrategy::create(['dost_strategy_id' => $strategy->id, 'description' => 'Sub 1']);

        $outcome->delete();

        $this->assertDatabaseMissing('dost_strategies', ['id' => $strategy->id]);
        $this->assertDatabaseMissing('dost_sub_strategies', ['id' => $sub->id]);
        // The Pillar itself is untouched — only the Strategy/AgencyOutcome edge cascades.
        $this->assertDatabaseHas('dost_pillars', ['id' => $pillar->id]);
    }

    public function test_deleting_a_strategy_cascades_to_its_sub_strategies_but_not_its_pillar(): void
    {
        $pillar = DostPillar::create(['name' => 'DOST Pillar 2: Wealth Creation']);
        $strategy = DostStrategy::create(['dost_pillar_id' => $pillar->id, 'name' => 'Strategy 5']);
        $sub = DostSubStrategy::create(['dost_strategy_id' => $strategy->id, 'description' => 'Sub 1']);

        $strategy->delete();

        $this->assertDatabaseMissing('dost_sub_strategies', ['id' => $sub->id]);
        $this->assertDatabaseHas('dost_pillars', ['id' => $pillar->id]);
    }
}
