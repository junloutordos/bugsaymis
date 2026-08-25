<?php

namespace Tests\Feature\Sos;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Sos\SosAlert;
use App\Models\Sos\SosAlertEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SosAlertStatsTest extends TestCase
{
    use RefreshDatabase;

    private function responder(): User
    {
        $permission = Permission::firstOrCreate(['name' => 'sos.respond'], ['module' => 'SOS', 'description' => 'x']);
        $role = Role::firstOrCreate(['name' => 'DRRM Coordinator']);
        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);
        return $user;
    }

    public function test_stats_computes_counts_and_averages(): void
    {
        $reporter = User::factory()->create();
        $triggeredAt = now()->subMinutes(30);

        $alert = SosAlert::create([
            'triggerable_type' => User::class, 'triggerable_id' => $reporter->id,
            'alert_type' => 'medical', 'status' => 'resolved', 'current_tier_order' => 1,
            'triggered_at' => $triggeredAt, 'resolved_at' => $triggeredAt->copy()->addMinutes(20),
        ]);

        SosAlertEvent::create([
            'sos_alert_id' => $alert->id, 'type' => 'claimed',
            'actor_type' => User::class, 'actor_id' => $reporter->id,
            'created_at' => $triggeredAt->copy()->addMinutes(5),
        ]);

        $response = $this->actingAs($this->responder())->getJson('/sos/stats');

        // round(5, 1)/round(20, 1) are whole-number floats, which PHP's json_encode
        // serializes as bare integers (5, 20) rather than 5.0/20.0 — assert against
        // the actual wire format, not the PHP-side float value.
        $response->assertOk()
            ->assertJsonPath('by_type.medical', 1)
            ->assertJsonPath('avg_first_claim_minutes', 5)
            ->assertJsonPath('avg_resolution_minutes', 20);
    }
}
