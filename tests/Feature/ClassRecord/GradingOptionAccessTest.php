<?php

namespace Tests\Feature\ClassRecord;

use App\Models\ClassRecord\GradingOption;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\AcademicUnit;
use App\Models\FacultyLoading\Designation;
use App\Models\FacultyLoading\DesignationCategory;
use App\Models\FacultyLoading\FacultyLoad;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Subject;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Grading Options: who can MANAGE (create/edit/delete) an option is scoped
 * per academic unit via a designation-based AUH assignment
 * (GradingOptionScopeService), independent of the legacy
 * class-records.grading-options permission/AUH role. Who can SELECT/USE an
 * option on a class record is intentionally NOT scoped — every active option
 * is visible and usable by every teacher regardless of unit (2026-07-24).
 */
class GradingOptionAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_designation_assigned_auh_without_role_can_create_a_scoped_option(): void
    {
        [$term, $designation] = $this->currentTermAndAuhDesignation('MATH');
        $auh = $this->assignAuhDesignation($term, $designation);

        $response = $this->actingAs($auh)->postJson(route('grading-options.store'), [
            'name' => 'Math Alternative Grading',
            'is_active' => true,
            'categories' => [
                ['name' => 'Assessments', 'code' => 'AA', 'weight' => 1, 'max_assessments' => 1, 'sort_order' => 1],
            ],
        ]);

        $response->assertCreated();
        $this->assertSame($designation->id, $response->json('data.owner_designation_id'));
    }

    public function test_ordinary_faculty_without_designation_or_admin_permission_cannot_manage(): void
    {
        $faculty = User::factory()->create(['email_verified_at' => now()]);

        $response = $this->actingAs($faculty)->postJson(route('grading-options.store'), [
            'name' => 'Should Not Be Created',
            'is_active' => true,
            'categories' => [
                ['name' => 'Assessments', 'code' => 'AA', 'weight' => 1, 'max_assessments' => 1, 'sort_order' => 1],
            ],
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('grading_options', ['name' => 'Should Not Be Created']);
    }

    public function test_auh_cannot_modify_a_campus_wide_global_option(): void
    {
        [$term, $designation] = $this->currentTermAndAuhDesignation('MATH');
        $auh = $this->assignAuhDesignation($term, $designation);

        $global = GradingOption::create(['name' => 'Standard', 'is_active' => true]);

        $update = $this->actingAs($auh)->putJson(route('grading-options.update', $global), [
            'name' => 'Renamed', 'is_active' => true,
        ]);
        $update->assertForbidden();

        $destroy = $this->actingAs($auh)->deleteJson(route('grading-options.destroy', $global));
        $destroy->assertForbidden();
    }

    public function test_auh_cannot_modify_another_units_option(): void
    {
        [$term, $mathDesignation] = $this->currentTermAndAuhDesignation('MATH');
        [, $sciDesignation] = $this->currentTermAndAuhDesignation('SCI', $term->schoolYear);
        $mathAuh = $this->assignAuhDesignation($term, $mathDesignation);

        $sciOption = GradingOption::create([
            'name' => 'Science Grading', 'is_active' => true, 'owner_designation_id' => $sciDesignation->id,
        ]);

        $response = $this->actingAs($mathAuh)->putJson(route('grading-options.update', $sciOption), [
            'name' => 'Hijacked', 'is_active' => true,
        ]);

        $response->assertForbidden();
    }

    public function test_auh_can_edit_and_delete_their_own_units_option(): void
    {
        [$term, $designation] = $this->currentTermAndAuhDesignation('MATH');
        $auh = $this->assignAuhDesignation($term, $designation);

        $option = GradingOption::create([
            'name' => 'Math Grading', 'is_active' => true, 'owner_designation_id' => $designation->id,
        ]);

        $update = $this->actingAs($auh)->putJson(route('grading-options.update', $option), [
            'name' => 'Math Grading v2', 'is_active' => true,
        ]);
        $update->assertOk();
        $this->assertSame('Math Grading v2', $option->fresh()->name);

        $destroy = $this->actingAs($auh)->deleteJson(route('grading-options.destroy', $option));
        $destroy->assertOk();
        $this->assertDatabaseMissing('grading_options', ['id' => $option->id]);
    }

    public function test_teacher_sees_every_active_grading_option_regardless_of_unit(): void
    {
        [$term, $mathDesignation] = $this->currentTermAndAuhDesignation('MATH');
        [, $sciDesignation] = $this->currentTermAndAuhDesignation('SCI', $term->schoolYear);

        GradingOption::create(['name' => 'Standard', 'is_active' => true]);
        GradingOption::create([
            'name' => 'Math Grading', 'is_active' => true, 'owner_designation_id' => $mathDesignation->id,
        ]);
        GradingOption::create([
            'name' => 'Science Grading', 'is_active' => true, 'owner_designation_id' => $sciDesignation->id,
        ]);
        GradingOption::create(['name' => 'Retired Option', 'is_active' => false]);

        $mathUnit = AcademicUnit::where('code', 'MATH')->firstOrFail();
        $subject = Subject::create([
            'school_year_id' => $term->schoolYear->id, 'code' => 'MATH7', 'name' => 'Mathematics 7',
            'credit_units' => 3, 'lecture_hours' => 3, 'load_units' => 3, 'subject_type' => 'lecture',
            'grade_level' => 7, 'sessions_per_week' => 5, 'minutes_per_session' => 60, 'is_active' => true,
            'academic_unit_id' => $mathUnit->id,
        ]);
        $teacher = User::factory()->create(['email_verified_at' => now()]);
        $facultyLoad = FacultyLoad::create([
            'user_id' => $teacher->id, 'school_year_id' => $term->schoolYear->id, 'academic_term_id' => $term->id,
        ]);
        LoadAssignment::create([
            'faculty_load_id' => $facultyLoad->id, 'user_id' => $teacher->id,
            'school_year_id' => $term->schoolYear->id, 'academic_term_id' => $term->id,
            'assignment_type' => 'teaching', 'subject_id' => $subject->id, 'load_units' => 3,
        ]);

        $response = $this->actingAs($teacher)->getJson(route('grading-options.index'));

        $response->assertOk();
        $names = collect($response->json())->pluck('name');
        $this->assertTrue($names->contains('Standard'));
        $this->assertTrue($names->contains('Math Grading'));
        $this->assertTrue($names->contains('Science Grading'));
        $this->assertFalse($names->contains('Retired Option'));
    }

    public function test_cross_unit_grading_option_is_allowed_on_class_record_creation(): void
    {
        [$term, $mathDesignation] = $this->currentTermAndAuhDesignation('MATH');
        [, $sciDesignation] = $this->currentTermAndAuhDesignation('SCI', $term->schoolYear);

        $mathUnit = AcademicUnit::where('code', 'MATH')->firstOrFail();
        // Cross-section subject type so no section_id is required for this test.
        $subject = Subject::create([
            'school_year_id' => $term->schoolYear->id, 'code' => 'MATHELEC', 'name' => 'Math Elective',
            'credit_units' => 3, 'lecture_hours' => 3, 'load_units' => 3, 'subject_type' => 'elective',
            'grade_level' => 0, 'sessions_per_week' => 5, 'minutes_per_session' => 60, 'is_active' => true,
            'academic_unit_id' => $mathUnit->id,
        ]);
        $sciOption = GradingOption::create([
            'name' => 'Science Grading', 'is_active' => true, 'owner_designation_id' => $sciDesignation->id,
        ]);

        $teacher = User::factory()->create(['email_verified_at' => now()]);
        $facultyLoad = FacultyLoad::create([
            'user_id' => $teacher->id, 'school_year_id' => $term->schoolYear->id, 'academic_term_id' => $term->id,
        ]);
        LoadAssignment::create([
            'faculty_load_id' => $facultyLoad->id, 'user_id' => $teacher->id,
            'school_year_id' => $term->schoolYear->id, 'academic_term_id' => $term->id,
            'assignment_type' => 'teaching', 'subject_id' => $subject->id, 'load_units' => 3,
        ]);

        $response = $this->actingAs($teacher)->postJson(route('class-records.store'), [
            'subject_id' => $subject->id,
            'grading_option_id' => $sciOption->id,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('class_records', [
            'subject_id' => $subject->id, 'grading_option_id' => $sciOption->id,
        ]);
    }

    public function test_inactive_grading_option_is_rejected_server_side_on_class_record_creation(): void
    {
        [$term] = $this->currentTermAndAuhDesignation('MATH');

        $subject = Subject::create([
            'school_year_id' => $term->schoolYear->id, 'code' => 'MATHELEC2', 'name' => 'Math Elective 2',
            'credit_units' => 3, 'lecture_hours' => 3, 'load_units' => 3, 'subject_type' => 'elective',
            'grade_level' => 0, 'sessions_per_week' => 5, 'minutes_per_session' => 60, 'is_active' => true,
        ]);
        $inactiveOption = GradingOption::create(['name' => 'Retired Option', 'is_active' => false]);

        $teacher = User::factory()->create(['email_verified_at' => now()]);
        $facultyLoad = FacultyLoad::create([
            'user_id' => $teacher->id, 'school_year_id' => $term->schoolYear->id, 'academic_term_id' => $term->id,
        ]);
        LoadAssignment::create([
            'faculty_load_id' => $facultyLoad->id, 'user_id' => $teacher->id,
            'school_year_id' => $term->schoolYear->id, 'academic_term_id' => $term->id,
            'assignment_type' => 'teaching', 'subject_id' => $subject->id, 'load_units' => 3,
        ]);

        $response = $this->actingAs($teacher)->postJson(route('class-records.store'), [
            'subject_id' => $subject->id,
            'grading_option_id' => $inactiveOption->id,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('grading_option_id');
        $this->assertDatabaseMissing('class_records', ['subject_id' => $subject->id]);
    }

    public function test_administrator_retains_full_access_across_all_units(): void
    {
        [$term, $mathDesignation] = $this->currentTermAndAuhDesignation('MATH');
        $admin = $this->adminUser();

        // Admin can create scoped to any unit, including one it does not hold itself.
        $response = $this->actingAs($admin)->postJson(route('grading-options.store'), [
            'name' => 'Admin Created Math Option',
            'is_active' => true,
            'owner_designation_id' => $mathDesignation->id,
            'categories' => [
                ['name' => 'Assessments', 'code' => 'AA', 'weight' => 1, 'max_assessments' => 1, 'sort_order' => 1],
            ],
        ]);
        $response->assertCreated();
        $this->assertSame($mathDesignation->id, $response->json('data.owner_designation_id'));

        $option = GradingOption::findOrFail($response->json('data.id'));
        $update = $this->actingAs($admin)->putJson(route('grading-options.update', $option), [
            'name' => 'Renamed by Admin', 'is_active' => true,
        ]);
        $update->assertOk();
    }

    // ── Fixtures ─────────────────────────────────────────────────────────────

    /** @return array{0: AcademicTerm, 1: Designation} */
    private function currentTermAndAuhDesignation(string $unitCode, ?SchoolYear $schoolYear = null): array
    {
        $sy = $schoolYear ?? SchoolYear::create([
            'name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30',
            'is_current' => true, 'status' => 'active',
        ]);
        $term = AcademicTerm::firstOrCreate(
            ['school_year_id' => $sy->id, 'is_current' => true],
            ['name' => '1st Semester', 'term_type' => '1st_semester', 'start_date' => '2025-08-01', 'end_date' => '2025-12-31'],
        );
        AcademicUnit::firstOrCreate(
            ['code' => $unitCode],
            ['name' => $unitCode.' Department', 'unit_type' => 'department', 'is_active' => true, 'school_year_id' => $sy->id],
        );
        $category = DesignationCategory::firstOrCreate(
            ['code' => 'AUH'],
            ['name' => 'Academic Unit Head', 'is_active' => true],
        );
        $designation = Designation::create([
            'designation_category_id' => $category->id,
            'code' => 'AUH-'.$unitCode,
            'name' => 'Academic Unit Head - '.$unitCode,
            'load_units' => 3,
            'assignment_type' => 'admin',
            'is_active' => true,
        ]);

        return [$term, $designation];
    }

    private function assignAuhDesignation(AcademicTerm $term, Designation $designation): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $facultyLoad = FacultyLoad::create([
            'user_id' => $user->id, 'school_year_id' => $term->school_year_id, 'academic_term_id' => $term->id,
        ]);
        LoadAssignment::create([
            'faculty_load_id' => $facultyLoad->id, 'user_id' => $user->id,
            'school_year_id' => $term->school_year_id, 'academic_term_id' => $term->id,
            'assignment_type' => 'admin', 'load_units' => 3,
            'description' => $designation->name, 'designation_id' => $designation->id,
        ]);

        return $user;
    }

    private function adminUser(): User
    {
        $permission = Permission::firstOrCreate(
            ['name' => 'class-records.admin'],
            ['module' => 'Class Records', 'description' => 'class-records.admin'],
        );
        $role = Role::create(['name' => 'Class Record Test Admin '.uniqid()]);
        $role->permissions()->attach($permission);
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->roles()->attach($role);

        return $user;
    }

}
