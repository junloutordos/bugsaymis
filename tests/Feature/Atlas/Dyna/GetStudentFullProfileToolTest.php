<?php

namespace Tests\Feature\Atlas\Dyna;

use App\Models\FacultyLoading\SchoolYear;
use App\Models\Permission;
use App\Models\Registrar\StudentEnrollment;
use App\Models\Role;
use App\Models\User;
use App\Services\Atlas\Dyna\Tools\GetStudentFullProfileTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetStudentFullProfileToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_enrollment_history_and_omits_discipline_without_access(): void
    {
        $lastname = 'FullProfileLookup'.uniqid();
        $studentId = \DB::table('students')->insertGetId(['lastname' => $lastname, 'firstname' => 'Test']);

        $schoolYear = SchoolYear::create(['name' => '2026-2027', 'start_date' => '2026-06-01', 'end_date' => '2027-03-31', 'is_current' => true]);
        StudentEnrollment::create(['student_id' => $studentId, 'school_year_id' => $schoolYear->id, 'section_id' => 3, 'grade_level' => 8, 'status' => 'enrolled', 'enrollment_date' => now()]);

        $user = $this->userWithPermissions(['atlas.dyna.access', 'students.enrollment.view']);

        $result = app(GetStudentFullProfileTool::class)->execute($user, ['identifier' => $lastname]);

        $this->assertCount(1, $result['enrollment_history']);
        $this->assertEquals(8, $result['enrollment_history'][0]['grade_level']);
        $this->assertArrayNotHasKey('discipline', $result);
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
