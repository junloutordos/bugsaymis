<?php

namespace Tests\Feature;

use App\Models\AgencyOutcome;
use App\Models\PerformanceIndicator;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgencyOutcomeControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $role = Role::create(['name' => 'Administrator']);
        $admin->roles()->attach($role->id);

        return $admin;
    }

    public function test_creating_a_sub_outcome_inherits_the_parents_function_type_and_fiscal_year(): void
    {
        $admin = $this->admin();
        $parent = AgencyOutcome::create([
            'outcome' => 'A. STEM', 'function_type' => 'Strategic Functions', 'fiscal_year' => 2026,
        ]);

        $response = $this->actingAs($admin)->post(route('outcome.store'), [
            'parent_id' => $parent->id,
            'sub_outcome' => 'A.1',
            'outcome' => 'ignored, should be overwritten',
            'function_type' => 'Core Functions',
            'fiscal_year' => 2020,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        $this->assertDatabaseHas('agency_org_outcomes', [
            'parent_id' => $parent->id,
            'sub_outcome' => 'A.1',
            'outcome' => 'A. STEM',
            'function_type' => 'Strategic Functions',
            'fiscal_year' => 2026,
        ]);
    }

    public function test_cannot_delete_an_outcome_that_still_has_children(): void
    {
        $admin = $this->admin();
        $parent = AgencyOutcome::create(['outcome' => 'A. STEM', 'function_type' => 'Strategic Functions']);
        AgencyOutcome::create(['outcome' => 'A. STEM', 'sub_outcome' => 'A.1', 'function_type' => 'Strategic Functions', 'parent_id' => $parent->id]);

        $response = $this->actingAs($admin)->delete(route('outcome.destroy', $parent->id));

        $response->assertSessionHasErrors('agencyOutcome');
        $this->assertDatabaseHas('agency_org_outcomes', ['id' => $parent->id]);
    }

    public function test_cannot_delete_an_outcome_referenced_by_a_performance_indicator(): void
    {
        $admin = $this->admin();
        $outcome = AgencyOutcome::create(['outcome' => 'A. STEM', 'function_type' => 'Strategic Functions']);
        PerformanceIndicator::create(['agency_outcome_id' => $outcome->id, 'description' => 'Indicator', 'target' => '100%']);

        $response = $this->actingAs($admin)->delete(route('outcome.destroy', $outcome->id));

        $response->assertSessionHasErrors('agencyOutcome');
        $this->assertDatabaseHas('agency_org_outcomes', ['id' => $outcome->id]);
    }

    public function test_can_delete_a_childless_unreferenced_outcome(): void
    {
        $admin = $this->admin();
        $outcome = AgencyOutcome::create(['outcome' => 'A. STEM', 'function_type' => 'Strategic Functions']);

        $response = $this->actingAs($admin)->delete(route('outcome.destroy', $outcome->id));

        $response->assertRedirect();
        $this->assertDatabaseMissing('agency_org_outcomes', ['id' => $outcome->id]);
    }

    public function test_index_returns_top_level_outcomes_with_nested_children(): void
    {
        $admin = $this->admin();
        $parent = AgencyOutcome::create(['outcome' => 'A. STEM', 'function_type' => 'Strategic Functions']);
        AgencyOutcome::create(['outcome' => 'A. STEM', 'sub_outcome' => 'A.1', 'function_type' => 'Strategic Functions', 'parent_id' => $parent->id]);

        $response = $this->actingAs($admin)->get(route('outcome.index', ['fiscal_year' => 'all']));

        $response->assertInertia(fn ($page) => $page
            ->component('PerformanceManagement/AgencyOrgOutcome')
            ->has('outcomes', 1)
            ->where('outcomes.0.id', $parent->id)
            ->has('outcomes.0.children', 1)
            ->where('outcomes.0.children.0.sub_outcome', 'A.1')
        );
    }
}
