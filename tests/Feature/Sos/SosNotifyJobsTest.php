<?php

namespace Tests\Feature\Sos;

use App\Jobs\Sos\NotifySosEmergencyContact;
use App\Jobs\Sos\NotifySosResponders;
use App\Models\HR\EmployeeProfile;
use App\Models\Sos\SosAlert;
use App\Models\Sos\SosEscalationTier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SosNotifyJobsTest extends TestCase
{
    use RefreshDatabase;

    public function test_notify_sos_responders_job_dispatches_tier_notifications(): void
    {
        config(['services.sms_gate.url' => 'https://sms.test/send', 'services.sms_gate.username' => 'u', 'services.sms_gate.password' => 'p']);
        Http::fake(['sms.test/*' => Http::response(['ok' => true], 200)]);

        $user = User::factory()->create();
        EmployeeProfile::create(['user_id' => $user->id, 'mobile_number' => '09171234567']);
        $tier = SosEscalationTier::create(['alert_type' => 'medical', 'order' => 1, 'timeout_minutes' => 10, 'channels' => ['sms'], 'notify_external' => false]);
        $tier->users()->attach($user->id);

        $alert = SosAlert::create([
            'triggerable_type' => User::class, 'triggerable_id' => User::factory()->create()->id,
            'alert_type' => 'medical', 'status' => 'triggered', 'current_tier_order' => 1, 'triggered_at' => now(),
        ]);

        (new NotifySosResponders($alert->id, $tier->id))->handle(app(\App\Services\Sos\SosNotificationDispatchService::class));

        $this->assertDatabaseHas('sos_notification_logs', ['sos_alert_id' => $alert->id, 'recipient_id' => $user->id]);
    }

    public function test_notify_sos_emergency_contact_job_notifies_guardian(): void
    {
        config(['services.sms_gate.url' => 'https://sms.test/send', 'services.sms_gate.username' => 'u', 'services.sms_gate.password' => 'p']);
        Http::fake(['sms.test/*' => Http::response(['ok' => true], 200)]);

        $user = User::factory()->create();
        EmployeeProfile::create(['user_id' => $user->id, 'emergency_contact_name' => 'Contact', 'emergency_contact_phone' => '09170001111']);

        $alert = SosAlert::create([
            'triggerable_type' => User::class, 'triggerable_id' => $user->id,
            'alert_type' => 'general', 'status' => 'triggered', 'current_tier_order' => 1, 'triggered_at' => now(),
        ]);

        (new NotifySosEmergencyContact($alert->id))->handle(app(\App\Services\Sos\SosNotificationDispatchService::class));

        $this->assertDatabaseHas('sos_notification_logs', ['sos_alert_id' => $alert->id, 'recipient_type' => 'user_emergency_contact']);
    }
}
