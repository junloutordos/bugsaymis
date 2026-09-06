<?php

namespace Tests\Feature\OPCR;

use App\Models\AgencyOutcome;
use App\Models\Division;
use App\Models\DostPillar;
use App\Models\DostStrategy;
use App\Models\DostSubStrategy;
use App\Models\OPCR\OpcrIndicator;
use App\Models\PerformanceIndicator;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpcrIndicatorCrudTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        $role = Role::create(['name' => 'OCD']);
        $permission = Permission::firstOrCreate(['name' => 'opcr.manage'], ['module' => 'OPCR', 'description' => 'opcr.manage']);
        $role->permissions()->attach($permission->id);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        return $user;
    }

    // Manual creation and deletion are gone — every OpcrIndicator now comes
    // from OpcrIndicatorPropagationService, sourced from a Performance
    // Indicator tagged to a genuine PSHS Program. The only editable surface
    // left on the OPCR side is DOST tagging and remarks (see the "ignores
    // synced field changes" test below).
    public function test_store_route_no_longer_exists(): void
    {
        $user = $this->manager();

        $response = $this->actingAs($user)->post('/opcr-indicators', [
            'fiscal_year' => 2026,
            'description' => 'Indicator',
        ]);

        $response->assertNotFound();
    }

    public function test_destroy_route_no_longer_exists(): void
    {
        $user = $this->manager();
        $outcome = AgencyOutcome::create(['outcome' => 'A. STEM']);
        $indicator = OpcrIndicator::create(['fiscal_year' => 2026, 'agency_outcome_id' => $outcome->id, 'description' => 'Indicator']);

        $response = $this->actingAs($user)->delete("/opcr-indicators/{$indicator->id}");

        // 405, not 404: PUT still exists on this same URI (update), so
        // Laravel reports the method as disallowed rather than the route
        // as missing — that's still proof destroy() is gone.
        $response->assertStatus(405);
        $this->assertDatabaseHas('opcr_indicators', ['id' => $indicator->id]);
    }

    public function test_update_resyncs_divisions(): void
    {
        $user = $this->manager();
        $outcome = AgencyOutcome::create(['outcome' => 'A. STEM']);
        $divisionA = Division::create(['division_name' => 'CID', 'acronym' => 'CID']);
        $divisionB = Division::create(['division_name' => 'FAD', 'acronym' => 'FAD']);
        $indicator = OpcrIndicator::create(['fiscal_year' => 2026, 'agency_outcome_id' => $outcome->id, 'description' => 'Indicator']);
        $indicator->divisions()->sync([$divisionA->id]);

        $response = $this->actingAs($user)->put(route('opcr-indicators.update', $indicator), [
            'fiscal_year' => 2026,
            'agency_outcome_id' => $outcome->id,
            'description' => 'Indicator updated',
            'division_ids' => [$divisionB->id],
        ]);

        $response->assertRedirect();
        $fresh = $indicator->fresh();
        $this->assertEquals('Indicator updated', $fresh->description);
        $this->assertFalse($fresh->divisions->contains($divisionA));
        $this->assertTrue($fresh->divisions->contains($divisionB));
    }

    public function test_update_ignores_synced_field_changes_on_a_propagated_indicator_but_still_saves_remarks(): void
    {
        $user = $this->manager();
        $outcome = AgencyOutcome::create(['outcome' => 'A. STEM']);
        $otherOutcome = AgencyOutcome::create(['outcome' => 'B. Other']);
        $pi = PerformanceIndicator::create(['agency_outcome_id' => $outcome->id, 'description' => 'Source PI', 'target' => '90%']);
        $indicator = OpcrIndicator::create([
            'fiscal_year' => 2026,
            'agency_outcome_id' => $outcome->id,
            'performance_indicator_id' => $pi->id,
            'description' => 'Cohort survival rate',
            'target' => '90%',
            'budget' => 5000,
        ]);

        $response = $this->actingAs($user)->put(route('opcr-indicators.update', $indicator), [
            'fiscal_year' => 2026,
            'agency_outcome_id' => $otherOutcome->id,
            'description' => 'Tampered description',
            'target' => 'Tampered target',
            'budget' => 999999,
            'remarks' => 'A remark',
        ]);

        $response->assertRedirect();
        $fresh = $indicator->fresh();
        $this->assertEquals('Cohort survival rate', $fresh->description);
        $this->assertEquals('90%', $fresh->target);
        $this->assertEquals(5000, $fresh->budget);
        $this->assertEquals($outcome->id, $fresh->agency_outcome_id);
        $this->assertEquals('A remark', $fresh->remarks);
    }

    public function test_update_no_longer_accepts_a_manual_dost_sub_strategy_id(): void
    {
        $user = $this->manager();
        $outcome = AgencyOutcome::create(['outcome' => 'A. STEM']);
        $pillar = DostPillar::create(['name' => 'Pillar 1']);
        $strategy = DostStrategy::create(['dost_pillar_id' => $pillar->id, 'name' => 'Strategy 1']);
        $subStrategy = DostSubStrategy::create(['dost_strategy_id' => $strategy->id, 'description' => 'Sub 1']);
        $indicator = OpcrIndicator::create(['fiscal_year' => 2026, 'agency_outcome_id' => $outcome->id, 'description' => 'Indicator']);

        $response = $this->actingAs($user)->put(route('opcr-indicators.update', $indicator), [
            'fiscal_year' => 2026,
            'agency_outcome_id' => $outcome->id,
            'dost_sub_strategy_id' => $subStrategy->id,
            'description' => 'Indicator',
        ]);

        $response->assertRedirect();
        $this->assertNull($indicator->fresh()->dost_sub_strategy_id);
    }

    public function test_update_ignores_fiscal_year_changes_on_a_propagated_indicator(): void
    {
        $user = $this->manager();
        $outcome = AgencyOutcome::create(['outcome' => 'A. STEM']);
        $pi = PerformanceIndicator::create(['agency_outcome_id' => $outcome->id, 'description' => 'Source PI', 'target' => '90%']);
        $indicator = OpcrIndicator::create([
            'fiscal_year' => 2026,
            'agency_outcome_id' => $outcome->id,
            'performance_indicator_id' => $pi->id,
            'description' => 'Cohort survival rate',
        ]);

        $response = $this->actingAs($user)->put(route('opcr-indicators.update', $indicator), [
            'fiscal_year' => 2027,
            'agency_outcome_id' => $outcome->id,
            'description' => 'Cohort survival rate',
        ]);

        $response->assertRedirect();
        $this->assertSame(2026, $indicator->fresh()->fiscal_year);
    }

    public function test_update_ignores_attempts_to_relink_the_performance_indicator_on_a_propagated_indicator(): void
    {
        $user = $this->manager();
        $outcome = AgencyOutcome::create(['outcome' => 'A. STEM']);
        $pi = PerformanceIndicator::create(['agency_outcome_id' => $outcome->id, 'description' => 'Source PI', 'target' => '90%']);
        $otherPi = PerformanceIndicator::create(['agency_outcome_id' => $outcome->id, 'description' => 'Other PI', 'target' => '50%']);
        $indicator = OpcrIndicator::create([
            'fiscal_year' => 2026,
            'agency_outcome_id' => $outcome->id,
            'performance_indicator_id' => $pi->id,
            'description' => 'Cohort survival rate',
        ]);

        $response = $this->actingAs($user)->put(route('opcr-indicators.update', $indicator), [
            'fiscal_year' => 2026,
            'agency_outcome_id' => $outcome->id,
            'performance_indicator_id' => $otherPi->id,
            'description' => 'Cohort survival rate',
        ]);

        $response->assertRedirect();
        $this->assertEquals($pi->id, $indicator->fresh()->performance_indicator_id);
    }
}
