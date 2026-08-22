<?php

namespace Tests\Feature;

use App\Models\StudentProfileChangeRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StudentProfileChangeRequestModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_casts_requested_changes_to_array_and_defaults_to_pending(): void
    {
        $studentId = DB::table('students')->insertGetId([
            'lastname' => 'Test', 'firstname' => 'Model', 'status' => 'active',
        ]);

        $request = StudentProfileChangeRequest::create([
            'student_id' => $studentId,
            'requested_changes' => ['contactno1' => '09171234567'],
        ]);

        $this->assertSame('pending', $request->fresh()->status);
        $this->assertIsArray($request->fresh()->requested_changes);
        $this->assertSame('09171234567', $request->fresh()->requested_changes['contactno1']);
    }
}
