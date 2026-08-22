<?php

namespace Tests\Unit;

use App\Models\Student;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Student authenticates AtlasGo mobile requests via Sanctum, but until now
 * did not implement Authenticatable — fine for auth:sanctum itself (which
 * resolves the tokenable model directly), but Laravel's ThrottleRequests
 * middleware unconditionally calls $user->getAuthIdentifier(), which fatals
 * with a BadMethodCallException for any throttled route. Confirmed via a
 * live curl against production dev Docker that this 500s every throttled
 * student mobile endpoint (RH applications, leave passes, medical records,
 * guidance forms, lost-found reports) — a real, pre-existing production bug
 * this fix resolves, not just a test-environment artifact.
 */
class StudentAuthenticatableTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_implements_authenticatable(): void
    {
        $id = DB::table('students')->insertGetId(['lastname' => 'Auth', 'firstname' => 'Test', 'status' => 'active']);
        $student = Student::find($id);

        $this->assertInstanceOf(Authenticatable::class, $student);
        $this->assertSame($id, $student->getAuthIdentifier());
        $this->assertSame('id', $student->getAuthIdentifierName());
    }
}
