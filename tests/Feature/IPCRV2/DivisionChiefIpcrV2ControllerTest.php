<?php

namespace Tests\Feature\IPCRV2;

use App\Models\Division;
use App\Models\IPCRRatingPeriod;
use App\Models\IPCRV2\IpcrV2Record;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\IPCRV2\IpcrV2WorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DivisionChiefIpcrV2ControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_division_chief_can_approve_targets_of_their_own_division_employee(): void
    {
        $role = Role::create(['name' => 'DivisionChief']);
        $ids = collect(['ipcr.v2.view', 'ipcr.v2.approve', 'ipcr.v2.monitor'])
            ->map(fn ($name) => Permission::firstOrCreate(['name' => $name], ['module' => 'IPCR V2', 'description' => 'x'])->id);
        $role->permissions()->attach($ids);

        $chief = User::factory()->create();
        $chief->roles()->attach($role->id);
        $division = Division::create(['division_name' => 'CID', 'acronym' => 'CID', 'division_chief_id' => $chief->id]);

        $employee = User::factory()->create(['division_id' => $division->id]);
        $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
        $record = IpcrV2Record::create([
            'user_id' => $employee->id, 'rating_period_id' => $period->id,
            'status' => IpcrV2WorkflowService::STATUS_FOR_REVIEW,
        ]);

        $response = $this->actingAs($chief)->post(route('division-chief-ipcr-v2.approveTargets', $record->id));

        $response->assertRedirect();
        $this->assertSame(IpcrV2WorkflowService::STATUS_TARGETS_APPROVED, $record->fresh()->status);
    }

    public function test_division_chief_cannot_view_an_employee_outside_their_division(): void
    {
        $role = Role::create(['name' => 'DivisionChief']);
        $ids = collect(['ipcr.v2.view', 'ipcr.v2.approve', 'ipcr.v2.monitor'])
            ->map(fn ($name) => Permission::firstOrCreate(['name' => $name], ['module' => 'IPCR V2', 'description' => 'x'])->id);
        $role->permissions()->attach($ids);

        $chief = User::factory()->create();
        $chief->roles()->attach($role->id);
        Division::create(['division_name' => 'CID', 'acronym' => 'CID', 'division_chief_id' => $chief->id]);
        $otherDivision = Division::create(['division_name' => 'SSD', 'acronym' => 'SSD']);

        $otherEmployee = User::factory()->create(['division_id' => $otherDivision->id]);
        $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
        $record = IpcrV2Record::create(['user_id' => $otherEmployee->id, 'rating_period_id' => $period->id]);

        $this->actingAs($chief)->get(route('division-chief-ipcr-v2.show', $record->id))->assertForbidden();
    }

    public function test_disapprove_targets_requires_remarks(): void
    {
        $role = Role::create(['name' => 'DivisionChief']);
        $ids = collect(['ipcr.v2.view', 'ipcr.v2.approve', 'ipcr.v2.monitor'])
            ->map(fn ($name) => Permission::firstOrCreate(['name' => $name], ['module' => 'IPCR V2', 'description' => 'x'])->id);
        $role->permissions()->attach($ids);

        $chief = User::factory()->create();
        $chief->roles()->attach($role->id);
        $division = Division::create(['division_name' => 'CID', 'acronym' => 'CID', 'division_chief_id' => $chief->id]);
        $employee = User::factory()->create(['division_id' => $division->id]);
        $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
        $record = IpcrV2Record::create([
            'user_id' => $employee->id, 'rating_period_id' => $period->id,
            'status' => IpcrV2WorkflowService::STATUS_FOR_REVIEW,
        ]);

        $missing = $this->actingAs($chief)->post(route('division-chief-ipcr-v2.disapproveTargets', $record->id));
        $missing->assertSessionHasErrors('remarks');

        $ok = $this->actingAs($chief)->post(route('division-chief-ipcr-v2.disapproveTargets', $record->id), ['remarks' => 'Please revise.']);
        $ok->assertRedirect();
        $this->assertSame('Returned for Revision', $record->fresh()->status);
        $this->assertSame('Please revise.', $record->fresh()->remarks);
    }

    public function test_submit_to_pmt_bridges_through_rated_when_starting_from_for_rating(): void
    {
        $role = Role::create(['name' => 'DivisionChief']);
        $ids = collect(['ipcr.v2.view', 'ipcr.v2.approve', 'ipcr.v2.monitor'])
            ->map(fn ($name) => Permission::firstOrCreate(['name' => $name], ['module' => 'IPCR V2', 'description' => 'x'])->id);
        $role->permissions()->attach($ids);

        $chief = User::factory()->create();
        $chief->roles()->attach($role->id);
        $division = Division::create(['division_name' => 'CID', 'acronym' => 'CID', 'division_chief_id' => $chief->id]);
        $employee = User::factory()->create(['division_id' => $division->id]);
        $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
        $record = IpcrV2Record::create([
            'user_id' => $employee->id, 'rating_period_id' => $period->id,
            'status' => IpcrV2WorkflowService::STATUS_FOR_RATING,
        ]);

        $response = $this->actingAs($chief)->post(route('division-chief-ipcr-v2.submitToPMT', $record->id));

        $response->assertRedirect();
        $this->assertSame('Submitted to PMT', $record->fresh()->status);
        $actionTypes = $record->fresh()->statusLogs->pluck('action_type')->all();
        $this->assertSame(['rated', 'submitted'], $actionTypes);
    }

    public function test_update_comments_saves_while_mutable(): void
    {
        $role = Role::create(['name' => 'DivisionChief']);
        $ids = collect(['ipcr.v2.view', 'ipcr.v2.approve', 'ipcr.v2.monitor'])
            ->map(fn ($name) => Permission::firstOrCreate(['name' => $name], ['module' => 'IPCR V2', 'description' => 'x'])->id);
        $role->permissions()->attach($ids);

        $chief = User::factory()->create();
        $chief->roles()->attach($role->id);
        $division = Division::create(['division_name' => 'CID', 'acronym' => 'CID', 'division_chief_id' => $chief->id]);
        $employee = User::factory()->create(['division_id' => $division->id]);
        $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
        $record = IpcrV2Record::create(['user_id' => $employee->id, 'rating_period_id' => $period->id]);

        $response = $this->actingAs($chief)->put(route('division-chief-ipcr-v2.updateComments', $record->id), [
            'comments_recommendations' => 'Keep up the good work.',
        ]);

        $response->assertRedirect();
        $this->assertSame('Keep up the good work.', $record->fresh()->comments_recommendations);
    }
}
