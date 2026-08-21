<?php

namespace Tests\Feature\Sos;

use App\Models\Sos\SosEscalationTier;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentPortalSosTriggerTest extends TestCase
{
    use RefreshDatabase;

    public function test_logged_in_student_can_trigger_sos(): void
    {
        SosEscalationTier::create(['alert_type' => 'security', 'order' => 1, 'timeout_minutes' => 10, 'channels' => ['in_app'], 'notify_external' => false]);
        // Student is guarded (read-only legacy table) — seed via raw insert,
        // matching this codebase's existing test convention.
        $studentId = \Illuminate\Support\Facades\DB::table('students')->insertGetId([
            'pisaysystemID' => 'TEST-002', 'firstname' => 'Test', 'lastname' => 'Student Two',
        ]);

        $response = $this->withSession(['student_pisaysystemID' => 'TEST-002'])
            ->postJson('/student-portal/sos/trigger', ['alert_type' => 'security', 'is_silent' => true]);

        $response->assertCreated()->assertJson(['blocked' => false]);
        $this->assertDatabaseHas('sos_alerts', [
            'triggerable_type' => Student::class, 'triggerable_id' => $studentId, 'is_silent' => true,
        ]);
    }

    public function test_logged_out_visitor_cannot_trigger(): void
    {
        $this->postJson('/student-portal/sos/trigger', ['alert_type' => 'general'])
            ->assertRedirect(route('student-portal.login'));
    }
}
