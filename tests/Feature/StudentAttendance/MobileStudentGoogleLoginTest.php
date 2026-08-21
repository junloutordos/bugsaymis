<?php

namespace Tests\Feature\StudentAttendance;

use App\Models\Student;
use App\Services\StudentAttendance\StudentGoogleClientFactory;
use Google\Client as GoogleClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class MobileStudentGoogleLoginTest extends TestCase
{
    use RefreshDatabase;

    private function mockGoogleClient(array $payload): void
    {
        $googleClient = Mockery::mock(GoogleClient::class);
        $googleClient->shouldReceive('verifyIdToken')->once()->andReturn($payload);
        $factory = Mockery::mock(StudentGoogleClientFactory::class);
        $factory->shouldReceive('make')->andReturn($googleClient);
        $this->app->instance(StudentGoogleClientFactory::class, $factory);
    }

    private function makeStudent(array $overrides = []): int
    {
        return DB::table('students')->insertGetId(array_merge([
            'lastname'      => 'Aguilera',
            'firstname'     => 'Nicole',
            'batch'         => '2029',
            'pisaysystemID' => '13-2023-031',
            'student_email' => 'naguilera2029@crc.pshs.edu.ph',
            'status'        => 'Enrolled',
        ], $overrides));
    }

    public function test_google_login_links_and_issues_token_without_creating_a_users_row(): void
    {
        $this->makeStudent();
        $email = 'naguilera2029@crc.pshs.edu.ph';
        $studentId = DB::table('students')->where('pisaysystemID', '13-2023-031')->value('id');

        $this->mockGoogleClient([
            'email'          => $email,
            'email_verified' => true,
            'name'           => 'Nicole Aguilera',
        ]);

        $response = $this->postJson(route('mobile.student.google-link'), [
            'id_token'      => 'fake-token',
            'pisaysystemID' => '13-2023-031',
            'device_name'   => 'AtlasGo on Pixel 8',
        ]);

        $response->assertOk()->assertJsonStructure(['token', 'role', 'student_id']);
        $this->assertSame('student', $response->json('role'));
        $this->assertSame($studentId, $response->json('student_id'));

        $this->assertDatabaseHas('student_google_links', [
            'google_email'  => $email,
            'pisaysystemID' => '13-2023-031',
        ]);

        $this->assertDatabaseMissing('users', ['email' => $email]);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_type' => Student::class,
            'tokenable_id'   => $studentId,
        ]);
    }

    public function test_google_login_on_an_already_linked_account_issues_token_without_a_users_row(): void
    {
        $this->makeStudent([
            'pisaysystemID' => '13-2023-032',
            'student_email' => 'jsomeone2029@crc.pshs.edu.ph',
        ]);
        $email = 'jsomeone2029@crc.pshs.edu.ph';
        $studentId = DB::table('students')->where('pisaysystemID', '13-2023-032')->value('id');

        DB::table('student_google_links')->insert([
            'google_email'  => $email,
            'pisaysystemID' => '13-2023-032',
            'linked_at'     => now(),
        ]);

        $this->mockGoogleClient([
            'email'          => $email,
            'email_verified' => true,
            'name'           => 'Nicole Aguilera',
        ]);

        $response = $this->postJson(route('mobile.student.google-login'), [
            'id_token'    => 'fake-token',
            'device_name' => 'AtlasGo on Pixel 8',
        ]);

        $response->assertOk();
        $this->assertSame('student', $response->json('role'));
        $this->assertSame($studentId, $response->json('student_id'));

        $this->assertDatabaseMissing('users', ['email' => $email]);
    }

    public function test_google_login_rejects_non_pshs_domain(): void
    {
        $this->mockGoogleClient([
            'email'          => 'someone@gmail.com',
            'email_verified' => true,
            'name'           => 'Someone',
        ]);

        $response = $this->postJson(route('mobile.student.google-login'), [
            'id_token'    => 'fake-token',
            'device_name' => 'AtlasGo on Pixel 8',
        ]);

        $response->assertStatus(403);
    }
}
