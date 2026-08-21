<?php

namespace Tests\Feature\Sos;

use App\Models\Role;
use App\Models\Sos\SosAlert;
use App\Models\Sos\SosAlertEvent;
use App\Models\Sos\SosEscalationTier;
use App\Models\Sos\SosExternalContact;
use App\Models\Sos\SosNotificationLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SosModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_sos_alert_relations_and_false_alarm_count(): void
    {
        $user = User::factory()->create();

        $alert = SosAlert::create([
            'triggerable_type'   => User::class,
            'triggerable_id'     => $user->id,
            'alert_type'         => 'medical',
            'is_silent'          => false,
            'status'             => 'false_alarm',
            'current_tier_order' => 1,
            'triggered_at'       => now(),
        ]);

        SosAlertEvent::create([
            'sos_alert_id' => $alert->id,
            'type'         => 'triggered',
            'actor_type'   => User::class,
            'actor_id'     => $user->id,
            'payload'      => ['alert_type' => 'medical'],
        ]);

        $this->assertTrue($alert->triggerable->is($user));
        $this->assertCount(1, $alert->events);
        $this->assertSame(1, SosAlert::falseAlarmCount(User::class, $user->id, 30));
    }

    public function test_escalation_tier_role_and_explicit_users(): void
    {
        $role = Role::firstOrCreate(['name' => 'Security Guard']);
        $tier = SosEscalationTier::create([
            'alert_type'      => 'security',
            'order'           => 1,
            'role_id'         => $role->id,
            'timeout_minutes' => 10,
            'channels'        => ['in_app', 'sms'],
            'notify_external' => false,
        ]);
        $user = User::factory()->create();
        $tier->users()->attach($user->id);

        $this->assertTrue($tier->role->is($role));
        $this->assertTrue($tier->users->contains($user));
        $this->assertSame(['in_app', 'sms'], $tier->channels);
    }

    public function test_external_contact_and_notification_log(): void
    {
        $contact = SosExternalContact::create([
            'name'        => 'Butuan City BFP',
            'phone'       => '+639170000000',
            'alert_types' => ['fire_disaster'],
            'channel'     => 'sms',
            'active'      => true,
        ]);

        $this->assertSame(['fire_disaster'], $contact->alert_types);
        $this->assertTrue($contact->active);

        $alert = SosAlert::create([
            'triggerable_type'   => User::class,
            'triggerable_id'     => User::factory()->create()->id,
            'alert_type'         => 'fire_disaster',
            'status'             => 'triggered',
            'current_tier_order' => 1,
            'triggered_at'       => now(),
        ]);

        $log = SosNotificationLog::create([
            'sos_alert_id'    => $alert->id,
            'channel'         => 'sms',
            'recipient_type'  => 'external_contact',
            'recipient_id'    => $contact->id,
            'recipient_label' => $contact->name,
            'sent'            => true,
            'sent_at'         => now(),
        ]);

        $this->assertTrue($log->alert->is($alert));
    }
}
