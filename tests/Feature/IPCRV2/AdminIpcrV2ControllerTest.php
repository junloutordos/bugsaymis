<?php

namespace Tests\Feature\IPCRV2;

use App\Models\IPCRRatingPeriod;
use App\Models\IPCRV2\IpcrV2Record;
use App\Models\Role;
use App\Models\User;
use App\Services\IPCRV2\IpcrV2WorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminIpcrV2ControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_sees_every_record_unscoped(): void
    {
        $adminRole = Role::create(['name' => 'Administrator']);
        $admin = User::factory()->create();
        $admin->roles()->attach($adminRole->id);

        $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
        IpcrV2Record::create(['user_id' => User::factory()->create()->id, 'rating_period_id' => $period->id]);
        IpcrV2Record::create(['user_id' => User::factory()->create()->id, 'rating_period_id' => $period->id]);

        $response = $this->actingAs($admin)->get(route('admin-ipcr-v2.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->has('records', 2));
    }

    public function test_non_administrator_is_forbidden(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('admin-ipcr-v2.index'))->assertForbidden();
    }

    public function test_reopen_requires_a_reason(): void
    {
        $adminRole = Role::create(['name' => 'Administrator']);
        $admin = User::factory()->create();
        $admin->roles()->attach($adminRole->id);

        $employee = User::factory()->create();
        $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
        $record = IpcrV2Record::create([
            'user_id' => $employee->id, 'rating_period_id' => $period->id,
            'status' => IpcrV2WorkflowService::STATUS_DIRECTOR_SIGNED,
            'locked_at' => now(),
        ]);

        $response = $this->actingAs($admin)->post(route('admin-ipcr-v2.reopen', $record->id));
        $response->assertSessionHasErrors('reason');
    }

    public function test_reopen_reverts_status_and_clears_lock(): void
    {
        $adminRole = Role::create(['name' => 'Administrator']);
        $admin = User::factory()->create();
        $admin->roles()->attach($adminRole->id);

        $employee = User::factory()->create();
        $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
        $record = IpcrV2Record::create([
            'user_id' => $employee->id, 'rating_period_id' => $period->id,
            'status' => IpcrV2WorkflowService::STATUS_DIRECTOR_SIGNED,
            'locked_at' => now(), 'locked_by_id' => $employee->id,
        ]);

        $response = $this->actingAs($admin)->post(route('admin-ipcr-v2.reopen', $record->id), ['reason' => 'Wrong rating entered.']);

        $response->assertRedirect();
        $fresh = $record->fresh();
        $this->assertSame(IpcrV2WorkflowService::STATUS_SUBMITTED_PMT, $fresh->status);
        $this->assertNull($fresh->locked_at);
        $this->assertSame('Wrong rating entered.', $fresh->reopen_reason);
        $this->assertSame('reopened', $fresh->statusLogs->last()->action_type);
    }

    public function test_reopen_rejects_a_non_finalized_record(): void
    {
        $adminRole = Role::create(['name' => 'Administrator']);
        $admin = User::factory()->create();
        $admin->roles()->attach($adminRole->id);

        $employee = User::factory()->create();
        $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
        $record = IpcrV2Record::create([
            'user_id' => $employee->id, 'rating_period_id' => $period->id,
            'status' => IpcrV2WorkflowService::STATUS_FOR_REVIEW,
        ]);

        $response = $this->actingAs($admin)->post(route('admin-ipcr-v2.reopen', $record->id), ['reason' => 'x']);
        $response->assertStatus(422);
    }
}
