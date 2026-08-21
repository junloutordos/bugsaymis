<?php

namespace Tests\Feature\StudentAttendance;

use App\Models\StudentAttendance\ParentContact;
use App\Models\StudentCredential;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MobilePasswordLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_parent_can_login_with_email_and_password_without_a_users_row(): void
    {
        ParentContact::create([
            'name'     => 'Maria Santos',
            'email'    => 'maria.santos.parent@gmail.com',
            'password' => Hash::make('password123'),
            'status'   => 'active',
        ]);

        $response = $this->postJson(route('mobile.login'), [
            'email'       => 'maria.santos.parent@gmail.com',
            'password'    => 'password123',
            'device_name' => 'AtlasGo on Pixel 8',
        ]);

        $response->assertOk();
        $this->assertSame('parent', $response->json('role'));
        $this->assertDatabaseMissing('users', ['email' => 'maria.santos.parent@gmail.com']);
    }

    public function test_parent_login_rejected_when_still_pending_verification(): void
    {
        ParentContact::create([
            'name'     => 'Maria Santos',
            'email'    => 'maria.santos.parent@gmail.com',
            'password' => Hash::make('password123'),
            'status'   => 'pending_verification',
        ]);

        $response = $this->postJson(route('mobile.login'), [
            'email'       => 'maria.santos.parent@gmail.com',
            'password'    => 'password123',
            'device_name' => 'AtlasGo on Pixel 8',
        ]);

        $response->assertStatus(403)->assertJson(['requires_verification' => true]);
    }

    public function test_student_can_login_with_email_and_password_without_a_users_row(): void
    {
        $studentId = DB::table('students')->insertGetId([
            'lastname'      => 'Aguilera',
            'firstname'     => 'Nicole',
            'batch'         => '2029',
            'pisaysystemID' => '13-2023-031',
            'student_email' => 'naguilera2029@crc.pshs.edu.ph',
            'status'        => 'Enrolled',
        ]);

        StudentCredential::create([
            'student_id' => $studentId,
            'email'      => 'naguilera2029@crc.pshs.edu.ph',
            'password'   => Hash::make('password123'),
            'status'     => 'active',
        ]);

        $response = $this->postJson(route('mobile.login'), [
            'email'       => 'naguilera2029@crc.pshs.edu.ph',
            'password'    => 'password123',
            'device_name' => 'AtlasGo on Pixel 8',
        ]);

        $response->assertOk();
        $this->assertSame('student', $response->json('role'));
        $this->assertSame($studentId, $response->json('student_id'));
        $this->assertDatabaseMissing('users', ['email' => 'naguilera2029@crc.pshs.edu.ph']);
    }

    public function test_login_rejects_wrong_password(): void
    {
        ParentContact::create([
            'name'     => 'Maria Santos',
            'email'    => 'maria.santos.parent@gmail.com',
            'password' => Hash::make('password123'),
            'status'   => 'active',
        ]);

        $response = $this->postJson(route('mobile.login'), [
            'email'       => 'maria.santos.parent@gmail.com',
            'password'    => 'wrong-password',
            'device_name' => 'AtlasGo on Pixel 8',
        ]);

        $response->assertStatus(422);
    }
}
