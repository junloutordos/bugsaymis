<?php

namespace Tests\Feature\Atlas\Dyna;

use App\Models\FacultyLoading\SchoolYear;
use App\Models\Permission;
use App\Models\Registrar\StudentEnrollment;
use App\Models\Role;
use App\Models\User;
use App\Services\Atlas\Dyna\Tools\GetStudentInfoTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetStudentInfoToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_enrollment_and_attendance_summary_without_discipline_access(): void
    {
        // students is a legacy table on the app's default connection (App\Models\Student
        // declares no $connection override) -- RefreshDatabase can't roll it back reliably
        // between tests (MyISAM), so use a name unique to this test.
        $lastname = 'StudentInfoLookup'.uniqid();
        $studentId = \DB::table('students')->insertGetId(['lastname' => $lastname, 'firstname' => 'Test']);

        // student_enrollments requires school_year_id, section_id, grade_level (tinyint),
        // and enrollment_date — confirmed via
        // database/migrations/*_create_student_enrollments_table.php (see Task 4).
        $schoolYear = SchoolYear::create(['name' => '2026-2027', 'start_date' => '2026-06-01', 'end_date' => '2027-03-31', 'is_current' => true]);
        StudentEnrollment::create(['student_id' => $studentId, 'school_year_id' => $schoolYear->id, 'section_id' => 1, 'grade_level' => 9, 'status' => 'enrolled', 'enrollment_date' => now()]);

        $user = $this->userWithPermissions(['atlas.dyna.access', 'students.enrollment.view']);

        $result = (new GetStudentInfoTool())->execute($user, ['identifier' => $lastname]);

        $this->assertEquals('enrolled', $result['enrollment_status']);
        $this->assertEquals(9, $result['grade_level']);
        $this->assertArrayNotHasKey('discipline_cases', $result);
    }

    private function userWithPermissions(array $permissionNames): User
    {
        $role = Role::create(['name' => 'Dyna Test '.uniqid()]);
        foreach ($permissionNames as $name) {
            $permission = Permission::firstOrCreate(['name' => $name], ['module' => 'Atlas', 'description' => $name]);
            $role->permissions()->attach($permission);
        }
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }
}
