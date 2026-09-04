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

    public function test_deleting_an_agency_outcome_removes_the_tag_but_leaves_pillar_and_strategy_intact(): void
    {
        // Pillar <-> AgencyOutcome and Strategy <-> AgencyOutcome are many-to-many —
        // deleting a Program only cascades its pivot rows, never the Pillar/Strategy itself.
        $outcome = AgencyOutcome::create(['outcome' => 'A. STEM Secondary Education Program']);
        $pillar = DostPillar::create(['name' => 'DOST Pillar 5: Governance']);
        $pillar->agencyOutcomes()->sync([$outcome->id]);
        $strategy = DostStrategy::create([
            'dost_pillar_id' => $pillar->id,
            'name' => 'Strategy 19: Roll-out enabled systems',
        ]);
        $strategy->agencyOutcomes()->sync([$outcome->id]);
        $sub = DostSubStrategy::create(['dost_strategy_id' => $strategy->id, 'description' => 'Sub 1']);

        $outcome->delete();

        $this->assertDatabaseHas('dost_pillars', ['id' => $pillar->id]);
        $this->assertDatabaseHas('dost_strategies', ['id' => $strategy->id]);
        $this->assertDatabaseHas('dost_sub_strategies', ['id' => $sub->id]);
        $this->assertDatabaseMissing('dost_pillar_agency_outcomes', ['dost_pillar_id' => $pillar->id]);
        $this->assertDatabaseMissing('dost_strategy_agency_outcomes', ['dost_strategy_id' => $strategy->id]);
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
