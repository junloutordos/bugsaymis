<?php

namespace Tests\Feature\ALP;

use App\Models\ALP\AlpMembership;
use App\Models\ALP\AlpProgram;
use App\Models\ALP\AlpProgramCycle;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\Permission;
use App\Models\Registrar\StudentEnrollment;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AlpMembersPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_downloads_a_pdf_of_active_members(): void
    {
        // 'alp.manage' is elevated per AlpRosterService::activeMembers()'s own
        // $elevated check, so this user sees the cycle's members regardless
        // of who its adviser/coordinator is — matches AlpMembersListTest.
        $permission = Permission::firstOrCreate(['name' => 'alp.manage'], ['module' => 'ALP', 'description' => 'alp.manage']);
        $role = Role::create(['name' => 'AlpManagerTester_'.uniqid()]);
        $role->permissions()->attach($permission->id);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        $sy = SchoolYear::create([
            'name' => '2026-2027', 'start_date' => '2026-07-01', 'end_date' => '2027-06-30',
            'is_current' => true, 'status' => 'active',
        ]);
        $studentId = (int) DB::table('students')->insertGetId(['lastname' => 'Cruz', 'firstname' => 'Ana']);
        $enrollment = StudentEnrollment::create([
            'student_id' => $studentId, 'school_year_id' => $sy->id, 'section_id' => null,
            'grade_level' => 8, 'enrollment_type' => 'returning', 'status' => 'enrolled', 'enrollment_date' => '2026-07-20',
        ]);
        $program = AlpProgram::create(['code' => 'ALP-'.uniqid(), 'name' => 'Reading Recovery Program', 'status' => 'active']);
        $cycle = AlpProgramCycle::create(['alp_program_id' => $program->id, 'school_year_id' => $sy->id, 'status' => 'draft']);
        AlpMembership::create([
            'alp_program_cycle_id' => $cycle->id, 'school_year_id' => $sy->id, 'student_id' => $studentId,
            'student_enrollment_id' => $enrollment->id, 'status' => 'active', 'joined_at' => '2026-07-20',
        ]);

        $response = $this->actingAs($user)->get(route('alp.members.pdf'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertNotEmpty($response->getContent());
    }
}
