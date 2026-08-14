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

    private function addMember(AlpProgramCycle $cycle, SchoolYear $sy, string $lastname, string $firstname, int $gradeLevel): AlpMembership
    {
        $studentId = $this->makeStudent($lastname, $firstname);
        $enrollment = $this->enroll($studentId, $sy, $gradeLevel);

        return AlpMembership::create([
            'alp_program_cycle_id' => $cycle->id, 'school_year_id' => $sy->id, 'student_id' => $studentId,
            'student_enrollment_id' => $enrollment->id, 'status' => 'active', 'joined_at' => '2026-07-20',
        ]);
    }

    public function test_index_lists_every_active_member_automatically_with_no_roster_creation_step(): void
    {
        $user = $this->alpUser();
        $sy = $this->currentSchoolYear();
        $cycle = $this->makeCycle($sy, 'Reading Recovery Program');

        $this->addMember($cycle, $sy, 'Cruz', 'Ana', 8);
        $this->addMember($cycle, $sy, 'Reyes', 'Ben', 9);

        // No date/session created yet — the member list must still be
        // fully populated (this is the core requirement: the roster is a
        // given, never something the user has to "create").
        $response = $this->actingAs($user)->getJson(route('alp.attendance.index', $cycle));

        $response->assertOk();
        $response->assertJsonCount(2, 'members');
        $response->assertJson(['dates' => [], 'records' => []]);
        $response->assertJsonFragment(['name' => 'Cruz, Ana']);
        $response->assertJsonFragment(['name' => 'Reyes, Ben']);
    }

    public function test_store_date_creates_a_session_and_backfills_present_for_every_active_member(): void
    {
        $user = $this->alpUser();
        $sy = $this->currentSchoolYear();
        $cycle = $this->makeCycle($sy, 'Reading Recovery Program');
        $membershipA = $this->addMember($cycle, $sy, 'Cruz', 'Ana', 8);
        $membershipB = $this->addMember($cycle, $sy, 'Reyes', 'Ben', 9);

        $response = $this->actingAs($user)->postJson(route('alp.attendance.dates.store', $cycle), [
            'date' => '2026-08-10',
        ]);
        $response->assertOk();
        $sessionId = $response->json('id');

        $this->assertNotNull(AlpSession::find($sessionId));
        $this->assertSame('present', AlpAttendance::where('alp_session_id', $sessionId)->where('alp_membership_id', $membershipA->id)->first()->status);
        $this->assertSame('present', AlpAttendance::where('alp_session_id', $sessionId)->where('alp_membership_id', $membershipB->id)->first()->status);

        $index = $this->actingAs($user)->getJson(route('alp.attendance.index', $cycle));
        $index->assertJsonCount(1, 'dates');
        $index->assertJsonPath('dates.0.date', '2026-08-10');
    }

    public function test_member_added_after_a_date_exists_still_gets_a_default_present_row(): void
    {
        $user = $this->alpUser();
        $sy = $this->currentSchoolYear();
        $cycle = $this->makeCycle($sy, 'Reading Recovery Program');
        $this->addMember($cycle, $sy, 'Cruz', 'Ana', 8);

        $dateResponse = $this->actingAs($user)->postJson(route('alp.attendance.dates.store', $cycle), ['date' => '2026-08-10']);
        $sessionId = $dateResponse->json('id');

        // A member added AFTER the date already exists — the exact scenario
        // that broke under the old session-card design.
        $studentId = $this->makeStudent('Reyes', 'Ben');
        $enrollment = $this->enroll($studentId, $sy, 9);
        $lateMembership = AlpMembership::create([
            'alp_program_cycle_id' => $cycle->id, 'school_year_id' => $sy->id, 'student_id' => $studentId,
            'student_enrollment_id' => $enrollment->id, 'status' => 'active', 'joined_at' => '2026-08-11',
        ]);

        // AlpController::storeMembers() isn't exercised here directly since
        // this test constructs the membership manually — call it via the
        // real endpoint instead to prove the backfill fires end-to-end.
        $newStudentId = $this->makeStudent('Santos', 'Cara');
        $this->enroll($newStudentId, $sy, 7);
        $storeResponse = $this->actingAs($user)->postJson(route('alp.members.store', $cycle), [
            'student_ids' => [$newStudentId],
        ]);
        $storeResponse->assertRedirect();

        $newestMembership = AlpMembership::where('alp_program_cycle_id', $cycle->id)->where('student_id', '!=', $lateMembership->student_id)
            ->orderByDesc('id')->first();

        $backfilled = AlpAttendance::where('alp_session_id', $sessionId)->where('alp_membership_id', $newestMembership->id)->first();
        $this->assertNotNull($backfilled, 'A member added after a date exists must be backfilled with a default present row for that date.');
        $this->assertSame('present', $backfilled->status);

        $index = $this->actingAs($user)->getJson(route('alp.attendance.index', $cycle));
        $index->assertJsonCount(3, 'members'); // Cruz, Reyes, Santos
    }

    public function test_upsert_saves_the_whole_grid_in_one_request(): void
    {
        $user = $this->alpUser();
        $sy = $this->currentSchoolYear();
        $cycle = $this->makeCycle($sy, 'Numeracy Bridge Program');
        $membershipA = $this->addMember($cycle, $sy, 'Cruz', 'Ana', 8);
        $membershipB = $this->addMember($cycle, $sy, 'Reyes', 'Ben', 9);

        $dateResponse = $this->actingAs($user)->postJson(route('alp.attendance.dates.store', $cycle), ['date' => '2026-08-10']);
        $sessionId = $dateResponse->json('id');

        $response = $this->actingAs($user)->postJson(route('alp.attendance.upsert', $cycle), [
            'records' => [
                ['session_id' => $sessionId, 'membership_id' => $membershipA->id, 'status' => 'absent', 'remarks' => 'Sick'],
                ['session_id' => $sessionId, 'membership_id' => $membershipB->id, 'status' => 'present'],
            ],
        ]);
        $response->assertOk();

        $recordA = AlpAttendance::where('alp_session_id', $sessionId)->where('alp_membership_id', $membershipA->id)->first();
        $this->assertSame('absent', $recordA->status);
        $this->assertSame('Sick', $recordA->remarks);

        $recordB = AlpAttendance::where('alp_session_id', $sessionId)->where('alp_membership_id', $membershipB->id)->first();
        $this->assertSame('present', $recordB->status);
    }

    public function test_upsert_rejects_a_session_or_membership_from_another_cycle(): void
    {
        $user = $this->alpUser();
        $sy = $this->currentSchoolYear();
        $cycle = $this->makeCycle($sy, 'Numeracy Bridge Program');
        $membership = $this->addMember($cycle, $sy, 'Cruz', 'Ana', 8);

        $otherCycle = $this->makeCycle($sy, 'Other Program');
        $otherDateResponse = $this->actingAs($user)->postJson(route('alp.attendance.dates.store', $otherCycle), ['date' => '2026-08-10']);
        $otherSessionId = $otherDateResponse->json('id');

        $response = $this->actingAs($user)->postJson(route('alp.attendance.upsert', $cycle), [
            'records' => [
                ['session_id' => $otherSessionId, 'membership_id' => $membership->id, 'status' => 'present'],
            ],
        ]);

        $response->assertStatus(422);
    }

    public function test_destroy_date_removes_the_session_and_its_attendance_rows(): void
    {
        $user = $this->alpUser();
        $sy = $this->currentSchoolYear();
        $cycle = $this->makeCycle($sy, 'Reading Recovery Program');
        $this->addMember($cycle, $sy, 'Cruz', 'Ana', 8);

        $dateResponse = $this->actingAs($user)->postJson(route('alp.attendance.dates.store', $cycle), ['date' => '2026-08-10']);
        $sessionId = $dateResponse->json('id');

        $response = $this->actingAs($user)->deleteJson(route('alp.attendance.dates.destroy', [$cycle, $sessionId]));
        $response->assertOk();

        $this->assertNull(AlpSession::find($sessionId));
        $this->assertSame(0, AlpAttendance::where('alp_session_id', $sessionId)->count());
    }
}
