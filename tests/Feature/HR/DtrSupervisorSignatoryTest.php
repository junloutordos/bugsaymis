<?php

namespace Tests\Feature\HR;

use App\Models\FacultyLoading\AcademicUnit;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\Division;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The DTR "Verified as to the prescribed office hours" signatory:
 * Teaching staff (Plantilla or COS) must be verified by their Academic
 * Unit Head, not the CID Chief / division chief. The CID Chief /
 * division chief is only a fallback when no AUH is resolvable.
 */
class DtrSupervisorSignatoryTest extends TestCase
{
    use RefreshDatabase;

    private SchoolYear $sy;
    private Division $cid;
    private User $cidChief;
    private User $auh;
    private AcademicUnit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sy = SchoolYear::create([
            'name' => '2098-2099', 'start_date' => '2098-06-01', 'end_date' => '2099-03-31',
            'is_current' => true, 'status' => 'active',
        ]);

        $this->cidChief = User::factory()->create(['name' => 'Dr. CID Chief', 'position' => 'Chief, Curriculum and Instruction Division']);
        $this->cid = Division::create([
            'division_name' => 'Curriculum and Instruction Division',
            'acronym' => 'CID',
            'division_chief_id' => $this->cidChief->id,
            'status' => 'active',
        ]);

        $this->auh = User::factory()->create(['name' => 'Ms. Math Unit Head', 'position' => null]);
        $this->unit = AcademicUnit::create([
            'school_year_id' => $this->sy->id,
            'code' => 'MATH',
            'name' => 'Mathematics Unit',
            'unit_type' => 'department',
            'head_user_id' => $this->auh->id,
            'sort_order' => 1,
            'is_active' => true,
        ]);
    }

    private function viewer(): User
    {
        $permission = Permission::firstOrCreate(
            ['name' => 'hr.dtr.view'],
            ['module' => 'DTR', 'description' => 'View DTR'],
        );
        $role = Role::create(['name' => 'HRDtr_'.uniqid()]);
        $role->permissions()->attach($permission->id);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        return $user->fresh();
    }

    public function test_cos_teacher_is_verified_by_their_academic_unit_head(): void
    {
        $teacher = User::factory()->create([
            'name' => 'Juan COS Teacher',
            'emp_category' => 'COS Teaching',
            'division_id' => $this->cid->id,
            'academic_unit_id' => $this->unit->id,
        ]);

        $this->actingAs($this->viewer())
            ->get(route('hr.dtr.print', ['user' => $teacher->id, 'month' => '2098-08']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('HR/DTR/PrintCsc')
                ->where('supervisor.name', 'Ms. Math Unit Head')
                ->where('supervisor.position', 'Academic Unit Head'));
    }

    public function test_cos_teacher_without_resolvable_unit_falls_back_to_division_chief(): void
    {
        $teacher = User::factory()->create([
            'name' => 'Pedro COS Teacher',
            'emp_category' => 'COS Teaching',
            'division_id' => $this->cid->id,
            'academic_unit_id' => null,
            'office_id' => null,
        ]);

        $this->actingAs($this->viewer())
            ->get(route('hr.dtr.print', ['user' => $teacher->id, 'month' => '2098-08']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('supervisor.name', 'Dr. CID Chief'));
    }

    public function test_non_teaching_employee_still_verified_by_division_chief(): void
    {
        $staff = User::factory()->create([
            'name' => 'Maria NonTeaching',
            'emp_category' => 'Plantilla Non-Teaching',
            'division_id' => $this->cid->id,
            'academic_unit_id' => $this->unit->id, // even with a unit, non-teaching keeps old behavior
        ]);

        $this->actingAs($this->viewer())
            ->get(route('hr.dtr.print', ['user' => $staff->id, 'month' => '2098-08']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('supervisor.name', 'Dr. CID Chief'));
    }

    public function test_plantilla_teacher_is_verified_by_their_academic_unit_head(): void
    {
        $teacher = User::factory()->create([
            'name' => 'Ana Plantilla Teacher',
            'emp_category' => 'Plantilla Teaching',
            'division_id' => $this->cid->id,
            'academic_unit_id' => $this->unit->id,
        ]);

        $this->actingAs($this->viewer())
            ->get(route('hr.dtr.print', ['user' => $teacher->id, 'month' => '2098-08']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('HR/DTR/PrintCsc')
                ->where('supervisor.name', 'Ms. Math Unit Head')
                ->where('supervisor.position', 'Academic Unit Head'));
    }

    public function test_plantilla_teacher_without_resolvable_unit_falls_back_to_division_chief(): void
    {
        $teacher = User::factory()->create([
            'name' => 'Jose Plantilla Teacher',
            'emp_category' => 'Plantilla Teaching',
            'division_id' => $this->cid->id,
            'academic_unit_id' => null,
            'office_id' => null,
        ]);

        $this->actingAs($this->viewer())
            ->get(route('hr.dtr.print', ['user' => $teacher->id, 'month' => '2098-08']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('supervisor.name', 'Dr. CID Chief'));
    }
}
