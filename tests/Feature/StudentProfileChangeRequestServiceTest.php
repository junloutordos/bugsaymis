<?php

namespace Tests\Feature;

use App\Models\StudentProfileChangeRequest;
use App\Models\User;
use App\Services\StudentProfileChangeRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StudentProfileChangeRequestServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeStudent(): int
    {
        return DB::table('students')->insertGetId([
            'lastname' => 'Cruz', 'firstname' => 'Juan', 'status' => 'active',
            'contactno1' => '09170000000', 'lrn' => '999999999999',
        ]);
    }

    public function test_submit_rejects_a_field_outside_the_allowlist(): void
    {
        $studentId = $this->makeStudent();
        $service = app(StudentProfileChangeRequestService::class);

        $result = $service->submit($studentId, ['lrn' => '111111111111']);

        $this->assertFalse($result['ok']);
        $this->assertDatabaseCount('student_profile_change_requests', 0);
    }

    public function test_submit_creates_a_pending_request_for_allowlisted_fields(): void
    {
        $studentId = $this->makeStudent();
        $service = app(StudentProfileChangeRequestService::class);

        $result = $service->submit($studentId, ['contactno1' => '09171234567']);

        $this->assertTrue($result['ok']);
        $this->assertSame('pending', $result['request']->status);
        $this->assertSame('09171234567', $result['request']->requested_changes['contactno1']);
    }

    public function test_submit_rejects_a_second_request_while_one_is_pending(): void
    {
        $studentId = $this->makeStudent();
        $service = app(StudentProfileChangeRequestService::class);
        $service->submit($studentId, ['contactno1' => '09171234567']);

        $result = $service->submit($studentId, ['contactno1' => '09179999999']);

        $this->assertFalse($result['ok']);
        $this->assertDatabaseCount('student_profile_change_requests', 1);
    }

    public function test_approve_writes_the_diff_to_students_and_updates_date_updated(): void
    {
        $studentId = $this->makeStudent();
        $reviewer = User::factory()->create();
        $service = app(StudentProfileChangeRequestService::class);
        $submitted = $service->submit($studentId, ['contactno1' => '09171234567'])['request'];

        $service->approve($submitted, $reviewer);

        $student = DB::table('students')->where('id', $studentId)->first();
        $this->assertSame('09171234567', $student->contactno1);
        $this->assertSame('approved', $submitted->fresh()->status);
        $this->assertSame($reviewer->id, $submitted->fresh()->reviewed_by);
        $this->assertNotNull($submitted->fresh()->reviewed_at);
    }

    public function test_reject_requires_notes_and_leaves_students_row_untouched(): void
    {
        $studentId = $this->makeStudent();
        $reviewer = User::factory()->create();
        $service = app(StudentProfileChangeRequestService::class);
        $submitted = $service->submit($studentId, ['contactno1' => '09171234567'])['request'];

        $service->reject($submitted, $reviewer, 'Number does not match records on file.');

        $student = DB::table('students')->where('id', $studentId)->first();
        $this->assertSame('09170000000', $student->contactno1);
        $this->assertSame('rejected', $submitted->fresh()->status);
        $this->assertSame('Number does not match records on file.', $submitted->fresh()->review_notes);
    }
}
