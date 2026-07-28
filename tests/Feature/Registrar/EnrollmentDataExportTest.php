<?php

namespace Tests\Feature\Registrar;

use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\Permission;
use App\Models\Registrar\StudentEnrollment;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class EnrollmentDataExportTest extends TestCase
{
    use RefreshDatabase;

    private SchoolYear $schoolYear;
    private Section $sectionA;
    private Section $sectionB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->schoolYear = SchoolYear::create([
            'name' => '2026-2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_current' => true,
            'status' => 'active',
        ]);

        $this->sectionA = Section::create([
            'levelid' => 7,
            'sectionname' => 'Lithium',
            'syid' => 2026,
            'school_year_id' => $this->schoolYear->id,
            'capacity' => 30,
            'is_active' => true,
        ]);

        $this->sectionB = Section::create([
            'levelid' => 8,
            'sectionname' => 'Beryllium',
            'syid' => 2026,
            'school_year_id' => $this->schoolYear->id,
            'capacity' => 30,
            'is_active' => true,
        ]);
    }

    public function test_export_all_grades_includes_only_mapped_fields_and_blanks_the_rest(): void
    {
        $studentId = $this->makeStudent('Dela Cruz', 'Juan', [
            'houseno' => '123', 'barangay' => 'Ambago', 'municipal' => 'Butuan City',
            'province' => 'Agusan del Norte', 'region' => 'Region XIII', 'schooltype' => 'Public',
            'feduc' => 'College Graduate', 'meduc' => 'High School Graduate',
            'foccupation' => 'Engineer', 'moccupation' => 'Teacher', 'remarks' => 'Scholar',
        ]);

        StudentEnrollment::create([
            'student_id' => $studentId,
            'school_year_id' => $this->schoolYear->id,
            'section_id' => $this->sectionA->id,
            'grade_level' => 7,
            'enrollment_type' => 'new',
            'status' => 'enrolled',
            'enrollment_date' => '2026-07-20',
        ]);

        $response = $this->actingAs($this->userWithPermission('students.records.export'))
            ->get(route('registrar.enrollment.export', [
                'school_year_id' => $this->schoolYear->id,
                'scope' => 'all',
            ]));

        $response->assertOk();

        $sheet = $this->readDownloadedSheet($response);

        $this->assertSame(7, $sheet->getCell('B2')->getValue());
        $this->assertSame('Dela Cruz', $sheet->getCell('C2')->getValue());
        $this->assertSame('Juan', $sheet->getCell('D2')->getValue());
        $this->assertSame('Ambago', $sheet->getCell('L2')->getValue());
        $this->assertSame('Butuan City, Agusan del Norte, Region XIII', $sheet->getCell('M2')->getValue());
        $this->assertSame('New', $sheet->getCell('N2')->getValue());
        $this->assertSame('Scholar', $sheet->getCell('AR2')->getValue());

        // Unmapped column (Campus) must stay blank.
        $this->assertSame(null, $sheet->getCell('A2')->getValue());
    }

    public function test_export_scoped_to_grade_level_excludes_other_grades(): void
    {
        $g7 = $this->makeStudent('Santos', 'Maria');
        $g8 = $this->makeStudent('Reyes', 'Pedro');

        StudentEnrollment::create([
            'student_id' => $g7, 'school_year_id' => $this->schoolYear->id,
            'section_id' => $this->sectionA->id, 'grade_level' => 7,
            'enrollment_type' => 'returning', 'status' => 'enrolled', 'enrollment_date' => '2026-07-20',
        ]);
        StudentEnrollment::create([
            'student_id' => $g8, 'school_year_id' => $this->schoolYear->id,
            'section_id' => $this->sectionB->id, 'grade_level' => 8,
            'enrollment_type' => 'returning', 'status' => 'enrolled', 'enrollment_date' => '2026-07-20',
        ]);

        $response = $this->actingAs($this->userWithPermission('students.records.export'))
            ->get(route('registrar.enrollment.export', [
                'school_year_id' => $this->schoolYear->id,
                'scope' => 'grade',
                'grade_level' => 7,
            ]));

        $sheet = $this->readDownloadedSheet($response);

        $this->assertSame('Santos', $sheet->getCell('C2')->getValue());
        $this->assertSame(null, $sheet->getCell('C3')->getValue());
    }

    public function test_export_scoped_to_section(): void
    {
        $studentA = $this->makeStudent('Cruz', 'Ana');
        $studentB = $this->makeStudent('Lim', 'Ben');

        StudentEnrollment::create([
            'student_id' => $studentA, 'school_year_id' => $this->schoolYear->id,
            'section_id' => $this->sectionA->id, 'grade_level' => 7,
            'enrollment_type' => 'returning', 'status' => 'enrolled', 'enrollment_date' => '2026-07-20',
        ]);
        StudentEnrollment::create([
            'student_id' => $studentB, 'school_year_id' => $this->schoolYear->id,
            'section_id' => $this->sectionB->id, 'grade_level' => 8,
            'enrollment_type' => 'returning', 'status' => 'enrolled', 'enrollment_date' => '2026-07-20',
        ]);

        $response = $this->actingAs($this->userWithPermission('students.records.export'))
            ->get(route('registrar.enrollment.export', [
                'school_year_id' => $this->schoolYear->id,
                'scope' => 'section',
                'section_id' => $this->sectionA->id,
            ]));

        $sheet = $this->readDownloadedSheet($response);

        $this->assertSame('Cruz', $sheet->getCell('C2')->getValue());
        $this->assertSame(null, $sheet->getCell('C3')->getValue());
    }

    public function test_export_scoped_to_individual_student(): void
    {
        $studentA = $this->makeStudent('Torres', 'Liza');
        $studentB = $this->makeStudent('Uy', 'Marco');

        StudentEnrollment::create([
            'student_id' => $studentA, 'school_year_id' => $this->schoolYear->id,
            'section_id' => $this->sectionA->id, 'grade_level' => 7,
            'enrollment_type' => 'returning', 'status' => 'enrolled', 'enrollment_date' => '2026-07-20',
        ]);
        StudentEnrollment::create([
            'student_id' => $studentB, 'school_year_id' => $this->schoolYear->id,
            'section_id' => $this->sectionA->id, 'grade_level' => 7,
            'enrollment_type' => 'returning', 'status' => 'enrolled', 'enrollment_date' => '2026-07-20',
        ]);

        $response = $this->actingAs($this->userWithPermission('students.records.export'))
            ->get(route('registrar.enrollment.export', [
                'school_year_id' => $this->schoolYear->id,
                'scope' => 'student',
                'student_id' => $studentA,
            ]));

        $sheet = $this->readDownloadedSheet($response);

        $this->assertSame('Torres', $sheet->getCell('C2')->getValue());
        $this->assertSame(null, $sheet->getCell('C3')->getValue());
    }

    public function test_dropped_students_are_excluded_from_export(): void
    {
        $studentId = $this->makeStudent('Gomez', 'Ian');

        StudentEnrollment::create([
            'student_id' => $studentId, 'school_year_id' => $this->schoolYear->id,
            'section_id' => $this->sectionA->id, 'grade_level' => 7,
            'enrollment_type' => 'returning', 'status' => 'dropped', 'enrollment_date' => '2026-07-20',
        ]);

        $response = $this->actingAs($this->userWithPermission('students.records.export'))
            ->get(route('registrar.enrollment.export', [
                'school_year_id' => $this->schoolYear->id,
                'scope' => 'all',
            ]));

        $sheet = $this->readDownloadedSheet($response);

        $this->assertSame(null, $sheet->getCell('C2')->getValue());
    }

    public function test_view_only_user_cannot_export(): void
    {
        $this->actingAs($this->userWithPermission('students.enrollment.view'))
            ->get(route('registrar.enrollment.export', [
                'school_year_id' => $this->schoolYear->id,
                'scope' => 'all',
            ]))
            ->assertForbidden();
    }

    public function test_export_validates_required_scope_fields(): void
    {
        $this->actingAs($this->userWithPermission('students.records.export'))
            ->get(route('registrar.enrollment.export', [
                'school_year_id' => $this->schoolYear->id,
                'scope' => 'grade',
            ]))
            ->assertSessionHasErrors('grade_level');
    }

    private function makeStudent(string $lastName, string $firstName, array $overrides = []): int
    {
        return DB::table('students')->insertGetId(array_merge([
            'lastname' => $lastName,
            'firstname' => $firstName,
            'middlename' => null,
            'sex' => 'Female',
            'status' => 'active',
        ], $overrides));
    }

    private function readDownloadedSheet($response)
    {
        $spreadsheet = IOFactory::load($response->getFile()->getPathname());

        return $spreadsheet->getActiveSheet();
    }

    private function userWithPermission(string $permissionName): User
    {
        $permission = Permission::firstOrCreate(
            ['name' => $permissionName],
            ['module' => 'Registrar', 'description' => $permissionName],
        );
        $role = Role::create(['name' => 'Registrar Export Test '.uniqid()]);
        $role->permissions()->attach($permission);
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->roles()->attach($role);

        return $user;
    }
}
