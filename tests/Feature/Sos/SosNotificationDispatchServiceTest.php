<?php

namespace Tests\Feature\Sos;

use App\Models\Role;
use App\Models\Sos\SosAlert;
use App\Models\Sos\SosEscalationTier;
use App\Models\Sos\SosExternalContact;
use App\Models\HR\EmployeeProfile;
use App\Models\User;
use App\Services\Sos\SosNotificationDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SosNotificationDispatchServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_notify_tier_sms_and_emails_role_and_explicit_responders_and_external_contacts(): void
    {
        config(['services.sms_gate.url' => 'https://sms.test/send', 'services.sms_gate.username' => 'u', 'services.sms_gate.password' => 'p']);
        Http::fake(['sms.test/*' => Http::response(['ok' => true], 200)]);
        Mail::fake();

        $role = Role::firstOrCreate(['name' => 'Security Guard']);
        $roleUser = User::factory()->create(['email' => 'guard@crc.pshs.edu.ph']);
        $roleUser->roles()->attach($role->id);
        EmployeeProfile::create(['user_id' => $roleUser->id, 'mobile_number' => '09171234567']);

        $explicitUser = User::factory()->create(['email' => 'drrm@crc.pshs.edu.ph']);
        EmployeeProfile::create(['user_id' => $explicitUser->id, 'mobile_number' => '09179876543']);

        $tier = SosEscalationTier::create([
            'alert_type' => 'fire_disaster', 'order' => 1, 'role_id' => $role->id,
            'timeout_minutes' => 10, 'channels' => ['sms', 'email', 'in_app'], 'notify_external' => true,
        ]);
        $tier->users()->attach($explicitUser->id);

        SosExternalContact::create([
            'name' => 'Butuan BFP', 'phone' => '09170000001', 'alert_types' => ['fire_disaster'], 'channel' => 'sms', 'active' => true,
        ]);
        SosExternalContact::create([
            'name' => 'Wrong Type Contact', 'phone' => '09170000002', 'alert_types' => ['medical'], 'channel' => 'sms', 'active' => true,
        ]);

        $alert = SosAlert::create([
            'triggerable_type' => User::class, 'triggerable_id' => User::factory()->create()->id,
            'alert_type' => 'fire_disaster', 'status' => 'triggered', 'current_tier_order' => 1, 'triggered_at' => now(),
        ]);

        app(SosNotificationDispatchService::class)->notifyTier($alert, $tier);

        Http::assertSentCount(3); // roleUser + explicitUser + the matching external contact
        Mail::assertSent(\App\Mail\SosAlertMail::class, 2); // roleUser + explicitUser

        $this->assertDatabaseHas('sos_notification_logs', ['sos_alert_id' => $alert->id, 'channel' => 'sms', 'recipient_id' => $roleUser->id]);
        $this->assertDatabaseHas('sos_notification_logs', ['sos_alert_id' => $alert->id, 'channel' => 'in_app', 'recipient_id' => $explicitUser->id]);
        $this->assertDatabaseHas('sos_notification_logs', ['sos_alert_id' => $alert->id, 'recipient_type' => 'external_contact']);
        $this->assertDatabaseMissing('sos_notification_logs', ['sos_alert_id' => $alert->id, 'recipient_label' => 'Wrong Type Contact']);
    }

    public function test_notify_tier_skips_sms_for_responder_without_mobile_number(): void
    {
        $role = Role::firstOrCreate(['name' => 'Nurse']);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);
        EmployeeProfile::create(['user_id' => $user->id]); // no mobile_number

        $tier = SosEscalationTier::create([
            'alert_type' => 'medical', 'order' => 1, 'role_id' => $role->id,
            'timeout_minutes' => 10, 'channels' => ['sms'], 'notify_external' => false,
        ]);

        $alert = SosAlert::create([
            'triggerable_type' => User::class, 'triggerable_id' => User::factory()->create()->id,
            'alert_type' => 'medical', 'status' => 'triggered', 'current_tier_order' => 1, 'triggered_at' => now(),
        ]);

        app(SosNotificationDispatchService::class)->notifyTier($alert, $tier);

        $this->assertDatabaseMissing('sos_notification_logs', ['sos_alert_id' => $alert->id, 'channel' => 'sms']);
    }
}
