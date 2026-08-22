<?php

namespace Tests\Feature\Mobile;

use App\Models\Sos\SosAlert;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StudentSosStatusEndTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // See StudentSosTriggerTest for why this is required for any
        // Student-authenticated Feature test.
        config(['opentelemetry.user_context' => false]);
    }

    private function tokenFor(Student $student): string
    {
        return $student->createToken('test')->plainTextToken;
    }

    private function makeStudent(): Student
    {
        $id = DB::table('students')->insertGetId(['lastname' => 'Santos', 'firstname' => 'Liza', 'status' => 'active']);

        return Student::find($id);
    }

    private function alertFor(Student $student, array $overrides = []): SosAlert
    {
        return SosAlert::create(array_merge([
            'triggerable_type'   => Student::class,
            'triggerable_id'     => $student->id,
            'alert_type'         => 'medical',
            'status'             => 'triggered',
            'current_tier_order' => 1,
            'triggered_at'       => now(),
        ], $overrides));
    }

    public function test_owner_can_view_their_alert_status(): void
    {
        $student = $this->makeStudent();
        $alert = $this->alertFor($student);
        $token = $this->tokenFor($student);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/mobile/student/portal/sos/{$alert->id}")
            ->assertOk()
            ->assertJson(['id' => $alert->id, 'status' => 'triggered']);
    }

    public function test_a_different_student_cannot_view_someone_elses_alert(): void
    {
        $owner = $this->makeStudent();
        $other = $this->makeStudent();
        $alert = $this->alertFor($owner);
        $token = $this->tokenFor($other);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/mobile/student/portal/sos/{$alert->id}")
            ->assertStatus(403);
    }

    public function test_owner_can_end_an_active_alert(): void
    {
        $student = $this->makeStudent();
        $alert = $this->alertFor($student);
        $token = $this->tokenFor($student);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/mobile/student/portal/sos/{$alert->id}/end")
            ->assertOk()
            ->assertJson(['status' => 'resolved']);

        $this->assertDatabaseHas('sos_alerts', ['id' => $alert->id, 'status' => 'resolved']);
    }

    public function test_a_different_student_cannot_end_someone_elses_alert(): void
    {
        $owner = $this->makeStudent();
        $other = $this->makeStudent();
        $alert = $this->alertFor($owner);
        $token = $this->tokenFor($other);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/mobile/student/portal/sos/{$alert->id}/end")
            ->assertStatus(403);

        $this->assertDatabaseHas('sos_alerts', ['id' => $alert->id, 'status' => 'triggered']);
    }

    public function test_ending_an_already_resolved_alert_returns_409(): void
    {
        $student = $this->makeStudent();
        $alert = $this->alertFor($student, ['status' => 'resolved']);
        $token = $this->tokenFor($student);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/mobile/student/portal/sos/{$alert->id}/end")
            ->assertStatus(409);
    }

    /**
     * Laravel's bare `throttle:max,decay` middleware keys its rate-limit
     * bucket purely by authenticated-user-id (see
     * ThrottleRequests::resolveRequestSignature) — with no route/URI
     * component at all. Without distinct prefixes, the GET status-poll
     * route (throttle:30,1) and this POST end route (throttle:10,1) would
     * share one counter: polling ~11+ times exhausts the shared bucket and
     * 429s the very next end-SOS call, even though the end route's own
     * 10/min quota was never independently exceeded.
     */
    public function test_polling_status_repeatedly_does_not_exhaust_the_end_endpoints_quota(): void
    {
        $student = $this->makeStudent();
        $alert = $this->alertFor($student);
        $token = $this->tokenFor($student);
        $client = $this->withHeader('Authorization', "Bearer {$token}");

        for ($i = 0; $i < 15; $i++) {
            $client->getJson("/api/mobile/student/portal/sos/{$alert->id}")->assertOk();
        }

        $client->postJson("/api/mobile/student/portal/sos/{$alert->id}/end")
            ->assertOk()
            ->assertJson(['status' => 'resolved']);
    }
}
