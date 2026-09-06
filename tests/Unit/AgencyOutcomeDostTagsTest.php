<?php

namespace Tests\Unit;

use App\Models\AgencyOutcome;
use App\Models\DostPillar;
use App\Models\DostStrategy;
use App\Models\DostSubStrategy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgencyOutcomeDostTagsTest extends TestCase
{
    use RefreshDatabase;

    public function test_joins_a_single_tagged_pillar_strategy_and_its_sub_strategies(): void
    {
        $pillar = DostPillar::create(['name' => 'Pillar 1']);
        $strategy = DostStrategy::create(['dost_pillar_id' => $pillar->id, 'name' => 'Strategy 1']);
        DostSubStrategy::create(['dost_strategy_id' => $strategy->id, 'description' => 'Sub A']);
        $program = AgencyOutcome::create(['outcome' => 'A. STEM']);
        $program->dostStrategies()->attach($strategy->id);

        $fresh = AgencyOutcome::with('dostStrategies.pillar', 'dostStrategies.subStrategies')->find($program->id);

        $this->assertSame('Pillar 1', $fresh->dost_pillar_names_joined);
        $this->assertSame('Strategy 1', $fresh->dost_strategy_names_joined);
        $this->assertSame('Sub A', $fresh->dost_sub_strategy_descriptions_joined);
    }

    public function test_joins_multiple_tagged_pillars_strategies_and_sub_strategies_with_semicolons(): void
    {
        $pillarA = DostPillar::create(['name' => 'Pillar A']);
        $pillarB = DostPillar::create(['name' => 'Pillar B']);
        $strategyA = DostStrategy::create(['dost_pillar_id' => $pillarA->id, 'name' => 'Strategy A']);
        $strategyB = DostStrategy::create(['dost_pillar_id' => $pillarB->id, 'name' => 'Strategy B']);
        DostSubStrategy::create(['dost_strategy_id' => $strategyA->id, 'description' => 'Sub A']);
        DostSubStrategy::create(['dost_strategy_id' => $strategyB->id, 'description' => 'Sub B']);
        $program = AgencyOutcome::create(['outcome' => 'B. Promotion']);
        $program->dostStrategies()->attach([$strategyA->id, $strategyB->id]);

        $fresh = AgencyOutcome::with('dostStrategies.pillar', 'dostStrategies.subStrategies')->find($program->id);

        $this->assertSame('Pillar A; Pillar B', $fresh->dost_pillar_names_joined);
        $this->assertSame('Strategy A; Strategy B', $fresh->dost_strategy_names_joined);
        $this->assertSame('Sub A; Sub B', $fresh->dost_sub_strategy_descriptions_joined);
    }

    public function test_returns_null_for_all_three_when_no_dost_strategy_is_tagged(): void
    {
        $program = AgencyOutcome::create(['outcome' => 'C. Untagged']);

        $fresh = AgencyOutcome::with('dostStrategies.pillar', 'dostStrategies.subStrategies')->find($program->id);

        $this->assertNull($fresh->dost_pillar_names_joined);
        $this->assertNull($fresh->dost_strategy_names_joined);
        $this->assertNull($fresh->dost_sub_strategy_descriptions_joined);
    }

    public function test_deduplicates_a_pillar_shared_by_two_tagged_strategies(): void
    {
        $pillar = DostPillar::create(['name' => 'Pillar 1']);
        $strategyA = DostStrategy::create(['dost_pillar_id' => $pillar->id, 'name' => 'Strategy A']);
        $strategyB = DostStrategy::create(['dost_pillar_id' => $pillar->id, 'name' => 'Strategy B']);
        $program = AgencyOutcome::create(['outcome' => 'A. STEM']);
        $program->dostStrategies()->attach([$strategyA->id, $strategyB->id]);

        $fresh = AgencyOutcome::with('dostStrategies.pillar', 'dostStrategies.subStrategies')->find($program->id);

        $this->assertSame('Pillar 1', $fresh->dost_pillar_names_joined);
        $this->assertSame('Strategy A; Strategy B', $fresh->dost_strategy_names_joined);
    }
}
