<?php

namespace Tests\Feature\IPCRV2;

use App\Models\IPCRRatingPeriod;
use App\Models\IPCRV2\IpcrV2Record;
use App\Models\Role;
use App\Models\User;
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
}
