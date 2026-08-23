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

    public function test_photo_streams_the_students_own_s3_photo(): void
    {
        Storage::fake('s3');
        Storage::disk('s3')->put('students/1/photo.jpg', 'fake-image-bytes');
        $student = $this->makeStudent(['img' => 'students/1/photo.jpg']);
        $token = $this->tokenFor($student);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->get('/api/mobile/student/photo');

        $response->assertOk();
        $this->assertSame('fake-image-bytes', $response->getContent());
    }

    public function test_photo_returns_404_when_no_photo_on_file(): void
    {
        $student = $this->makeStudent();
        $token = $this->tokenFor($student);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->get('/api/mobile/student/photo')
            ->assertStatus(404);
    }

    public function test_photo_rejects_unauthenticated_requests(): void
    {
        $this->getJson('/api/mobile/student/photo')->assertStatus(401);
    }
}
