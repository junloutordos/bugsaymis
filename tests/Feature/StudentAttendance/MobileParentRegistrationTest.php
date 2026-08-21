<?php

namespace Tests\Feature\StudentAttendance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MobileParentRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_parent_registration_succeeds_for_a_non_student_email(): void
    {
        Mail::fake();

        $this->postJson(route('mobile.register'), [
            'name'                  => 'Maria Santos',
            'email'                 => 'maria.santos.parent@gmail.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ])->assertOk();

        $this->assertDatabaseHas('parent_contacts', [
            'email'  => 'maria.santos.parent@gmail.com',
            'status' => 'pending_verification',
        ]);

        $this->assertDatabaseMissing('users', [
            'email' => 'maria.santos.parent@gmail.com',
        ]);
    }

    public function test_parent_registration_rejected_when_email_belongs_to_a_student(): void
    {
        Mail::fake();

        DB::table('students')->insert([
            'lastname'      => 'Aguilera',
            'firstname'     => 'Nicole',
            'batch'         => '2029',
            'pisaysystemID' => '13-2023-031',
            'student_email' => 'naguilera2029@crc.pshs.edu.ph',
            'status'        => 'Enrolled',
        ]);

        $this->postJson(route('mobile.register'), [
            'name'                  => 'Nicole Aguilera',
            'email'                 => 'naguilera2029@crc.pshs.edu.ph',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('email');

        $this->assertDatabaseMissing('users', [
            'email' => 'naguilera2029@crc.pshs.edu.ph',
        ]);

        $this->assertDatabaseMissing('parent_contacts', [
            'email' => 'naguilera2029@crc.pshs.edu.ph',
        ]);
    }
}
