<?php

namespace Tests\Feature\SPMS;

use App\Models\Permission;
use App\Models\Role;
use App\Models\SPMS\FiscalPeriod;
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

    public function test_stores_a_fiscal_period(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/spms/admin/fiscal-periods', [
            'cadence' => 'annual',
            'fiscal_year' => 2026,
            'label' => 'FY 2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ])->assertRedirect();

        $this->assertDatabaseHas('spms_fiscal_periods', ['label' => 'FY 2026', 'is_current' => false]);
    }

    public function test_marking_a_fiscal_period_current_unmarks_others_of_same_cadence(): void
    {
        $admin = $this->admin();
        $existingCurrent = FiscalPeriod::factory()->create(['cadence' => 'annual', 'is_current' => true]);

        $this->actingAs($admin)->post('/spms/admin/fiscal-periods', [
            'cadence' => 'annual',
            'fiscal_year' => 2027,
            'label' => 'FY 2027',
            'start_date' => '2027-01-01',
            'end_date' => '2027-12-31',
            'is_current' => true,
        ])->assertRedirect();

        $this->assertFalse((bool) $existingCurrent->fresh()->is_current);
        $this->assertDatabaseHas('spms_fiscal_periods', ['label' => 'FY 2027', 'is_current' => true]);
    }

    public function test_marking_current_does_not_affect_other_cadences(): void
    {
        $admin = $this->admin();
        $semesterCurrent = FiscalPeriod::factory()->create(['cadence' => 'semester', 'is_current' => true]);

        $this->actingAs($admin)->post('/spms/admin/fiscal-periods', [
            'cadence' => 'annual',
            'fiscal_year' => 2027,
            'label' => 'FY 2027',
            'start_date' => '2027-01-01',
            'end_date' => '2027-12-31',
            'is_current' => true,
        ])->assertRedirect();

        $this->assertTrue((bool) $semesterCurrent->fresh()->is_current);
    }
}
