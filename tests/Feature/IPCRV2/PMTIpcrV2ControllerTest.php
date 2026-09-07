<?php

namespace Tests\Feature\IPCRV2;

use App\Models\IPCRRatingPeriod;
use App\Models\IPCRV2\IpcrV2Record;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\IPCRV2\IpcrV2WorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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

    public function test_return_for_revision_requires_remarks(): void
    {
        $pmt = $this->pmtUser();
        $employee = User::factory()->create();
        $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
        $record = IpcrV2Record::create([
            'user_id' => $employee->id, 'rating_period_id' => $period->id,
            'status' => IpcrV2WorkflowService::STATUS_SUBMITTED_PMT,
        ]);

        $missing = $this->actingAs($pmt)->post(route('pmt-ipcr-v2.return', $record->id));
        $missing->assertSessionHasErrors('remarks');
        $this->assertSame(IpcrV2WorkflowService::STATUS_SUBMITTED_PMT, $record->fresh()->status);

        $ok = $this->actingAs($pmt)->post(route('pmt-ipcr-v2.return', $record->id), ['remarks' => 'Needs more detail.']);
        $ok->assertRedirect();
        $this->assertSame(IpcrV2WorkflowService::STATUS_PMT_RETURNED, $record->fresh()->status);
        $this->assertSame('Needs more detail.', $record->fresh()->remarks);
    }

    public function test_director_sign_requires_correct_pin_when_pin_is_set(): void
    {
        $pmt = $this->pmtUser();
        $pmt->update(['signature_pin' => Hash::make('123456')]);
        $employee = User::factory()->create();
        $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open', 'is_current' => true]);
        \App\Models\AgencyOutcome::create(['outcome' => 'A']);
        \App\Models\OPCR\OpcrIndicator::create([
            'fiscal_year' => 2026,
            'agency_outcome_id' => \App\Models\AgencyOutcome::first()->id,
            'description' => 'x', 'rating_average' => 4.0,
        ]);
        $record = IpcrV2Record::create([
            'user_id' => $employee->id, 'rating_period_id' => $period->id,
            'status' => IpcrV2WorkflowService::STATUS_PMT_APPROVED,
        ]);
        $record->coreItems()->create(['label' => 'x', 'weight_percent' => 100, 'row_average' => 4.0]);

        $wrong = $this->actingAs($pmt)->post(route('pmt-ipcr-v2.directorSign', $record->id), ['pin' => '000000']);
        $wrong->assertSessionHasErrors('pin');
        $this->assertSame(IpcrV2WorkflowService::STATUS_PMT_APPROVED, $record->fresh()->status);

        $right = $this->actingAs($pmt)->post(route('pmt-ipcr-v2.directorSign', $record->id), ['pin' => '123456']);
        $right->assertRedirect();
        $this->assertSame(IpcrV2WorkflowService::STATUS_DIRECTOR_SIGNED, $record->fresh()->status);
    }
}
