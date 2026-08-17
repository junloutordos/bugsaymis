<?php

namespace Tests\Feature;

use App\Models\DostPillar;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DostPillarControllerTest extends TestCase
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

    public function test_administrator_can_create_a_pillar(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post(route('dost-pillars.store'), [
            'name' => 'DOST Pillar 5: Governance',
            'outcome_statement' => 'DOST System Governance Strengthened and Harmonized',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('dost_pillars', ['name' => 'DOST Pillar 5: Governance']);
    }

    public function test_administrator_can_update_a_pillar(): void
    {
        $admin = $this->admin();
        $pillar = DostPillar::create(['name' => 'Old Name']);

        $response = $this->actingAs($admin)->put(route('dost-pillars.update', $pillar), [
            'name' => 'New Name',
            'outcome_statement' => 'Updated statement',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('dost_pillars', ['id' => $pillar->id, 'name' => 'New Name']);
    }

    public function test_administrator_can_delete_a_pillar(): void
    {
        $admin = $this->admin();
        $pillar = DostPillar::create(['name' => 'To Delete']);

        $response = $this->actingAs($admin)->delete(route('dost-pillars.destroy', $pillar));

        $response->assertRedirect();
        $this->assertDatabaseMissing('dost_pillars', ['id' => $pillar->id]);
    }

    public function test_user_without_ipcr_view_permission_cannot_create_a_pillar(): void
    {
        $user = $this->userWithoutIpcrView();

        $response = $this->actingAs($user)->post(route('dost-pillars.store'), [
            'name' => 'Blocked Pillar',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('dost_pillars', ['name' => 'Blocked Pillar']);
    }

    public function test_creating_a_pillar_requires_a_name(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post(route('dost-pillars.store'), [
            'outcome_statement' => 'No name provided',
        ]);

        $response->assertSessionHasErrors('name');
    }
}
