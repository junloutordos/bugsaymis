<?php

namespace Tests\Feature\IPCRV2;

use App\Models\IPCRRatingPeriod;
use App\Models\IPCRV2\IpcrV2Record;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\IPCRV2\IpcrV2WorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HRIpcrV2ControllerTest extends TestCase
{
    use RefreshDatabase;

    private function hrUser(): User
    {
        $role = Role::create(['name' => 'HR']);
        $ids = collect(['ipcr.v2.view', 'ipcr.v2.monitor', 'ipcr.v2.admin'])
            ->map(fn ($name) => Permission::firstOrCreate(['name' => $name], ['module' => 'IPCR V2', 'description' => 'x'])->id);
        $role->permissions()->attach($ids);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_hr_can_batch_submit_rated_records_to_pmt(): void
    {
        $hr = $this->hrUser();
        $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
        $employeeA = User::factory()->create();
        $employeeB = User::factory()->create();
        $recordA = IpcrV2Record::create(['user_id' => $employeeA->id, 'rating_period_id' => $period->id, 'status' => IpcrV2WorkflowService::STATUS_SUBMITTED_HR]);
        $recordB = IpcrV2Record::create(['user_id' => $employeeB->id, 'rating_period_id' => $period->id, 'status' => IpcrV2WorkflowService::STATUS_SUBMITTED_HR]);

        $response = $this->actingAs($hr)->post(route('hr-ipcr-v2.batchSubmitToPMT'), [
            'ids' => [$recordA->id, $recordB->id],
        ]);

        $response->assertRedirect();
        $this->assertSame(IpcrV2WorkflowService::STATUS_SUBMITTED_PMT, $recordA->fresh()->status);
        $this->assertSame(IpcrV2WorkflowService::STATUS_SUBMITTED_PMT, $recordB->fresh()->status);
    }
}
