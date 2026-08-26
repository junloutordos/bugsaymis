<?php

namespace Tests\Feature\HR;

use App\Models\Pds;
use App\Models\PDSPersonalInfo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeIdCardRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_self_service_card_includes_dob_and_residential_address(): void
    {
        $user = User::factory()->create(['name' => 'Cruz, Juan A.', 'employee_idno_new' => 'E13-2020-01-001']);
        $pds = Pds::create(['user_id' => $user->id]);
        PDSPersonalInfo::create([
            'pds_id' => $pds->id, 'surname' => 'Cruz', 'first_name' => 'Juan',
            'date_of_birth' => '1990-05-15',
            'residential_house' => '123 Main St',
            'residential_barangay' => 'Ampayon',
            'residential_city' => 'Butuan City',
            'residential_province' => 'Agusan del Norte',
        ]);
        \App\Models\HR\EmployeeProfile::create([
            'user_id' => $user->id,
            'emergency_contact_name' => 'Maria Cruz',
            'emergency_contact_phone' => '09171234567',
            'emergency_contact_address' => 'Brgy. Libertad, Butuan City',
        ]);

        $response = $this->actingAs($user)->get(route('profile.id-card'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Profile/IdCard')
            ->where('employee.date_of_birth', 'May 15, 1990')
            ->where('employee.residential_address', '123 Main St, Brgy. Ampayon, Butuan City, Agusan del Norte')
            ->where('employee.employee_no', 'E13-2020-01-001')
            ->where('emergency.contact_name', 'Maria Cruz')
            ->where('emergency.contact_phone', '09171234567')
            ->where('emergency.contact_address', 'Brgy. Libertad, Butuan City')
            ->has('qr_svg')
            ->has('back_route')
        );
    }

    public function test_self_service_card_omits_dob_and_address_when_no_pds(): void
    {
        $user = User::factory()->create(['employee_idno_new' => 'E13-2020-01-001']);

        $response = $this->actingAs($user)->get(route('profile.id-card'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('employee.date_of_birth', null)
            ->where('employee.residential_address', null)
        );
    }

    public function test_hr_admin_can_print_any_employee_id_card(): void
    {
        $admin = $this->userWithPermission('hr.employees.manage');
        $employee = User::factory()->create(['name' => 'Santos, Pedro', 'employee_idno_new' => 'E13-2019-06-004']);

        $response = $this->actingAs($admin)->get(route('hr.employees.id-card', $employee->id));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Profile/IdCard')
            ->where('employee.employee_no', 'E13-2019-06-004')
            ->where('back_route', route('hr.employees.index'))
        );
    }

    public function test_hr_id_card_forbidden_without_permission(): void
    {
        $staff = User::factory()->create();
        $employee = User::factory()->create();

        $this->actingAs($staff)->get(route('hr.employees.id-card', $employee->id))->assertForbidden();
    }

    private function userWithPermission(string $permissionName): User
    {
        $role = \App\Models\Role::create(['name' => 'Test Role '.uniqid()]);
        $permission = \App\Models\Permission::firstOrCreate(['name' => $permissionName], ['module' => 'HR', 'description' => $permissionName]);
        $role->permissions()->attach($permission);
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }
}
