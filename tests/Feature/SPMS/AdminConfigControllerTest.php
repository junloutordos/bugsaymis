<?php

namespace Tests\Feature\SPMS;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminConfigControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $role = Role::create(['name' => 'HR']);
        $permission = Permission::create(['name' => 'spms.admin.manage', 'module' => 'SPMS']);
        $role->permissions()->attach($permission->id);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_stores_a_weight_profile(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/spms/admin/weight-profiles', [
            'level' => 'ipcr',
            'division_id' => null,
            'fiscal_year' => 2026,
            'strategic_pct' => 30,
            'core_pct' => 50,
            'support_pct' => 20,
        ])->assertRedirect();

        $this->assertDatabaseHas('spms_weight_profiles', ['level' => 'ipcr', 'fiscal_year' => 2026]);
    }

    public function test_rejects_weights_that_do_not_sum_to_100(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/spms/admin/weight-profiles', [
            'level' => 'ipcr',
            'fiscal_year' => 2026,
            'strategic_pct' => 30,
            'core_pct' => 50,
            'support_pct' => 30,
        ])->assertSessionHasErrors();
    }
}
