<?php

namespace Tests\Feature\ALP;

use App\Models\ALP\AlpAttendance;
use App\Models\ALP\AlpMembership;
use App\Models\ALP\AlpProgram;
use App\Models\ALP\AlpProgramCycle;
use App\Models\ALP\AlpSession;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\Permission;
use App\Models\Registrar\StudentEnrollment;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AlpAttendanceTest extends TestCase
{
    use RefreshDatabase;

    private function alpUser(): User
    {
        $permission = Permission::firstOrCreate(['name' => 'alp.manage'], ['module' => 'ALP', 'description' => 'alp.manage']);
        $role = Role::create(['name' => 'AlpManagerTester_'.uniqid()]);
        $role->permissions()->attach($permission->id);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        return $user;
    }

    private function currentSchoolYear(): SchoolYear
    {
        return SchoolYear::create([
            'name' => '2026-2027', 'start_date' => '2026-07-01', 'end_date' => '2027-06-30',
            'is_current' => true, 'status' => 'active',
        ]);
    }

    private function makeStudent(string $lastname, string $firstname): int
    {
        return (int) DB::table('students')->insertGetId(['lastname' => $lastname, 'firstname' => $firstname]);
    }

    private function enroll(int $studentId, SchoolYear $sy, int $gradeLevel): StudentEnrollment
    {
        return StudentEnrollment::create([
            'student_id' => $studentId, 'school_year_id' => $sy->id, 'section_id' => null,
            'grade_level' => $gradeLevel, 'enrollment_type' => 'returning', 'status' => 'enrolled',
            'enrollment_date' => '2026-07-20',
        ]);
    }

    private function makeCycle(SchoolYear $sy, string $programName): AlpProgramCycle
    {
        $program = AlpProgram::create(['code' => 'ALP-'.uniqid(), 'name' => $programName, 'status' => 'active']);

        return AlpProgramCycle::create(['alp_program_id' => $program->id, 'school_year_id' => $sy->id, 'status' => 'draft']);
    }

    public function test_member_added_after_a_session_exists_still_gets_a_default_present_attendance_row(): void
    {
        $user = $this->alpUser();
        $sy = $this->currentSchoolYear();
        $cycle = $this->makeCycle($sy, 'Reading Recovery Program');

        // A member present before the session is created — the pre-existing
        // "works" path.
        $earlyStudent = $this->makeStudent('Cruz', 'Ana');
        $earlyEnrollment = $this->enroll($earlyStudent, $sy, 8);
        AlpMembership::create([
            'alp_program_cycle_id' => $cycle->id, 'school_year_id' => $sy->id, 'student_id' => $earlyStudent,
            'student_enrollment_id' => $earlyEnrollment->id, 'status' => 'active', 'joined_at' => '2026-07-20',
        ]);

        $response = $this->actingAs($user)->post(route('alp.sessions.store', $cycle), [
            'session_date' => '2026-08-10', 'topic' => 'Reading Circle',
        ]);
        $response->assertRedirect();
        $session = AlpSession::where('alp_program_cycle_id', $cycle->id)->first();
        $this->assertNotNull($session);
        $this->assertCount(1, AlpAttendance::where('alp_session_id', $session->id)->get());

        // A member added AFTER the session already exists — this is the bug
        // scenario: before the fix, this member would have zero AlpAttendance
        // rows for this session and their row would silently not render.
        $lateStudent = $this->makeStudent('Reyes', 'Ben');
        $lateEnrollment = $this->enroll($lateStudent, $sy, 9);
        $storeResponse = $this->actingAs($user)->post(route('alp.members.store', $cycle), [
            'student_ids' => [$lateStudent],
        ]);
        $storeResponse->assertRedirect();

        $lateMembership = AlpMembership::where('alp_program_cycle_id', $cycle->id)->where('student_id', $lateStudent)->first();
        $this->assertNotNull($lateMembership);

        $backfilled = AlpAttendance::where('alp_session_id', $session->id)->where('alp_membership_id', $lateMembership->id)->first();
        $this->assertNotNull($backfilled, 'Newly added member must be backfilled with a default attendance row for every pre-existing session.');
        $this->assertSame('present', $backfilled->status);

        // Every active member now has exactly one row for the session.
        $this->assertCount(2, AlpAttendance::where('alp_session_id', $session->id)->get());
    }

    public function test_save_attendance_upserts_every_active_member_even_when_untouched_by_the_client(): void
    {
        $user = $this->alpUser();
        $sy = $this->currentSchoolYear();
        $cycle = $this->makeCycle($sy, 'Numeracy Bridge Program');

        $studentA = $this->makeStudent('Cruz', 'Ana');
        $enrollmentA = $this->enroll($studentA, $sy, 8);
        $membershipA = AlpMembership::create([
            'alp_program_cycle_id' => $cycle->id, 'school_year_id' => $sy->id, 'student_id' => $studentA,
            'student_enrollment_id' => $enrollmentA->id, 'status' => 'active', 'joined_at' => '2026-07-20',
        ]);

        $studentB = $this->makeStudent('Reyes', 'Ben');
        $enrollmentB = $this->enroll($studentB, $sy, 9);
        $membershipB = AlpMembership::create([
            'alp_program_cycle_id' => $cycle->id, 'school_year_id' => $sy->id, 'student_id' => $studentB,
            'student_enrollment_id' => $enrollmentB->id, 'status' => 'active', 'joined_at' => '2026-07-20',
        ]);

        $this->actingAs($user)->post(route('alp.sessions.store', $cycle), [
            'session_date' => '2026-08-10', 'topic' => 'Numeracy Session',
        ]);
        $session = AlpSession::where('alp_program_cycle_id', $cycle->id)->first();

        // Client only sends membership A's record (marked absent) — mirrors a
        // frontend that only submits touched cells. Membership B must still
        // end up with a persisted "present" row, not be silently dropped.
        $response = $this->actingAs($user)->post(route('alp.attendance.save', [$cycle, $session]), [
            'records' => [
                ['membership_id' => $membershipA->id, 'status' => 'absent', 'remarks' => 'Sick'],
            ],
        ]);
        $response->assertRedirect();

        $recordA = AlpAttendance::where('alp_session_id', $session->id)->where('alp_membership_id', $membershipA->id)->first();
        $this->assertSame('absent', $recordA->status);
        $this->assertSame('Sick', $recordA->remarks);

        $recordB = AlpAttendance::where('alp_session_id', $session->id)->where('alp_membership_id', $membershipB->id)->first();
        $this->assertNotNull($recordB, 'An active member not included in the save payload must still be persisted with the default status.');
        $this->assertSame('present', $recordB->status);

        $this->assertSame('closed', $session->refresh()->status);
    }
}
