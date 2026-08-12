<?php

namespace Tests\Feature\ALP;

use App\Models\ALP\AlpMembership;
use App\Models\ALP\AlpProgram;
use App\Models\ALP\AlpProgramCycle;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\Permission;
use App\Models\Registrar\StudentEnrollment;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AlpMembersListTest extends TestCase
{
    use RefreshDatabase;

    // AlpRosterService::activeMembers() scopes non-elevated users to cycles
    // they advise/coordinate — grant 'alp.manage' (an elevated permission per
    // AlpRosterService's own $elevated check) so this test can see active
    // members aggregated across BOTH cycles below, not just one adviser's.
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

    private function enroll(int $studentId, SchoolYear $sy, ?int $sectionId, int $gradeLevel): StudentEnrollment
    {
        return StudentEnrollment::create([
            'student_id' => $studentId, 'school_year_id' => $sy->id, 'section_id' => $sectionId,
            'grade_level' => $gradeLevel, 'enrollment_type' => 'returning', 'status' => 'enrolled',
            'enrollment_date' => '2026-07-20',
        ]);
    }

    private function makeCycle(SchoolYear $sy, string $programName): AlpProgramCycle
    {
        $program = AlpProgram::create(['code' => 'ALP-'.uniqid(), 'name' => $programName, 'status' => 'active']);

        return AlpProgramCycle::create(['alp_program_id' => $program->id, 'school_year_id' => $sy->id, 'status' => 'draft']);
    }

    public function test_lists_active_members_across_all_cycles_with_name_grade_section_and_alp(): void
    {
        $user = $this->alpUser();
        $sy = $this->currentSchoolYear();
        $section = Section::create([
            'levelid' => 8, 'sectionname' => 'Newton', 'syid' => 2026,
            'school_year_id' => $sy->id, 'capacity' => 30, 'is_active' => true,
        ]);

        $cycleA = $this->makeCycle($sy, 'Reading Recovery Program');
        $cycleB = $this->makeCycle($sy, 'Numeracy Bridge Program');

        $studentA = $this->makeStudent('Cruz', 'Ana');
        $enrollmentA = $this->enroll($studentA, $sy, $section->id, 8);
        AlpMembership::create([
            'alp_program_cycle_id' => $cycleA->id, 'school_year_id' => $sy->id, 'student_id' => $studentA,
            'student_enrollment_id' => $enrollmentA->id, 'status' => 'active', 'joined_at' => '2026-07-20',
        ]);

        $studentB = $this->makeStudent('Reyes', 'Ben');
        $enrollmentB = $this->enroll($studentB, $sy, null, 9);
        AlpMembership::create([
            'alp_program_cycle_id' => $cycleB->id, 'school_year_id' => $sy->id, 'student_id' => $studentB,
            'student_enrollment_id' => $enrollmentB->id, 'status' => 'active', 'joined_at' => '2026-07-20',
        ]);

        // Inactive membership must be excluded.
        $studentC = $this->makeStudent('Santos', 'Cara');
        $enrollmentC = $this->enroll($studentC, $sy, null, 7);
        AlpMembership::create([
            'alp_program_cycle_id' => $cycleA->id, 'school_year_id' => $sy->id, 'student_id' => $studentC,
            'student_enrollment_id' => $enrollmentC->id, 'status' => 'inactive', 'joined_at' => '2026-07-20',
        ]);

        $response = $this->actingAs($user)->get(route('alp.members.index'));

        $response->assertInertia(fn ($page) => $page
            ->component('CID/ALP/Members')
            ->has('members', 2)
            ->where('members.0.name', 'Cruz, Ana')
            ->where('members.0.grade_level', 8)
            ->where('members.0.section', 'Newton')
            ->where('members.0.alp', 'Reading Recovery Program')
            ->where('members.1.name', 'Reyes, Ben')
            ->where('members.1.alp', 'Numeracy Bridge Program'));
    }
}
