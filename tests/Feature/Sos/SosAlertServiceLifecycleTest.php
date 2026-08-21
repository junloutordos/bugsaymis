<?php

namespace Tests\Feature\Sos;

use App\Models\Role;
use App\Models\Sos\SosAlert;
use App\Models\User;
use App\Services\Sos\SosAlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SosAlertServiceLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function alert(array $overrides = []): SosAlert
    {
        return SosAlert::create(array_merge([
            'triggerable_type'   => User::class,
            'triggerable_id'     => User::factory()->create()->id,
            'alert_type'         => 'medical',
            'status'             => 'triggered',
            'current_tier_order' => 1,
            'triggered_at'       => now(),
        ], $overrides));
    }

    public function test_acknowledge_sets_status_and_logs_event(): void
    {
        $alert = $this->alert();
        $responder = User::factory()->create();

        app(SosAlertService::class)->acknowledge($alert, $responder);

        $this->assertSame('acknowledged', $alert->fresh()->status);
        $this->assertDatabaseHas('sos_alert_events', ['sos_alert_id' => $alert->id, 'type' => 'acknowledged', 'actor_id' => $responder->id]);
    }

    public function test_verify_keeps_alert_active_for_further_escalation(): void
    {
        $alert = $this->alert();
        $responder = User::factory()->create();

        app(SosAlertService::class)->verify($alert, $responder, 'Confirmed real emergency');

        $this->assertSame('verified', $alert->fresh()->status);
    }

    public function test_false_alarm_requires_reason_and_records_it(): void
    {
        $alert = $this->alert();
        $responder = User::factory()->create();

        app(SosAlertService::class)->markFalseAlarm($alert, $responder, 'Accidental tap, confirmed with student');

        $this->assertSame('false_alarm', $alert->fresh()->status);
        $this->assertDatabaseHas('sos_alert_events', [
            'sos_alert_id' => $alert->id, 'type' => 'false_alarm', 'actor_id' => $responder->id,
        ]);
    }

    public function test_repeat_false_alarms_notify_administrators(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['name' => 'Administrator'])->id);
        $triggerable = User::factory()->create();
        $responder = User::factory()->create();

        // 2 prior false alarms + this one crosses the default threshold of 3
        for ($i = 0; $i < 2; $i++) {
            $this->alert(['triggerable_id' => $triggerable->id, 'status' => 'false_alarm']);
        }
        $alert = $this->alert(['triggerable_id' => $triggerable->id]);

        app(SosAlertService::class)->markFalseAlarm($alert, $responder, 'Another accidental trigger');

        $this->assertDatabaseHas('notifications', ['notifiable_id' => $admin->id]);
    }

    public function test_resolve_sets_resolution_fields(): void
    {
        $alert = $this->alert(['status' => 'verified']);
        $responder = User::factory()->create();

        app(SosAlertService::class)->resolve($alert, $responder, 'Handled by campus nurse');

        $fresh = $alert->fresh();
        $this->assertSame('resolved', $fresh->status);
        $this->assertSame($responder->id, $fresh->resolved_by);
        $this->assertSame('Handled by campus nurse', $fresh->resolution_notes);
        $this->assertNotNull($fresh->resolved_at);
    }

    public function test_resolve_rejects_already_false_alarm_alerts(): void
    {
        $alert = $this->alert(['status' => 'false_alarm']);
        $responder = User::factory()->create();

        $this->expectException(\RuntimeException::class);
        app(SosAlertService::class)->resolve($alert, $responder);
    }
}
