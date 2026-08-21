<?php

namespace Tests\Feature\Sos;

use App\Models\HR\EmployeeProfile;
use App\Models\Sos\SosAlert;
use App\Models\Student;
use App\Models\StudentAttendance\ParentContact;
use App\Models\User;
use App\Services\Sos\SosNotificationDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SosEmergencyContactNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_trigger_sms_the_employee_emergency_contact(): void
    {
        config(['services.sms_gate.url' => 'https://sms.test/send', 'services.sms_gate.username' => 'u', 'services.sms_gate.password' => 'p']);
        Http::fake(['sms.test/*' => Http::response(['ok' => true], 200)]);

        $user = User::factory()->create(['name' => 'Juan Dela Cruz']);
        EmployeeProfile::create([
            'user_id' => $user->id,
            'emergency_contact_name' => 'Maria Dela Cruz',
            'emergency_contact_phone' => '09171112222',
        ]);

        $alert = SosAlert::create([
            'triggerable_type' => User::class, 'triggerable_id' => $user->id,
            'alert_type' => 'medical', 'status' => 'triggered', 'current_tier_order' => 1, 'triggered_at' => now(),
        ]);

        app(SosNotificationDispatchService::class)->notifyEmergencyContact($alert);

        Http::assertSentCount(1);
        $this->assertDatabaseHas('sos_notification_logs', [
            'sos_alert_id' => $alert->id, 'channel' => 'sms', 'recipient_type' => 'user_emergency_contact', 'recipient_label' => 'Maria Dela Cruz',
        ]);
    }

    public function test_student_trigger_pushes_and_sms_all_linked_parents(): void
    {
        config(['services.sms_gate.url' => 'https://sms.test/send', 'services.sms_gate.username' => 'u', 'services.sms_gate.password' => 'p']);
        Http::fake(['sms.test/*' => Http::response(['ok' => true], 200)]);

        // Student is deliberately guarded (read-only legacy table) — Eloquent
        // mass-assignment is blocked, so seed it the same way the rest of this
        // codebase's tests do: a raw DB insert, then read back via the model.
        $studentId = \Illuminate\Support\Facades\DB::table('students')->insertGetId([
            'pisaysystemID' => 'TEST-001', 'firstname' => 'Test', 'lastname' => 'Student',
        ]);
        $student = Student::find($studentId);
        $parent = ParentContact::create([
            'name' => 'Parent One', 'email' => 'parent@example.com', 'password' => bcrypt('x'),
            'notify_push' => true, 'notify_sms' => true, 'fcm_device_token' => 'token-abc', 'mobile_phone' => '09173334444',
        ]);
        $parent->students()->attach($student->id, ['relationship' => 'guardian']);

        $alert = SosAlert::create([
            'triggerable_type' => Student::class, 'triggerable_id' => $student->id,
            'alert_type' => 'security', 'status' => 'triggered', 'current_tier_order' => 1, 'triggered_at' => now(),
        ]);

        app(SosNotificationDispatchService::class)->notifyEmergencyContact($alert);

        $this->assertDatabaseHas('sos_notification_logs', ['sos_alert_id' => $alert->id, 'channel' => 'push', 'recipient_id' => $parent->id]);
        $this->assertDatabaseHas('sos_notification_logs', ['sos_alert_id' => $alert->id, 'channel' => 'sms', 'recipient_id' => $parent->id]);
    }
}
