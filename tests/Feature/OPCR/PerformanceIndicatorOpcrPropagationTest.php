<?php

namespace Tests\Feature\OPCR;

use App\Models\AgencyOutcome;
use App\Models\OPCR\OpcrIndicator;
use App\Models\PerformanceIndicator;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PerformanceIndicatorOpcrPropagationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $role = Role::create(['name' => 'Administrator']);
        $admin->roles()->attach($role->id);

        return $admin;
    }

    public function test_storing_a_strategic_functions_indicator_auto_creates_an_opcr_indicator(): void
    {
        $admin = $this->admin();
        $outcome = AgencyOutcome::create(['outcome' => 'A. STEM', 'function_type' => 'Strategic Functions']);

        $response = $this->actingAs($admin)->post(route('performanceindicator.store'), [
            'description' => 'Cohort survival rate',
            'target' => '90%',
            'agency_outcome_id' => $outcome->id,
            'fiscal_year' => 2026,
        ]);

        $response->assertRedirect();
        $pi = PerformanceIndicator::firstWhere('description', 'Cohort survival rate');
        $this->assertDatabaseHas('opcr_indicators', [
            'performance_indicator_id' => $pi->id,
            'description' => 'Cohort survival rate',
            'fiscal_year' => 2026,
        ]);
    }

    public function test_storing_a_core_functions_indicator_does_not_create_an_opcr_indicator(): void
    {
        $admin = $this->admin();
        $outcome = AgencyOutcome::create(['outcome' => 'Core Functions', 'function_type' => 'Core Functions']);

        $response = $this->actingAs($admin)->post(route('performanceindicator.store'), [
            'description' => 'Daily attendance logging',
            'target' => '100%',
            'agency_outcome_id' => $outcome->id,
        ]);

        $response->assertRedirect();
        $pi = PerformanceIndicator::firstWhere('description', 'Daily attendance logging');
        $this->assertSame(0, OpcrIndicator::where('performance_indicator_id', $pi->id)->count());
    }

    public function test_updating_a_linked_performance_indicator_resyncs_the_opcr_indicator(): void
    {
        $admin = $this->admin();
        $outcome = AgencyOutcome::create(['outcome' => 'A. STEM', 'function_type' => 'Strategic Functions']);
        $pi = PerformanceIndicator::create([
            'description' => 'Original wording',
            'target' => '50%',
            'agency_outcome_id' => $outcome->id,
            'fiscal_year' => 2026,
        ]);
        app(\App\Services\OPCR\OpcrIndicatorPropagationService::class)->syncFromPerformanceIndicator($pi);

        $response = $this->actingAs($admin)->put(route('performanceindicator.update', $pi), [
            'description' => 'Revised wording',
            'target' => '80%',
            'agency_outcome_id' => $outcome->id,
            'fiscal_year' => 2026,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('opcr_indicators', [
            'performance_indicator_id' => $pi->id,
            'description' => 'Revised wording',
            'target' => '80%',
        ]);
    }

    public function test_deleting_a_linked_performance_indicator_unlinks_but_keeps_the_opcr_indicator(): void
    {
        $admin = $this->admin();
        $outcome = AgencyOutcome::create(['outcome' => 'A. STEM', 'function_type' => 'Strategic Functions']);
        $pi = PerformanceIndicator::create([
            'description' => 'Indicator',
            'target' => '50%',
            'agency_outcome_id' => $outcome->id,
            'fiscal_year' => 2026,
        ]);
        app(\App\Services\OPCR\OpcrIndicatorPropagationService::class)->syncFromPerformanceIndicator($pi);
        $opcrIndicator = OpcrIndicator::where('performance_indicator_id', $pi->id)->firstOrFail();

        $response = $this->actingAs($admin)->delete(route('performanceindicator.destroy', $pi));

        $response->assertRedirect();
        $this->assertNotNull(OpcrIndicator::find($opcrIndicator->id));
        $this->assertNull($opcrIndicator->fresh()->performance_indicator_id);
    }
}
