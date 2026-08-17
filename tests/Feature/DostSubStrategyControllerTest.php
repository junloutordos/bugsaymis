<?php

namespace Tests\Feature;

use App\Models\DostPillar;
use App\Models\DostStrategy;
use App\Models\DostSubStrategy;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DostSubStrategyControllerTest extends TestCase
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

    private function strategy(): DostStrategy
    {
        $pillar = DostPillar::create(['name' => 'DOST Pillar 1: Human Well-Being']);

        return DostStrategy::create(['dost_pillar_id' => $pillar->id, 'name' => 'Strategy 1']);
    }

    public function test_administrator_can_create_a_sub_strategy(): void
    {
        $admin = $this->admin();
        $strategy = $this->strategy();

        $response = $this->actingAs($admin)->post(route('dost-sub-strategies.store'), [
            'dost_strategy_id' => $strategy->id,
            'description' => 'Institutionalize FORWARD Framework',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('dost_sub_strategies', [
            'dost_strategy_id' => $strategy->id,
            'description' => 'Institutionalize FORWARD Framework',
        ]);
    }

    public function test_creating_a_sub_strategy_requires_a_valid_strategy(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post(route('dost-sub-strategies.store'), [
            'dost_strategy_id' => 99999,
            'description' => 'Orphan Sub-Strategy',
        ]);

        $response->assertSessionHasErrors('dost_strategy_id');
        $this->assertDatabaseMissing('dost_sub_strategies', ['description' => 'Orphan Sub-Strategy']);
    }

    public function test_administrator_can_update_a_sub_strategy(): void
    {
        $admin = $this->admin();
        $strategy = $this->strategy();
        $sub = DostSubStrategy::create(['dost_strategy_id' => $strategy->id, 'description' => 'Old description']);

        $response = $this->actingAs($admin)->put(route('dost-sub-strategies.update', $sub), [
            'dost_strategy_id' => $strategy->id,
            'description' => 'New description',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('dost_sub_strategies', ['id' => $sub->id, 'description' => 'New description']);
    }

    public function test_administrator_can_delete_a_sub_strategy(): void
    {
        $admin = $this->admin();
        $strategy = $this->strategy();
        $sub = DostSubStrategy::create(['dost_strategy_id' => $strategy->id, 'description' => 'To delete']);

        $response = $this->actingAs($admin)->delete(route('dost-sub-strategies.destroy', $sub));

        $response->assertRedirect();
        $this->assertDatabaseMissing('dost_sub_strategies', ['id' => $sub->id]);
    }

    public function test_user_without_ipcr_view_permission_cannot_create_a_sub_strategy(): void
    {
        $user = $this->userWithoutIpcrView();
        $strategy = $this->strategy();

        $response = $this->actingAs($user)->post(route('dost-sub-strategies.store'), [
            'dost_strategy_id' => $strategy->id,
            'description' => 'Blocked',
        ]);

        $response->assertForbidden();
    }
}
