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
use App\Services\ALP\AlpRosterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AlpRosterServiceTest extends TestCase
{
    use RefreshDatabase;

    private function currentSchoolYear(): SchoolYear
    {
        return SchoolYear::create([
            'name' => '2026-2027', 'start_date' => '2026-07-01', 'end_date' => '2027-06-30',
            'is_current' => true, 'status' => 'active',
        ]);
    }

    private function elevatedUser(): User
    {
        $permission = Permission::firstOrCreate(['name' => 'alp.manage'], ['module' => 'ALP', 'description' => 'alp.manage']);
        $role = Role::create(['name' => 'AlpManagerTester_'.uniqid()]);
        $role->permissions()->attach($permission->id);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_unassigned_grades_7_to_10_excludes_enrollments_with_a_deleted_student(): void
    {
        $sy = $this->currentSchoolYear();

        // Real student — must appear.
        $realStudentId = (int) DB::table('students')->insertGetId(['lastname' => 'Dela Cruz', 'firstname' => 'Diego']);
        StudentEnrollment::create([
            'student_id' => $realStudentId, 'school_year_id' => $sy->id, 'section_id' => null,
            'grade_level' => 7, 'enrollment_type' => 'new', 'status' => 'enrolled', 'enrollment_date' => '2026-07-30',
        ]);

        // Orphaned enrollment — student_id points at nothing (mirrors the prod bug: a
        // students row hard-deleted after its student_enrollments stub was created).
        StudentEnrollment::create([
            'student_id' => 999999, 'school_year_id' => $sy->id, 'section_id' => null,
            'grade_level' => 7, 'enrollment_type' => 'new', 'status' => 'enrolled', 'enrollment_date' => '2026-07-30',
        ]);

        $rows = (new AlpRosterService)->unassignedGrades7To10($sy->id);

        $this->assertCount(1, $rows);
        $this->assertSame('Dela Cruz, Diego', $rows->first()['name']);
    }

    public function test_active_members_excludes_memberships_with_a_deleted_student(): void
    {
        $user = $this->elevatedUser();
        $sy = $this->currentSchoolYear();
        $program = AlpProgram::create(['code' => 'ALP-'.uniqid(), 'name' => 'Reading Recovery Program', 'status' => 'active']);
        $cycle = AlpProgramCycle::create(['alp_program_id' => $program->id, 'school_year_id' => $sy->id, 'status' => 'draft']);

        $realStudentId = (int) DB::table('students')->insertGetId(['lastname' => 'Cruz', 'firstname' => 'Ana']);
        $enrollment = StudentEnrollment::create([
            'student_id' => $realStudentId, 'school_year_id' => $sy->id, 'section_id' => null,
            'grade_level' => 8, 'enrollment_type' => 'returning', 'status' => 'enrolled', 'enrollment_date' => '2026-07-20',
        ]);
        AlpMembership::create([
            'alp_program_cycle_id' => $cycle->id, 'school_year_id' => $sy->id, 'student_id' => $realStudentId,
            'student_enrollment_id' => $enrollment->id, 'status' => 'active', 'joined_at' => '2026-07-20',
        ]);

        $orphanEnrollment = StudentEnrollment::create([
            'student_id' => 999999, 'school_year_id' => $sy->id, 'section_id' => null,
            'grade_level' => 8, 'enrollment_type' => 'returning', 'status' => 'enrolled', 'enrollment_date' => '2026-07-20',
        ]);
        AlpMembership::create([
            'alp_program_cycle_id' => $cycle->id, 'school_year_id' => $sy->id, 'student_id' => 999999,
            'student_enrollment_id' => $orphanEnrollment->id, 'status' => 'active', 'joined_at' => '2026-07-20',
        ]);

        $rows = (new AlpRosterService)->activeMembers($sy->id, $user);

        $this->assertCount(1, $rows);
        $this->assertSame('Cruz, Ana', $rows->first()['name']);
    }

    public function test_filter_rows_by_grade_section_and_search(): void
    {
        $rows = new Collection([
            ['name' => 'Cruz, Ana', 'grade_level' => 7, 'section' => 'Curie', 'alp' => 'Reading Recovery Program'],
            ['name' => 'Reyes, Ben', 'grade_level' => 8, 'section' => 'Newton', 'alp' => 'Numeracy Bridge Program'],
            ['name' => 'Santos, Cara', 'grade_level' => 7, 'section' => 'Newton', 'alp' => 'Reading Recovery Program'],
        ]);
        $service = new AlpRosterService;

        $byGrade = $service->filterRows($rows, null, '7', null, ['name', 'alp']);
        $this->assertCount(2, $byGrade);

        $bySection = $service->filterRows($rows, null, null, 'Newton', ['name', 'alp']);
        $this->assertCount(2, $bySection);

        $bySearch = $service->filterRows($rows, 'ana', null, null, ['name', 'alp']);
        $this->assertCount(1, $bySearch);
        $this->assertSame('Cruz, Ana', $bySearch->first()['name']);

        $byAlpSearch = $service->filterRows($rows, 'numeracy', null, null, ['name', 'alp']);
        $this->assertCount(1, $byAlpSearch);
        $this->assertSame('Reyes, Ben', $byAlpSearch->first()['name']);

        $combined = $service->filterRows($rows, null, '7', 'Newton', ['name', 'alp']);
        $this->assertCount(1, $combined);
        $this->assertSame('Santos, Cara', $combined->first()['name']);
    }
}
