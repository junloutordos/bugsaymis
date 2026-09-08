<?php

namespace Tests\Feature\OPCR;

use App\Models\AgencyOutcome;
use App\Models\DostPillar;
use App\Models\DostStrategy;
use App\Models\DostSubStrategy;
use App\Models\OPCR\OpcrIndicator;
use App\Models\PerformanceIndicator;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CopyFrameworkPropagatesDostTaggingTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $role = Role::create(['name' => 'Administrator']);
        $admin->roles()->attach($role->id);

        return $admin;
    }

    public function test_cloned_agency_outcome_carries_pillar_and_strategy_tags(): void
    {
        $admin = $this->admin();
        $pillar = DostPillar::create(['name' => 'Pillar 1']);
        $strategy = DostStrategy::create(['dost_pillar_id' => $pillar->id, 'name' => 'Strategy 1']);
        $program = AgencyOutcome::create(['outcome' => 'A. STEM', 'function_type' => 'Strategic Functions', 'fiscal_year' => 2026]);
        $program->dostPillars()->attach($pillar->id);
        $program->dostStrategies()->attach($strategy->id);

        $response = $this->actingAs($admin)->post(route('ipcr-rating-periods.copyFramework'), [
            'source_year' => 2026,
            'target_year' => 2025,
        ]);

        $response->assertSessionHasNoErrors();

        $clonedProgram = AgencyOutcome::where('fiscal_year', 2025)->where('outcome', 'A. STEM')->firstOrFail();
        $this->assertTrue($clonedProgram->dostPillars->contains($pillar->id));
        $this->assertTrue($clonedProgram->dostStrategies->contains($strategy->id));
    }

    public function test_cloned_opcr_indicator_carries_sub_strategy_tag(): void
    {
        $admin = $this->admin();
        $pillar = DostPillar::create(['name' => 'Pillar 1']);
        $strategy = DostStrategy::create(['dost_pillar_id' => $pillar->id, 'name' => 'Strategy 1']);
        $subStrategy = DostSubStrategy::create(['dost_strategy_id' => $strategy->id, 'description' => 'Sub-Strategy 1']);
        $program = AgencyOutcome::create(['outcome' => 'A. STEM', 'function_type' => 'Strategic Functions', 'fiscal_year' => 2026]);
        $pi = PerformanceIndicator::create([
            'agency_outcome_id' => $program->id,
            'description' => 'Cohort survival rate',
            'target' => '90%',
            'fiscal_year' => 2026,
        ]);
        OpcrIndicator::where('performance_indicator_id', $pi->id)->update(['dost_sub_strategy_id' => $subStrategy->id]);

        $response = $this->actingAs($admin)->post(route('ipcr-rating-periods.copyFramework'), [
            'source_year' => 2026,
            'target_year' => 2025,
        ]);

        $response->assertSessionHasNoErrors();

        $clonedPi = PerformanceIndicator::where('fiscal_year', 2025)->where('description', 'Cohort survival rate')->firstOrFail();
        $clonedOpcrIndicator = OpcrIndicator::where('performance_indicator_id', $clonedPi->id)->firstOrFail();
        $this->assertSame($subStrategy->id, $clonedOpcrIndicator->dost_sub_strategy_id);
    }
}
