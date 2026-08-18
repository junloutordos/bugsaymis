<?php

namespace Tests\Feature;

use App\Models\AgencyOutcome;
use App\Models\EmployeeIPCR;
use App\Models\IPCRRatingPeriod;
use App\Models\Permission;
use App\Models\PerformanceIndicator;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkDistributionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IPCRShowOutcomeParentEagerLoadTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $role = Role::create(['name' => 'Administrator']);
        $admin->roles()->attach($role->id);

        return $admin;
    }

    private function userWithIpcrView(): User
    {
        $permission = Permission::firstOrCreate(
            ['name' => 'ipcr.view'],
            ['module' => 'IPCR', 'description' => 'ipcr.view'],
        );
        $role = Role::create(['name' => 'IpcrViewer_'.uniqid()]);
        $role->permissions()->attach($permission->id);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        return $user;
    }

    private function ipcrWithChildOutcomePlan(): EmployeeIPCR
    {
        $period = IPCRRatingPeriod::create(['label' => 'FY 2026', 'year' => 2026]);
        $parent = AgencyOutcome::create(['outcome' => 'A. STEM', 'function_type' => 'Strategic Functions']);
        $child = AgencyOutcome::create(['outcome' => 'A. STEM', 'sub_outcome' => 'A.1', 'function_type' => 'Strategic Functions', 'parent_id' => $parent->id]);
        $indicator = PerformanceIndicator::create(['agency_outcome_id' => $child->id, 'description' => 'Indicator 1', 'target' => '100%']);
        $plan = WorkDistributionPlan::create(['performance_indicator_id' => $indicator->id]);

        $employee = $this->userWithIpcrView();
        $ipcr = EmployeeIPCR::create([
            'user_id' => $employee->id,
            'rating_period_id' => $period->id,
            'rating_period' => $period->label,
            'title' => 'Test IPCR',
            'status' => 'Submitted for Rating',
        ]);
        $ipcr->plans()->attach($plan->id);

        return $ipcr;
    }

    public function test_employee_ipcr_show_exposes_agency_outcome_parent(): void
    {
        $ipcr = $this->ipcrWithChildOutcomePlan();

        $response = $this->actingAs($ipcr->user)->get(route('employee-ipcr.show', $ipcr->id));

        $response->assertInertia(fn ($page) => $page
            ->where('ipcr.plans.0.performance_indicator.agency_outcome.parent.outcome', 'A. STEM')
        );
    }

    public function test_admin_ipcr_show_exposes_agency_outcome_parent(): void
    {
        $admin = $this->admin();
        $ipcr = $this->ipcrWithChildOutcomePlan();

        $response = $this->actingAs($admin)->get(route('admin-ipcr.show', $ipcr->id));

        $response->assertInertia(fn ($page) => $page
            ->where('ipcr.plans.0.performance_indicator.agency_outcome.parent.outcome', 'A. STEM')
        );
    }
}
