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

    public function test_store_creates_ipcr_for_current_semester_period(): void
    {
        $user = $this->actingUserWithPermission();
        FiscalPeriod::factory()->create(['cadence' => 'semester', 'is_current' => true]);

        $this->actingAs($user)->post('/spms/ipcr')->assertRedirect();

        $this->assertDatabaseHas('spms_ipcrs', ['user_id' => $user->id]);
    }

    public function test_store_redirects_to_existing_ipcr_instead_of_duplicating(): void
    {
        $user = $this->actingUserWithPermission();
        $period = FiscalPeriod::factory()->create(['cadence' => 'semester', 'is_current' => true]);
        $existing = Ipcr::factory()->create(['user_id' => $user->id, 'fiscal_period_id' => $period->id]);

        $this->actingAs($user)->post('/spms/ipcr')->assertRedirect(route('spms.ipcr.show', $existing->id));

        $this->assertSame(1, Ipcr::where('user_id', $user->id)->count());
    }

    public function test_store_errors_when_no_current_semester_period_exists(): void
    {
        $user = $this->actingUserWithPermission();

        $this->actingAs($user)->post('/spms/ipcr')->assertSessionHasErrors('fiscal_period');
    }
}
