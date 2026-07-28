<?php

namespace Tests\Feature\StudentPortal;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StudentGoogleLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_link_succeeds_when_pisay_id_matches_students_own_email(): void
    {
        $studentId = $this->student('13-2023-064', 'alirio2029@crc.pshs.edu.ph');

        $this->withSession(['student_portal_google_email' => 'alirio2029@crc.pshs.edu.ph'])
            ->post(route('student-portal.link.submit'), ['pisaysystemID' => '13-2023-064'])
            ->assertRedirect(route('student-portal.dashboard'));

        $this->assertDatabaseHas('student_google_links', [
            'google_email'  => 'alirio2029@crc.pshs.edu.ph',
            'pisaysystemID' => '13-2023-064',
        ]);
    }

    public function test_link_rejected_when_pisay_id_belongs_to_a_different_students_email(): void
    {
        $this->student('13-2023-064', 'alirio2029@crc.pshs.edu.ph');
        $this->student('13-2023-065', 'rmanugas2029@crc.pshs.edu.ph');

        $this->withSession(['student_portal_google_email' => 'alirio2029@crc.pshs.edu.ph'])
            ->post(route('student-portal.link.submit'), ['pisaysystemID' => '13-2023-065'])
            ->assertSessionHasErrors('pisaysystemID');

        $this->assertDatabaseMissing('student_google_links', [
            'google_email' => 'alirio2029@crc.pshs.edu.ph',
        ]);
    }

    public function test_link_backfills_blank_student_email_on_success(): void
    {
        $studentId = $this->student('13-2023-066', null);

        $this->withSession(['student_portal_google_email' => 'lmontero2029@crc.pshs.edu.ph'])
            ->post(route('student-portal.link.submit'), ['pisaysystemID' => '13-2023-066'])
            ->assertRedirect(route('student-portal.dashboard'));

        $this->assertDatabaseHas('students', [
            'id'            => $studentId,
            'student_email' => 'lmontero2029@crc.pshs.edu.ph',
        ]);
    }

    private function student(string $pisaysystemID, ?string $studentEmail): int
    {
        return DB::table('students')->insertGetId([
            'lastname'      => 'Dela Cruz',
            'firstname'     => 'Juan',
            'batch'         => '2029',
            'pisaysystemID' => $pisaysystemID,
            'student_email' => $studentEmail,
            'status'        => 'Enrolled',
        ]);
    }
}
