<?php

namespace Tests\Feature\Mobile;

use App\Models\Student;
use App\Models\StudentCredential;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentFcmTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_student_updating_fcm_token_persists_it_on_credential(): void
    {
        $studentId = \Illuminate\Support\Facades\DB::table('students')->insertGetId([
            'pisaysystemID' => 'TEST-FCM-1', 'firstname' => 'Fcm', 'lastname' => 'Student',
        ]);
        $credential = StudentCredential::create([
            'student_id' => $studentId, 'email' => 'fcm-student@example.com',
            'password' => bcrypt('secret'), 'status' => 'active', 'email_verified_at' => now(),
        ]);
        $student = Student::find($studentId);
        $token = $student->createToken('test-device', ['mobile'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/mobile/fcm-token', ['fcm_token' => 'device-token-abc']);

        $response->assertOk();
        $this->assertSame('device-token-abc', $credential->fresh()->fcm_device_token);
    }
}
