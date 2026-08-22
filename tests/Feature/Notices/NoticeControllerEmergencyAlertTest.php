<?php

namespace Tests\Feature\Notices;

use App\Models\Sos\EmergencyAlert;
use App\Models\StudentAttendance\ParentContact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NoticeControllerEmergencyAlertTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Keepsuit\LaravelOpenTelemetry::collectUserContext() type-hints
        // Illuminate\Contracts\Auth\Authenticatable and fatals under
        // PHPUnit's HTTP-kernel path for a ParentContact-authenticated
        // request — confirmed elsewhere in this codebase that this never
        // fires on real traffic (test-environment-only quirk).
        config(['opentelemetry.user_context' => false]);
    }

    public function test_web_pending_includes_active_unacknowledged_emergency_alerts(): void
    {
        $user = User::factory()->create(['account_type' => 'employee']);
        EmergencyAlert::create([
            'title' => 'Lockdown', 'message' => 'Stay indoors', 'severity' => 'critical',
            'audience' => 'all', 'status' => 'active', 'source' => 'manual', 'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->getJson('/notices/pending');

        $titles = collect($response->json('emergency_alerts'))->pluck('title');
        $this->assertTrue($titles->contains('Lockdown'));
    }

    public function test_web_pending_excludes_resolved_emergency_alerts(): void
    {
        $user = User::factory()->create(['account_type' => 'employee']);
        EmergencyAlert::create([
            'title' => 'Resolved One', 'message' => 'Body', 'severity' => 'warning',
            'audience' => 'all', 'status' => 'resolved', 'source' => 'manual', 'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->getJson('/notices/pending');

        $this->assertEmpty($response->json('emergency_alerts'));
    }

    public function test_mobile_parent_pending_includes_matching_audience_emergency_alerts(): void
    {
        $parent = ParentContact::create([
            'name' => 'P', 'email' => 'p@example.com', 'password' => bcrypt('x'), 'status' => 'active',
        ]);
        $token = $parent->createToken('device', ['mobile'])->plainTextToken;
        EmergencyAlert::create([
            'title' => 'Parent Alert', 'message' => 'Body', 'severity' => 'critical',
            'audience' => 'parents', 'status' => 'active', 'source' => 'manual', 'created_by' => User::factory()->create()->id,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/mobile/notices/pending');

        $titles = collect($response->json('emergency_alerts'))->pluck('title');
        $this->assertTrue($titles->contains('Parent Alert'));
    }

    public function test_acknowledge_endpoint_accepts_emergency_alert_type(): void
    {
        $user = User::factory()->create();
        $alert = EmergencyAlert::create([
            'title' => 'X', 'message' => 'Y', 'severity' => 'info',
            'audience' => 'all', 'status' => 'active', 'source' => 'manual', 'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->postJson("/notices/emergency-alert/{$alert->id}/acknowledge")
            ->assertOk();

        $this->assertTrue($alert->fresh()->isAcknowledgedBy($user));
    }
}
