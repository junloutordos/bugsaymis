<?php

namespace Tests\Feature\ALP;

use App\Models\FacultyLoading\SchoolYear;
use App\Models\Permission;
use App\Models\Registrar\StudentEnrollment;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AlpUnassignedPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_downloads_a_pdf_of_unassigned_grade_7_to_10_students(): void
    {
        $permission = Permission::firstOrCreate(['name' => 'alp.view'], ['module' => 'ALP', 'description' => 'alp.view']);
        $role = Role::create(['name' => 'AlpViewerTester_'.uniqid()]);
        $role->permissions()->attach($permission->id);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        $sy = SchoolYear::create([
            'name' => '2026-2027', 'start_date' => '2026-07-01', 'end_date' => '2027-06-30',
            'is_current' => true, 'status' => 'active',
        ]);
        $studentId = (int) DB::table('students')->insertGetId(['lastname' => 'Dela Cruz', 'firstname' => 'Diego']);
        StudentEnrollment::create([
            'student_id' => $studentId, 'school_year_id' => $sy->id, 'section_id' => null,
            'grade_level' => 7, 'enrollment_type' => 'returning', 'status' => 'enrolled', 'enrollment_date' => '2026-07-20',
        ]);

        $response = $this->actingAs($user)->get(route('alp.unassigned.pdf'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertNotEmpty($response->getContent());
    }

    public function test_pdf_accepts_search_grade_and_section_filters(): void
    {
        $permission = Permission::firstOrCreate(['name' => 'alp.view'], ['module' => 'ALP', 'description' => 'alp.view']);
        $role = Role::create(['name' => 'AlpViewerTester_'.uniqid()]);
        $role->permissions()->attach($permission->id);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        $sy = SchoolYear::create([
            'name' => '2026-2027', 'start_date' => '2026-07-01', 'end_date' => '2027-06-30',
            'is_current' => true, 'status' => 'active',
        ]);
        $studentId = (int) DB::table('students')->insertGetId(['lastname' => 'Dela Cruz', 'firstname' => 'Diego']);
        StudentEnrollment::create([
            'student_id' => $studentId, 'school_year_id' => $sy->id, 'section_id' => null,
            'grade_level' => 7, 'enrollment_type' => 'returning', 'status' => 'enrolled', 'enrollment_date' => '2026-07-20',
        ]);

        $response = $this->actingAs($user)->get(route('alp.unassigned.pdf', [
            'search' => 'diego', 'grade' => 7,
        ]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertNotEmpty($response->getContent());
    }
}
