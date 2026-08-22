<?php

namespace Tests\Feature\Sos;

use App\Jobs\Sos\DispatchEmergencyAlertJob;
use App\Models\Sos\EmergencyAlert;
use App\Models\StudentAttendance\ParentContact;
use App\Models\User;
use App\Services\NoticeAudienceResolver;
use App\Services\StudentAttendance\FcmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DispatchEmergencyAlertJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatches_on_the_dedicated_emergency_queue(): void
    {
        \Illuminate\Support\Facades\Queue::fake();

        $alert = EmergencyAlert::create([
            'title' => 'Test', 'message' => 'Body', 'severity' => 'critical',
            'audience' => 'employees', 'status' => 'active', 'source' => 'manual',
            'created_by' => User::factory()->create()->id,
        ]);

        DispatchEmergencyAlertJob::dispatch($alert->id);

        \Illuminate\Support\Facades\Queue::assertPushedOn('emergency', DispatchEmergencyAlertJob::class);
    }

    public function test_parents_audience_pushes_and_sms_and_emails_all_channels(): void
    {
        config(['services.sms_gate.url' => 'https://sms.test/send', 'services.sms_gate.username' => 'u', 'services.sms_gate.password' => 'p']);
        Http::fake(['sms.test/*' => Http::response(['ok' => true], 200)]);
        Mail::fake();

        $parent = ParentContact::create([
            'name' => 'Parent', 'email' => 'parent@example.com', 'password' => bcrypt('x'),
            'status' => 'active', 'notify_push' => true, 'notify_sms' => true,
            'fcm_device_token' => 'token-x', 'mobile_phone' => '09171234567',
        ]);
        $alert = EmergencyAlert::create([
            'title' => 'Lockdown', 'message' => 'Stay indoors', 'severity' => 'critical',
            'audience' => 'parents', 'status' => 'active', 'source' => 'manual',
            'created_by' => User::factory()->create()->id,
        ]);

        $fcm = $this->mock(FcmService::class);
        $fcm->shouldReceive('send')->once()->with('token-x', 'Lockdown', 'Stay indoors', [
            'type' => 'emergency_alert', 'emergency_alert_id' => (string) $alert->id,
            'title' => 'Lockdown', 'message' => 'Stay indoors',
        ])->andReturn(true);

        (new DispatchEmergencyAlertJob($alert->id))->handle(app(NoticeAudienceResolver::class), $fcm);

        Http::assertSentCount(1); // SMS to the parent's mobile_phone
    }

    public function test_employees_audience_emails_every_resolved_employee(): void
    {
        Mail::fake();

        $employee = User::factory()->create(['account_type' => 'employee', 'status' => 'active']);
        $alert = EmergencyAlert::create([
            'title' => 'Weather Advisory', 'message' => 'Classes suspended.', 'severity' => 'warning',
            'audience' => 'employees', 'status' => 'active', 'source' => 'manual',
            'created_by' => User::factory()->create()->id,
        ]);

        $fcm = $this->mock(FcmService::class);

        (new DispatchEmergencyAlertJob($alert->id))->handle(app(NoticeAudienceResolver::class), $fcm);

        Mail::assertSent(\App\Mail\EmergencyAlertMail::class, fn ($mail) => $mail->hasTo($employee->email));
    }
}
