<?php

namespace Tests\Feature\Sos;

use App\Models\Sos\SosAlert;
use App\Models\Sos\SosEscalationTier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ProcessSosEscalationsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_escalates_overdue_alerts(): void
    {
        Queue::fake();

        SosEscalationTier::create(['alert_type' => 'general', 'order' => 1, 'timeout_minutes' => 5, 'channels' => ['in_app'], 'notify_external' => false]);
        SosEscalationTier::create(['alert_type' => 'general', 'order' => 2, 'timeout_minutes' => null, 'channels' => ['in_app'], 'notify_external' => true]);

        $alert = SosAlert::create([
            'triggerable_type' => User::class, 'triggerable_id' => User::factory()->create()->id,
            'alert_type' => 'general', 'status' => 'triggered', 'current_tier_order' => 1,
            'triggered_at' => now()->subMinutes(10),
        ]);
        $alert->events()->create(['type' => 'triggered', 'actor_type' => null, 'actor_id' => null, 'payload' => null, 'created_at' => now()->subMinutes(10)]);

        $this->artisan('sos:process-escalations')
            ->expectsOutputToContain('Escalated 1 SOS alert(s).')
            ->assertExitCode(0);

        $this->assertSame('escalated', $alert->fresh()->status);
    }
}
