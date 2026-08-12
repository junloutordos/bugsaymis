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

class AlpUnassignedListTest extends TestCase
{
    use RefreshDatabase;

    private function alpUser(): User
    {
        $permission = Permission::firstOrCreate(['name' => 'alp.view'], ['module' => 'ALP', 'description' => 'alp.view']);
        $role = Role::create(['name' => 'AlpViewerTester_'.uniqid()]);
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

    public function test_lists_enrolled_grade_7_to_10_students_without_an_active_alp_membership(): void
    {
        $user = $this->alpUser();
        $sy = $this->currentSchoolYear();
        $section = Section::create([
            'levelid' => 7, 'sectionname' => 'Curie', 'syid' => 2026,
            'school_year_id' => $sy->id, 'capacity' => 30, 'is_active' => true,
        ]);

        // Unassigned, in range — must appear.
        $unassigned = $this->makeStudent('Dela Cruz', 'Diego');
        $this->enroll($unassigned, $sy, $section->id, 7);

        // Assigned via an active membership with a grade 7-10 enrollment — must NOT appear.
        $assigned = $this->makeStudent('Enriquez', 'Elena');
        $assignedEnrollment = $this->enroll($assigned, $sy, null, 9);
        $program = AlpProgram::create(['code' => 'ALP-'.uniqid(), 'name' => 'Numeracy Bridge Program', 'status' => 'active']);
        $cycle = AlpProgramCycle::create(['alp_program_id' => $program->id, 'school_year_id' => $sy->id, 'status' => 'draft']);
        AlpMembership::create([
            'alp_program_cycle_id' => $cycle->id, 'school_year_id' => $sy->id, 'student_id' => $assigned,
            'student_enrollment_id' => $assignedEnrollment->id, 'status' => 'active', 'joined_at' => '2026-07-20',
        ]);

        // Enrolled but out of the grade 7-10 range — must NOT appear.
        $outOfRange = $this->makeStudent('Fajardo', 'Fina');
        $this->enroll($outOfRange, $sy, null, 6);

        $response = $this->actingAs($user)->get(route('alp.unassigned.index'));

        $response->assertInertia(fn ($page) => $page
            ->component('CID/ALP/Unassigned')
            ->has('students', 1)
            ->where('students.0.name', 'Dela Cruz, Diego')
            ->where('students.0.grade_level', 7)
            ->where('students.0.section', 'Curie'));
    }
}
