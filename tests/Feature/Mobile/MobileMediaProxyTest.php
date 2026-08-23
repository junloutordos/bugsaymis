<?php

namespace Tests\Feature\Mobile;

use App\Models\Student;
use App\Models\StudentCredential;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MobileMediaProxyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['opentelemetry.user_context' => false]);
    }

    public function test_serves_a_private_s3_file_to_a_sanctum_authenticated_student(): void
    {
        Storage::fake('s3');
        Storage::disk('s3')->put('announcements/test.jpg', 'fake-image-bytes');

        $studentId = DB::table('students')->insertGetId([
            'pisaysystemID' => 'TST-MEDIA-1', 'firstname' => 'Media', 'lastname' => 'Student',
        ]);
        StudentCredential::create([
            'student_id' => $studentId, 'email' => 'mediastudent@example.com',
            'password' => bcrypt('x'), 'status' => 'active', 'email_verified_at' => now(),
        ]);
        $token = Student::find($studentId)->createToken('device', ['mobile'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->get('/api/mobile/media/announcements/test.jpg');

        $response->assertOk();
        $this->assertSame('fake-image-bytes', $response->streamedContent());
    }

    public function test_rejects_an_unauthenticated_request(): void
    {
        // getJson (not get) — matches AtlasGo's real ApiClient, which always
        // sends Accept: application/json, so an unauthenticated request gets
        // a JSON 401, not the Authenticate middleware's HTML-redirect
        // fallback for requests that don't ask for JSON.
        $this->getJson('/api/mobile/media/announcements/test.jpg')->assertUnauthorized();
    }
}
