<?php

namespace Tests\Feature;

use App\Models\AgencyOutcome;
use App\Models\DostPillar;
use App\Models\DostStrategy;
use App\Models\DostSubStrategy;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DostStrategicPlanControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $role = Role::create(['name' => 'Administrator']);
        $admin->roles()->attach($role->id);

        return $admin;
    }

    private function userWithoutIpcrView(): User
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'RegularStaffTester_'.uniqid()]);
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_index_renders_nested_pillars_strategies_and_sub_strategies(): void
    {
        $admin = $this->admin();
        $outcome = AgencyOutcome::create(['outcome' => 'A. STEM Secondary Education Program']);
        $pillar = DostPillar::create(['name' => 'DOST Pillar 1: Human Well-Being']);
        $strategy = DostStrategy::create([
            'dost_pillar_id' => $pillar->id,
            'agency_outcome_id' => $outcome->id,
            'name' => 'Strategy 1: Achieve quality science education',
        ]);
        DostSubStrategy::create([
            'dost_strategy_id' => $strategy->id,
            'description' => 'Institutionalize FORWARD Framework',
        ]);

        $response = $this->actingAs($admin)->get(route('dost-strategic-plan.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('PerformanceManagement/DostStrategicPlan')
            ->has('pillars', 1)
            ->where('pillars.0.strategies.0.sub_strategies.0.description', 'Institutionalize FORWARD Framework')
            ->where('pillars.0.strategies.0.agency_outcome.outcome', 'A. STEM Secondary Education Program')
        );
    }

    public function test_index_renders_with_no_data(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->get(route('dost-strategic-plan.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('PerformanceManagement/DostStrategicPlan')
            ->has('pillars', 0)
        );
    }

    public function test_user_without_ipcr_view_permission_cannot_view_the_index(): void
    {
        $user = $this->userWithoutIpcrView();

        $response = $this->actingAs($user)->get(route('dost-strategic-plan.index'));

        $response->assertForbidden();
    }

    public function test_agency_outcomes_picker_excludes_sub_outcome_children(): void
    {
        $admin = $this->admin();
        $parent = AgencyOutcome::create(['outcome' => 'A. STEM', 'function_type' => 'Strategic Functions']);
        AgencyOutcome::create(['outcome' => 'A. STEM', 'sub_outcome' => 'A.1', 'function_type' => 'Strategic Functions', 'parent_id' => $parent->id]);

        $response = $this->actingAs($admin)->get(route('dost-strategic-plan.index'));

        $response->assertInertia(fn ($page) => $page
            ->has('agencyOutcomes', 1)
            ->where('agencyOutcomes.0.id', $parent->id)
        );
    }

    public function test_agency_outcomes_picker_excludes_auto_generated_wdp_marker_rows(): void
    {
        $admin = $this->admin();
        $real = AgencyOutcome::create(['outcome' => 'Core Functions', 'function_type' => 'Core Functions']);
        AgencyOutcome::create([
            'outcome' => 'Core Functions',
            'sub_outcome' => 'App\\Models\\FacultyLoading\\LoadAssignment#99',
            'function_type' => 'Core Functions',
        ]);

        $response = $this->actingAs($admin)->get(route('dost-strategic-plan.index'));

        $response->assertInertia(fn ($page) => $page
            ->has('agencyOutcomes', 1)
            ->where('agencyOutcomes.0.id', $real->id)
        );
    }
}
