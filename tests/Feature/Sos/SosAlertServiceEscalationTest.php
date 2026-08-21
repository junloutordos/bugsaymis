<?php

namespace Tests\Feature\Sos;

use App\Jobs\Sos\NotifySosResponders;
use App\Models\Sos\SosAlert;
use App\Models\Sos\SosEscalationTier;
use App\Models\User;
use App\Services\Sos\SosAlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SosAlertServiceEscalationTest extends TestCase
{
    use RefreshDatabase;

    public function test_alert_past_tier_timeout_advances_to_next_tier(): void
    {
        Queue::fake();

        SosEscalationTier::create(['alert_type' => 'security', 'order' => 1, 'timeout_minutes' => 10, 'channels' => ['in_app'], 'notify_external' => false]);
        $tier2 = SosEscalationTier::create(['alert_type' => 'security', 'order' => 2, 'timeout_minutes' => null, 'channels' => ['in_app', 'sms'], 'notify_external' => true]);

        $alert = SosAlert::create([
            'triggerable_type' => User::class, 'triggerable_id' => User::factory()->create()->id,
            'alert_type' => 'security', 'status' => 'triggered', 'current_tier_order' => 1,
            'triggered_at' => now()->subMinutes(15),
        ]);
        $alert->events()->create(['type' => 'triggered', 'actor_type' => null, 'actor_id' => null, 'payload' => null, 'created_at' => now()->subMinutes(15)]);

        $count = app(SosAlertService::class)->processEscalations();

        $this->assertSame(1, $count);
        $fresh = $alert->fresh();
        $this->assertSame('escalated', $fresh->status);
        $this->assertSame(2, $fresh->current_tier_order);
        $this->assertDatabaseHas('sos_alert_events', ['sos_alert_id' => $alert->id, 'type' => 'escalated']);
        Queue::assertPushed(NotifySosResponders::class, fn ($job) => $job->tierId === $tier2->id);
    }

    public function test_alert_within_timeout_is_not_escalated(): void
    {
        SosEscalationTier::create(['alert_type' => 'medical', 'order' => 1, 'timeout_minutes' => 10, 'channels' => ['in_app'], 'notify_external' => false]);

        $alert = SosAlert::create([
            'triggerable_type' => User::class, 'triggerable_id' => User::factory()->create()->id,
            'alert_type' => 'medical', 'status' => 'triggered', 'current_tier_order' => 1,
            'triggered_at' => now()->subMinutes(2),
        ]);
        $alert->events()->create(['type' => 'triggered', 'actor_type' => null, 'actor_id' => null, 'payload' => null, 'created_at' => now()->subMinutes(2)]);

        $count = app(SosAlertService::class)->processEscalations();

        $this->assertSame(0, $count);
        $this->assertSame('triggered', $alert->fresh()->status);
    }

    public function test_resolved_alerts_are_never_escalated(): void
    {
        SosEscalationTier::create(['alert_type' => 'general', 'order' => 1, 'timeout_minutes' => 10, 'channels' => ['in_app'], 'notify_external' => false]);

        $alert = SosAlert::create([
            'triggerable_type' => User::class, 'triggerable_id' => User::factory()->create()->id,
            'alert_type' => 'general', 'status' => 'resolved', 'current_tier_order' => 1,
            'triggered_at' => now()->subHours(1), 'resolved_at' => now(),
        ]);

        $count = app(SosAlertService::class)->processEscalations();

        $this->assertSame(0, $count);
    }
}
