<?php

namespace Tests\Feature\Registrar;

use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\Permission;
use App\Models\Registrar\StudentEnrollment;
use App\Models\Role;
use App\Models\StudentClearance\StudentClearance;
use App\Models\StudentClearance\StudentClearancePeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EnrollmentSectionTransferTest extends TestCase
{
    use RefreshDatabase;

    private SchoolYear $schoolYear;
    private Section $source;
    private Section $target;
    private StudentEnrollment $enrollment;

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
        $this->source = $this->makeSection('Lithium');
        $this->target = $this->makeSection('Beryllium');
        $this->enrollment = StudentEnrollment::create([
            'student_id' => 1001,
            'school_year_id' => $this->schoolYear->id,
            'section_id' => $this->source->id,
            'grade_level' => 7,
            'enrollment_type' => 'returning',
            'status' => 'enrolled',
            'enrollment_date' => '2026-07-20',
        ]);

        DB::table('section_students')->insert([
            'studentid' => $this->enrollment->student_id,
            'levelid' => 7,
            'sectionid' => $this->source->id,
            'syid' => 2026,
        ]);
    }

    public function test_manager_can_transfer_student_and_sync_section_snapshots(): void
    {
        $period = StudentClearancePeriod::create([
            'school_year_id' => $this->schoolYear->id,
            'title' => 'Year-end Clearance',
            'status' => 'open',
        ]);
        $clearance = StudentClearance::create([
            'student_clearance_period_id' => $period->id,
            'school_year_id' => $this->schoolYear->id,
            'student_id' => $this->enrollment->student_id,
            'student_enrollment_id' => $this->enrollment->id,
            'section_id' => $this->source->id,
            'grade_level' => 7,
            'status' => 'open',
        ]);

        $this->actingAs($this->userWithPermission('students.enrollment.manage'))
            ->put(route('registrar.enrollment.transfer-section', $this->enrollment), [
                'section_id' => $this->target->id,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('student_enrollments', [
            'id' => $this->enrollment->id,
            'section_id' => $this->target->id,
            'grade_level' => 7,
            'status' => 'enrolled',
        ]);
        $this->assertDatabaseHas('section_students', [
            'studentid' => $this->enrollment->student_id,
            'syid' => 2026,
            'sectionid' => $this->target->id,
            'levelid' => 7,
        ]);
        $this->assertDatabaseHas('student_clearances', [
            'id' => $clearance->id,
            'section_id' => $this->target->id,
            'grade_level' => 7,
        ]);
    }

    public function test_transfer_rejects_a_full_target_section(): void
    {
        $this->target->update(['capacity' => 1]);
        StudentEnrollment::create([
            'student_id' => 1002,
            'school_year_id' => $this->schoolYear->id,
            'section_id' => $this->target->id,
            'grade_level' => 7,
            'enrollment_type' => 'returning',
            'status' => 'enrolled',
            'enrollment_date' => '2026-07-20',
        ]);

        $this->actingAs($this->userWithPermission('students.enrollment.manage'))
            ->put(route('registrar.enrollment.transfer-section', $this->enrollment), [
                'section_id' => $this->target->id,
            ])
            ->assertSessionHasErrors('section_id');

        $this->assertDatabaseHas('student_enrollments', [
            'id' => $this->enrollment->id,
            'section_id' => $this->source->id,
        ]);
    }

    public function test_transfer_rejects_a_different_grade_school_year_or_inactive_section(): void
    {
        $otherGrade = $this->makeSection('Calcium', ['levelid' => 8]);

        $this->actingAs($this->userWithPermission('students.enrollment.manage'))
            ->put(route('registrar.enrollment.transfer-section', $this->enrollment), [
                'section_id' => $otherGrade->id,
            ])
            ->assertSessionHasErrors('section_id');

        $otherSchoolYear = SchoolYear::create([
            'name' => '2027-2028',
            'start_date' => '2027-07-01',
            'end_date' => '2028-06-30',
            'is_current' => false,
            'status' => 'active',
        ]);
        $otherYear = Section::create([
            'levelid' => 7,
            'sectionname' => 'Magnesium',
            'syid' => 2027,
            'school_year_id' => $otherSchoolYear->id,
            'capacity' => 30,
            'is_active' => true,
        ]);

        $this->actingAs($this->userWithPermission('students.enrollment.manage'))
            ->put(route('registrar.enrollment.transfer-section', $this->enrollment), [
                'section_id' => $otherYear->id,
            ])
            ->assertSessionHasErrors('section_id');

        $this->target->update(['is_active' => false]);

        $this->actingAs($this->userWithPermission('students.enrollment.manage'))
            ->put(route('registrar.enrollment.transfer-section', $this->enrollment), [
                'section_id' => $this->target->id,
            ])
            ->assertSessionHasErrors('section_id');

        $this->assertSame($this->source->id, $this->enrollment->fresh()->section_id);
    }

    public function test_transfer_rejects_same_section_and_non_enrolled_student(): void
    {
        $manager = $this->userWithPermission('students.enrollment.manage');

        $this->actingAs($manager)
            ->put(route('registrar.enrollment.transfer-section', $this->enrollment), [
                'section_id' => $this->source->id,
            ])
            ->assertSessionHasErrors('section_id');

        $this->enrollment->update(['status' => 'dropped']);

        $this->actingAs($manager)
            ->put(route('registrar.enrollment.transfer-section', $this->enrollment), [
                'section_id' => $this->target->id,
            ])
            ->assertSessionHasErrors('section_id');
    }

    public function test_view_only_user_cannot_transfer_a_student(): void
    {
        $this->actingAs($this->userWithPermission('students.enrollment.view'))
            ->put(route('registrar.enrollment.transfer-section', $this->enrollment), [
                'section_id' => $this->target->id,
            ])
            ->assertForbidden();

        $this->assertSame($this->source->id, $this->enrollment->fresh()->section_id);
    }

    private function makeSection(string $name, array $overrides = []): Section
    {
        return Section::create(array_merge([
            'levelid' => 7,
            'sectionname' => $name,
            'syid' => 2026,
            'school_year_id' => $this->schoolYear->id,
            'capacity' => 30,
            'is_active' => true,
        ], $overrides));
    }

    private function userWithPermission(string $permissionName): User
    {
        $permission = Permission::firstOrCreate(
            ['name' => $permissionName],
            ['module' => 'Registrar', 'description' => $permissionName],
        );
        $role = Role::create(['name' => 'Registrar Test '.uniqid()]);
        $role->permissions()->attach($permission);
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->roles()->attach($role);

        return $user;
    }
}
