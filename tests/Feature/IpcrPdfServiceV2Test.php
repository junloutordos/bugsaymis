<?php

namespace Tests\Feature;

use App\Models\PM2\EmployeeIpcrPlanV2;
use App\Models\PM2\EmployeeIpcrV2;
use App\Models\PM2\IpcrRatingPeriodV2;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IpcrPdfServiceV2Test extends TestCase
{
    use RefreshDatabase;

    public function test_pdf_route_returns_a_pdf_response(): void
    {
        $user = User::factory()->create();
        $permission = Permission::firstOrCreate(['name' => 'ipcr.v2.view'], ['module' => 'PM V2', 'description' => 'ipcr.v2.view']);
        $role = Role::create(['name' => 'PmV2Viewer_'.uniqid()]);
        $role->permissions()->attach($permission->id);
        $user->roles()->attach($role->id);

        $period = IpcrRatingPeriodV2::create(['label' => 'Jul-Dec 2026', 'year' => 2026]);
        $ipcr = EmployeeIpcrV2::create([
            'user_id' => $user->id, 'rating_period_id' => $period->id, 'title' => 'Test', 'status' => 'New Target',
        ]);
        EmployeeIpcrPlanV2::create(['ipcr_id' => $ipcr->id, 'function_type' => 'core', 'weight_percent' => 50, 'individual_target' => 'Math 1']);

        $response = $this->actingAs($user)->get(route('pm2.employee-ipcr.pdf', $ipcr->id));

        $response->assertOk();
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_a_different_employee_cannot_download_someone_elses_pdf(): void
    {
        $owner = User::factory()->create();
        $otherEmployee = User::factory()->create();
        $permission = Permission::firstOrCreate(['name' => 'ipcr.v2.view'], ['module' => 'PM V2', 'description' => 'ipcr.v2.view']);
        $role = Role::create(['name' => 'PmV2Viewer_'.uniqid()]);
        $role->permissions()->attach($permission->id);
        $otherEmployee->roles()->attach($role->id);

        $period = IpcrRatingPeriodV2::create(['label' => 'Jul-Dec 2026', 'year' => 2026]);
        $ipcr = EmployeeIpcrV2::create([
            'user_id' => $owner->id, 'rating_period_id' => $period->id, 'title' => 'Test', 'status' => 'New Target',
        ]);

        $this->actingAs($otherEmployee)->get(route('pm2.employee-ipcr.pdf', $ipcr->id))->assertForbidden();
    }
}
