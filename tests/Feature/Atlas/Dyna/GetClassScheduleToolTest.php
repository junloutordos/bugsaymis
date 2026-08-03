<?php

namespace Tests\Feature\Atlas\Dyna;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\ClassSchedule;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Atlas\Dyna\Tools\GetClassScheduleTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetClassScheduleToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_a_faculty_members_active_schedule_for_the_current_term(): void
    {
        $schoolYear = SchoolYear::create(['name' => '2026-2027', 'start_date' => '2026-06-01', 'end_date' => '2027-03-31', 'is_current' => true]);
        $term = AcademicTerm::create(['school_year_id' => $schoolYear->id, 'name' => 'Q1', 'is_current' => true]);
        $subject = Subject::create(['name' => 'Physics', 'code' => 'PHYS1', 'grade_level' => 8, 'school_year_id' => $schoolYear->id]);
        $section = Section::create(['sectionname' => 'Newton', 'levelid' => 8]);
        $faculty = User::factory()->create(['name' => 'Maria Santos']);

        ClassSchedule::create([
            'user_id' => $faculty->id,
            'subject_id' => $subject->id,
            'section_id' => $section->id,
            'classroom_id' => null,
            'school_year_id' => $schoolYear->id,
            'academic_term_id' => $term->id,
            'day_of_week' => 'Monday',
            'start_time' => '08:00:00',
            'end_time' => '09:00:00',
            'status' => 'active',
        ]);
        ClassSchedule::create([
            'user_id' => $faculty->id,
            'subject_id' => $subject->id,
            'section_id' => $section->id,
            'classroom_id' => null,
            'school_year_id' => $schoolYear->id,
            'academic_term_id' => $term->id,
            'day_of_week' => 'Tuesday',
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'status' => 'cancelled',
        ]);

        $user = $this->userWithPermissions(['atlas.dyna.access', 'faculty_loading.view']);

        $result = (new GetClassScheduleTool())->execute($user, ['faculty_identifier' => 'Maria Santos']);

        $this->assertCount(1, $result['schedule']);
        $this->assertEquals('Physics', $result['schedule'][0]['subject']);
        $this->assertEquals('Monday', $result['schedule'][0]['day']);
        $this->assertEquals('Newton', $result['schedule'][0]['section']);
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
