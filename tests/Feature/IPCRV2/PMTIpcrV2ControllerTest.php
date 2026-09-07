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

class PMTIpcrV2ControllerTest extends TestCase
{
    use RefreshDatabase;

    private function pmtUser(): User
    {
        $role = Role::create(['name' => 'PMT']);
        $ids = collect(['ipcr.v2.view', 'ipcr.v2.approve', 'ipcr.v2.monitor'])
            ->map(fn ($name) => Permission::firstOrCreate(['name' => $name], ['module' => 'IPCR V2', 'description' => 'x'])->id);
        $role->permissions()->attach($ids);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_pmt_can_approve_a_submitted_record(): void
    {
        $pmt = $this->pmtUser();
        $employee = User::factory()->create();
        $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
        $record = IpcrV2Record::create([
            'user_id' => $employee->id, 'rating_period_id' => $period->id,
            'status' => IpcrV2WorkflowService::STATUS_SUBMITTED_PMT,
        ]);

        $response = $this->actingAs($pmt)->post(route('pmt-ipcr-v2.approve', $record->id));

        $response->assertRedirect();
        $this->assertSame(IpcrV2WorkflowService::STATUS_PMT_APPROVED, $record->fresh()->status);
    }
}
