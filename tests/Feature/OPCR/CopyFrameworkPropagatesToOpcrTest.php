<?php

namespace Tests\Feature\OPCR;

use App\Models\AgencyOutcome;
use App\Models\OPCR\OpcrIndicator;
use App\Models\PerformanceIndicator;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CopyFrameworkPropagatesToOpcrTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $role = Role::create(['name' => 'Administrator']);
        $admin->roles()->attach($role->id);

        return $admin;
    }

    public function test_copying_a_fiscal_year_propagates_its_strategic_performance_indicators_into_opcr(): void
    {
        $admin = $this->admin();
        $program = AgencyOutcome::create(['outcome' => 'A. STEM', 'function_type' => 'Strategic Functions', 'fiscal_year' => 2026]);
        PerformanceIndicator::create([
            'agency_outcome_id' => $program->id,
            'description' => 'Cohort survival rate',
            'target' => '90%',
            'fiscal_year' => 2026,
        ]);

        $response = $this->actingAs($admin)->post(route('ipcr-rating-periods.copyFramework'), [
            'source_year' => 2026,
            'target_year' => 2027,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $clonedPi = PerformanceIndicator::where('fiscal_year', 2027)->where('description', 'Cohort survival rate')->firstOrFail();
        $this->assertDatabaseHas('opcr_indicators', [
            'performance_indicator_id' => $clonedPi->id,
            'fiscal_year' => 2027,
            'description' => 'Cohort survival rate',
        ]);
    }
}
