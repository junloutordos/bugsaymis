<?php

namespace Tests\Feature\Mobile;

use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudentIdCardApiTest extends TestCase
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

    private function makeStudent(array $attrs = []): Student
    {
        $id = DB::table('students')->insertGetId(array_merge([
            'lastname' => 'Dela Cruz',
            'firstname' => 'Juan',
            'status' => 'active',
        ], $attrs));

        return Student::find($id);
    }

    public function test_profile_reports_has_photo_true_when_img_is_set(): void
    {
        $student = $this->makeStudent(['img' => 'students/1/photo.jpg']);
        $token = $this->tokenFor($student);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/mobile/student/profile')
            ->assertOk()
            ->assertJson(['student' => ['has_photo' => true]]);
    }

    public function test_profile_reports_has_photo_false_when_no_img(): void
    {
        $student = $this->makeStudent();
        $token = $this->tokenFor($student);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/mobile/student/profile')
            ->assertOk()
            ->assertJson(['student' => ['has_photo' => false]]);
    }
}
