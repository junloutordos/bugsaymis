<?php

namespace Tests\Feature\Mobile;

use App\Models\Sos\SosEscalationTier;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StudentSosTriggerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Under PHPUnit's TestCase HTTP-kernel path (not real nginx/php-fpm
        // traffic — confirmed via a live curl probe against an existing,
        // already-shipped student mobile endpoint, which returns 200),
        // Keepsuit\LaravelOpenTelemetry's TraceRequestMiddleware calls
        // collectUserContext(Authenticatable $user) with our Student model,
        // which does NOT implement Authenticatable and is not meant to
        // (it's a deliberately guarded, read-only legacy-table model — see
        // App\Models\Student). This throws a TypeError and 500s ANY
        // Student-authenticated request made through a Feature test,
        // regardless of which endpoint. Disabling user-context collection
        // for this test class sidesteps the test-only artifact without
        // touching production code or config.
        config(['opentelemetry.user_context' => false]);
    }

    /**
     * Student does not implement Authenticatable, so Sanctum::actingAs()
     * (which calls the guard's strictly-typed setUser()) fatals with a
     * TypeError. Issue a real token instead — this is also what actually
     * exercises the auth:sanctum middleware the way a live request would.
     */
    private function tokenFor(Student $student): string
    {
        return $student->createToken('test')->plainTextToken;
    }

    private function makeStudent(): Student
    {
        $id = DB::table('students')->insertGetId(['lastname' => 'Santos', 'firstname' => 'Liza', 'status' => 'active']);

        return Student::find($id);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->postJson('/api/mobile/student/portal/sos/trigger', ['alert_type' => 'medical'])
            ->assertStatus(401);
    }

    public function test_config_returns_hold_and_countdown_seconds(): void
    {
        $token = $this->tokenFor($this->makeStudent());

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/mobile/student/portal/sos/config')
            ->assertOk()
            ->assertJsonStructure(['hold_confirm_seconds', 'countdown_seconds', 'emergency_hotline_number']);
    }

    public function test_trigger_creates_an_alert_for_the_authenticated_student(): void
    {
        SosEscalationTier::create(['alert_type' => 'medical', 'order' => 1, 'timeout_minutes' => 10, 'channels' => ['in_app'], 'notify_external' => false]);
        $student = $this->makeStudent();
        $token = $this->tokenFor($student);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/mobile/student/portal/sos/trigger', [
                'alert_type' => 'medical',
                'is_silent' => false,
            ]);

        $response->assertStatus(201)->assertJson(['blocked' => false]);
        $this->assertDatabaseHas('sos_alerts', [
            'triggerable_type' => Student::class,
            'triggerable_id' => $student->id,
            'alert_type' => 'medical',
        ]);
    }

    public function test_invalid_alert_type_is_rejected(): void
    {
        $token = $this->tokenFor($this->makeStudent());

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/mobile/student/portal/sos/trigger', ['alert_type' => 'not_a_type'])
            ->assertStatus(422);
    }
}
