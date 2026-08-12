<?php

namespace Tests\Feature;

use App\Models\FacultyLoading\SchoolYear;
use App\Models\Registrar\StudentEnrollment;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StudentControllerDestroyTest extends TestCase
{
    use RefreshDatabase;

    private function makeStudent(string $lastname, string $firstname): int
    {
        return (int) DB::table('students')->insertGetId(['lastname' => $lastname, 'firstname' => $firstname]);
    }

    public function test_non_administrator_cannot_delete_a_student(): void
    {
        $studentId = $this->makeStudent('Cruz', 'Ana');
        $role = Role::create(['name' => 'RegularStaffTester_'.uniqid()]);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        $response = $this->actingAs($user)->delete(route('students.destroy', $studentId));

        $response->assertForbidden();
        $this->assertDatabaseHas('students', ['id' => $studentId]);
    }

    public function test_administrator_cannot_delete_a_student_with_enrollment_records(): void
    {
        $studentId = $this->makeStudent('Dela Cruz', 'Diego');
        $sy = SchoolYear::create([
            'name' => '2026-2027', 'start_date' => '2026-07-01', 'end_date' => '2027-06-30',
            'is_current' => true, 'status' => 'active',
        ]);
        StudentEnrollment::create([
            'student_id' => $studentId, 'school_year_id' => $sy->id, 'section_id' => null,
            'grade_level' => 7, 'enrollment_type' => 'new', 'status' => 'enrolled', 'enrollment_date' => '2026-07-30',
        ]);
        $admin = User::factory()->create();
        $adminRole = Role::create(['name' => 'Administrator']);
        $admin->roles()->attach($adminRole->id);

        $response = $this->actingAs($admin)->delete(route('students.destroy', $studentId));

        $response->assertStatus(422);
        $this->assertDatabaseHas('students', ['id' => $studentId]);
    }

    public function test_administrator_can_delete_a_student_with_no_enrollment_records(): void
    {
        $studentId = $this->makeStudent('Reyes', 'Ben');
        $admin = User::factory()->create();
        $adminRole = Role::create(['name' => 'Administrator']);
        $admin->roles()->attach($adminRole->id);

        $response = $this->actingAs($admin)->delete(route('students.destroy', $studentId));

        $response->assertRedirect(route('students.index'));
        $this->assertDatabaseMissing('students', ['id' => $studentId]);
    }
}
