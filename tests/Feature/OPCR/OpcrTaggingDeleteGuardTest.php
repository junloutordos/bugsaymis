<?php

namespace Tests\Feature\OPCR;

use App\Models\AgencyOutcome;
use App\Models\DostPillar;
use App\Models\DostStrategy;
use App\Models\DostSubStrategy;
use App\Models\OPCR\OpcrIndicator;
use App\Models\OPCR\OpcrPeriod;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpcrTaggingDeleteGuardTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $role = Role::create(['name' => 'Administrator']);
        $admin->roles()->attach($role->id);

        return $admin;
    }

    public function test_cannot_delete_a_sub_strategy_still_tagged_on_an_opcr_indicator(): void
    {
        $admin = $this->admin();
        $pillar = DostPillar::create(['name' => 'Pillar 1']);
        $strategy = DostStrategy::create(['dost_pillar_id' => $pillar->id, 'name' => 'Strategy 1']);
        $subStrategy = DostSubStrategy::create(['dost_strategy_id' => $strategy->id, 'description' => 'Sub 1']);
        $period = OpcrPeriod::create(['fiscal_year' => 2026, 'period_label' => 'FY2026']);
        OpcrIndicator::create(['opcr_period_id' => $period->id, 'dost_sub_strategy_id' => $subStrategy->id, 'description' => 'Indicator']);

        $response = $this->actingAs($admin)->delete(route('dost-sub-strategies.destroy', $subStrategy));

        $response->assertSessionHasErrors();
        $this->assertModelExists($subStrategy);
    }

    public function test_cannot_delete_an_agency_outcome_still_tagged_on_an_opcr_indicator(): void
    {
        $admin = $this->admin();
        $outcome = AgencyOutcome::create(['outcome' => 'A. STEM']);
        $period = OpcrPeriod::create(['fiscal_year' => 2026, 'period_label' => 'FY2026']);
        OpcrIndicator::create(['opcr_period_id' => $period->id, 'agency_outcome_id' => $outcome->id, 'description' => 'Indicator']);

        $response = $this->actingAs($admin)->delete(route('outcome.destroy', $outcome->id));

        $response->assertSessionHasErrors();
        $this->assertModelExists($outcome);
    }

    public function test_sub_strategy_with_no_opcr_indicators_still_deletes_normally(): void
    {
        $admin = $this->admin();
        $pillar = DostPillar::create(['name' => 'Pillar 1']);
        $strategy = DostStrategy::create(['dost_pillar_id' => $pillar->id, 'name' => 'Strategy 1']);
        $subStrategy = DostSubStrategy::create(['dost_strategy_id' => $strategy->id, 'description' => 'Sub 1']);

        $response = $this->actingAs($admin)->delete(route('dost-sub-strategies.destroy', $subStrategy));

        $response->assertRedirect();
        $this->assertModelMissing($subStrategy);
    }
}
