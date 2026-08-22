<?php

namespace Tests\Feature\Mobile;

use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StudentProfileChangeRequestApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // See StudentSosTriggerTest for why this is needed under PHPUnit's
        // TestCase HTTP-kernel path specifically (not real traffic).
        config(['opentelemetry.user_context' => false]);
    }

    private function tokenFor(Student $student): string
    {
        return $student->createToken('test')->plainTextToken;
    }

    private function makeStudent(): Student
    {
        $id = DB::table('students')->insertGetId([
            'lastname' => 'Dizon', 'firstname' => 'Marco', 'status' => 'active', 'contactno1' => '09170000000',
        ]);

        return Student::find($id);
    }

    public function test_show_returns_current_values_and_editable_fields(): void
    {
        $token = $this->tokenFor($this->makeStudent());

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/mobile/student/portal/profile-update');

        $response->assertOk()->assertJsonStructure(['current', 'editable_fields', 'pending']);
        $this->assertSame('09170000000', $response->json('current.contactno1'));
        $this->assertNull($response->json('pending'));
    }

    public function test_store_submits_a_pending_request(): void
    {
        $token = $this->tokenFor($this->makeStudent());

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/mobile/student/portal/profile-update', ['changes' => ['contactno1' => '09171234567']])
            ->assertStatus(201);

        $this->assertDatabaseHas('student_profile_change_requests', ['status' => 'pending']);
    }

    public function test_store_rejects_a_disallowed_field(): void
    {
        $token = $this->tokenFor($this->makeStudent());

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/mobile/student/portal/profile-update', ['changes' => ['lrn' => '111111111111']])
            ->assertStatus(422);
    }

    public function test_show_surfaces_a_pending_request_after_submission(): void
    {
        $student = $this->makeStudent();
        $token = $this->tokenFor($student);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/mobile/student/portal/profile-update', ['changes' => ['contactno1' => '09171234567']]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/mobile/student/portal/profile-update');

        $this->assertSame('09171234567', $response->json('pending.requested_changes.contactno1'));
    }
}
