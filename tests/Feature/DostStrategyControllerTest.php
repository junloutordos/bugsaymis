<?php

namespace Tests\Feature;

use App\Models\AgencyOutcome;
use App\Models\DostPillar;
use App\Models\DostStrategy;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DostStrategyControllerTest extends TestCase
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

    public function test_administrator_can_create_a_strategy_without_an_agency_outcome(): void
    {
        $admin = $this->admin();
        $pillar = DostPillar::create(['name' => 'DOST Pillar 1: Human Well-Being']);

        $response = $this->actingAs($admin)->post(route('dost-strategies.store'), [
            'dost_pillar_id' => $pillar->id,
            'name' => 'Strategy 1: Achieve quality science education',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('dost_strategies', [
            'dost_pillar_id' => $pillar->id,
            'agency_outcome_id' => null,
            'name' => 'Strategy 1: Achieve quality science education',
        ]);
    }

    public function test_administrator_can_create_a_strategy_linked_to_an_agency_outcome(): void
    {
        $admin = $this->admin();
        $pillar = DostPillar::create(['name' => 'DOST Pillar 5: Governance']);
        $outcome = AgencyOutcome::create(['outcome' => 'B. STEM Promotion Program']);

        $response = $this->actingAs($admin)->post(route('dost-strategies.store'), [
            'dost_pillar_id' => $pillar->id,
            'agency_outcome_id' => $outcome->id,
            'name' => 'Strategy 17: Institutionalize science communication',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('dost_strategies', [
            'dost_pillar_id' => $pillar->id,
            'agency_outcome_id' => $outcome->id,
        ]);
    }

    public function test_creating_a_strategy_requires_a_valid_pillar(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post(route('dost-strategies.store'), [
            'dost_pillar_id' => 99999,
            'name' => 'Orphan Strategy',
        ]);

        $response->assertSessionHasErrors('dost_pillar_id');
        $this->assertDatabaseMissing('dost_strategies', ['name' => 'Orphan Strategy']);
    }

    public function test_creating_a_strategy_rejects_a_nonexistent_agency_outcome(): void
    {
        $admin = $this->admin();
        $pillar = DostPillar::create(['name' => 'DOST Pillar 2: Wealth Creation']);

        $response = $this->actingAs($admin)->post(route('dost-strategies.store'), [
            'dost_pillar_id' => $pillar->id,
            'agency_outcome_id' => 99999,
            'name' => 'Strategy 5: Advance research',
        ]);

        $response->assertSessionHasErrors('agency_outcome_id');
    }

    public function test_administrator_can_update_a_strategy(): void
    {
        $admin = $this->admin();
        $pillar = DostPillar::create(['name' => 'Pillar A']);
        $strategy = DostStrategy::create(['dost_pillar_id' => $pillar->id, 'name' => 'Old Name']);

        $response = $this->actingAs($admin)->put(route('dost-strategies.update', $strategy), [
            'dost_pillar_id' => $pillar->id,
            'name' => 'New Name',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('dost_strategies', ['id' => $strategy->id, 'name' => 'New Name']);
    }

    public function test_administrator_can_delete_a_strategy(): void
    {
        $admin = $this->admin();
        $pillar = DostPillar::create(['name' => 'Pillar A']);
        $strategy = DostStrategy::create(['dost_pillar_id' => $pillar->id, 'name' => 'To Delete']);

        $response = $this->actingAs($admin)->delete(route('dost-strategies.destroy', $strategy));

        $response->assertRedirect();
        $this->assertDatabaseMissing('dost_strategies', ['id' => $strategy->id]);
    }

    public function test_user_without_ipcr_view_permission_cannot_create_a_strategy(): void
    {
        $user = $this->userWithoutIpcrView();
        $pillar = DostPillar::create(['name' => 'Pillar A']);

        $response = $this->actingAs($user)->post(route('dost-strategies.store'), [
            'dost_pillar_id' => $pillar->id,
            'name' => 'Blocked Strategy',
        ]);

        $response->assertForbidden();
    }
}
