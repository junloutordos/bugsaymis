<?php

namespace Tests\Feature\IPCRV2;

use App\Models\EmployeeFunction;
use App\Models\IPCRRatingPeriod;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeIpcrV2ControllerTest extends TestCase
{
    use RefreshDatabase;

    private function employee(): User
    {
        $role = Role::create(['name' => 'Faculty']);
        $ids = collect(['ipcr.v2.view', 'ipcr.v2.create', 'ipcr.v2.update', 'ipcr.v2.submit'])
            ->map(fn ($name) => Permission::firstOrCreate(['name' => $name], ['module' => 'IPCR V2', 'description' => 'x'])->id);
        $role->permissions()->attach($ids);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_generate_targets_creates_an_ipcr_v2_record(): void
    {
        $employee = $this->employee();
        $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
        EmployeeFunction::create(['user_id' => $employee->id, 'function_type' => 'core', 'source_type' => 'manual', 'label' => 'Subject 1', 'weight_percent' => 100]);

        $response = $this->actingAs($employee)->post(route('employee-ipcr-v2.generateTargets'), [
            'rating_period_id' => $period->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('ipcr_v2_records', ['user_id' => $employee->id, 'rating_period_id' => $period->id]);
    }

    public function test_show_is_scoped_to_owner_by_default(): void
    {
        $employee = $this->employee();
        $other = User::factory()->create();
        $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
        $record = \App\Models\IPCRV2\IpcrV2Record::create(['user_id' => $other->id, 'rating_period_id' => $period->id]);

        // Owner check happens on mutating routes, not show() (matches v1 —
        // DC/HR/PMT also need to view). Confirm submitReview rejects a non-owner instead.
        $response = $this->actingAs($employee)->post(route('employee-ipcr-v2.submitReview', $record->id));
        $response->assertForbidden();
    }
}
