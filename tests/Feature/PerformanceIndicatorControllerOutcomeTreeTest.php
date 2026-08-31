<?php

namespace Tests\Feature;

use App\Models\AgencyOutcome;
use App\Models\PerformanceIndicator;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PerformanceIndicatorControllerOutcomeTreeTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $role = Role::create(['name' => 'Administrator']);
        $admin->roles()->attach($role->id);

        return $admin;
    }

    public function test_index_sends_outcomes_as_a_tree_and_indicator_agency_outcome_includes_its_parent(): void
    {
        $admin = $this->admin();
        $parent = AgencyOutcome::create(['outcome' => 'A. STEM', 'function_type' => 'Strategic Functions']);
        $child = AgencyOutcome::create(['outcome' => 'A. STEM', 'sub_outcome' => 'A.1', 'function_type' => 'Strategic Functions', 'parent_id' => $parent->id]);
        PerformanceIndicator::create(['agency_outcome_id' => $child->id, 'description' => 'Indicator 1', 'target' => '100%']);

        $response = $this->actingAs($admin)->get(route('performanceindicator.index', ['fiscal_year' => 'all']));

        $response->assertInertia(fn ($page) => $page
            ->component('PerformanceManagement/PerformanceIndicators')
            ->has('outcomes', 1)
            ->has('outcomes.0.children', 1)
            ->where('indicators.0.agency_outcome.parent.outcome', 'A. STEM')
        );
    }

    public function test_outcomes_tree_excludes_auto_generated_wdp_marker_rows(): void
    {
        $admin = $this->admin();
        $real = AgencyOutcome::create(['outcome' => 'Core Functions', 'function_type' => 'Core Functions']);
        AgencyOutcome::create([
            'outcome' => 'Core Functions',
            'sub_outcome' => 'App\\Models\\FacultyLoading\\LoadAssignment#99',
            'function_type' => 'Core Functions',
        ]);

        $response = $this->actingAs($admin)->get(route('performanceindicator.index', ['fiscal_year' => 'all']));

        $response->assertInertia(fn ($page) => $page
            ->has('outcomes', 1)
            ->where('outcomes.0.id', $real->id)
        );
    }
}
