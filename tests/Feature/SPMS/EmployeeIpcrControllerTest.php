<?php

namespace Tests\Feature\SPMS;

use App\Models\Permission;
use App\Models\Role;
use App\Models\SPMS\FiscalPeriod;
use App\Models\SPMS\Ipcr;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeIpcrControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actingUserWithPermission(): User
    {
        $role = Role::create(['name' => 'Faculty']);
        $permission = Permission::create(['name' => 'spms.ipcr.manage', 'module' => 'SPMS']);
        $role->permissions()->attach($permission->id);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_index_renders_own_ipcrs_only(): void
    {
        $user = $this->actingUserWithPermission();
        $period = FiscalPeriod::factory()->create();
        Ipcr::factory()->create(['user_id' => $user->id, 'fiscal_period_id' => $period->id]);
        Ipcr::factory()->create(['fiscal_period_id' => $period->id]); // someone else's

        $response = $this->actingAs($user)->get('/spms/ipcr');

        $response->assertInertia(fn ($page) => $page
            ->component('SPMS/EmployeeIpcrIndex')
            ->has('ipcrs', 1)
        );
    }

    public function test_submit_target_transitions_status(): void
    {
        $user = $this->actingUserWithPermission();
        $ipcr = Ipcr::factory()->create(['user_id' => $user->id, 'status' => Ipcr::STATUS_DRAFT_TARGET]);

        $this->actingAs($user)->post("/spms/ipcr/{$ipcr->id}/submit-target")
            ->assertRedirect();

        $this->assertSame(Ipcr::STATUS_TARGET_SUBMITTED, $ipcr->fresh()->status);
    }
}
