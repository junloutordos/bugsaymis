<?php

namespace Tests\Feature\HR;

use App\Models\HR\EmployeeProfile;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserEditExtraFieldsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('s3');
    }

    public function test_admin_can_update_employee_idno_new_via_edit_endpoint(): void
    {
        $admin = $this->userWithPermissions(['users.view', 'hr.employees.manage']);
        $employee = User::factory()->create();

        $response = $this->actingAs($admin)->put(route('users.update', $employee->id), $this->baseFields($employee) + [
            'employee_idno_new' => 'E13-2020-06-004',
        ]);

        $response->assertRedirect();
        $this->assertSame('E13-2020-06-004', $employee->refresh()->employee_idno_new);
    }

    public function test_employee_idno_new_must_match_pattern(): void
    {
        $admin = $this->userWithPermissions(['users.view', 'hr.employees.manage']);
        $employee = User::factory()->create();

        $response = $this->actingAs($admin)->put(route('users.update', $employee->id), $this->baseFields($employee) + [
            'employee_idno_new' => 'not-a-valid-format',
        ]);

        $response->assertSessionHasErrors('employee_idno_new');
    }

    public function test_admin_can_update_emergency_contact_via_edit_endpoint(): void
    {
        $admin = $this->userWithPermissions(['users.view', 'hr.employees.manage']);
        $employee = User::factory()->create();

        $response = $this->actingAs($admin)->put(route('users.update', $employee->id), $this->baseFields($employee) + [
            'emergency_contact_name' => 'Maria Santos',
            'emergency_contact_phone' => '09171234567',
            'emergency_contact_address' => 'Brgy. Ampayon, Butuan City',
        ]);

        $response->assertRedirect();
        $employee->refresh();
        $this->assertSame('Maria Santos', $employee->employeeProfile->emergency_contact_name);
        $this->assertSame('09171234567', $employee->employeeProfile->emergency_contact_phone);
        $this->assertSame('Brgy. Ampayon, Butuan City', $employee->employeeProfile->emergency_contact_address);
    }

    public function test_edit_endpoint_forbidden_without_hr_employees_manage_permission(): void
    {
        $staff = $this->userWithPermissions(['users.view']);
        $employee = User::factory()->create();

        $this->actingAs($staff)->put(route('users.update', $employee->id), $this->baseFields($employee))
            ->assertForbidden();
    }

    public function test_photo_update_stores_to_s3_and_updates_profile_picture(): void
    {
        $admin = $this->userWithPermissions(['users.view', 'hr.employees.manage']);
        $employee = User::factory()->create(['profile_picture' => null]);

        $dataUri = 'data:image/jpeg;base64,' . base64_encode('fake-image-bytes');

        $response = $this->actingAs($admin)->post(route('users.photo.update', ['user' => $employee->id]), [
            'photo_base64' => $dataUri,
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $employee->refresh();
        $this->assertNotNull($employee->profile_picture);
        Storage::disk('s3')->assertExists($employee->profile_picture);
    }

    public function test_photo_update_deletes_old_photo(): void
    {
        $admin = $this->userWithPermissions(['users.view', 'hr.employees.manage']);
        Storage::disk('s3')->put('profile_pictures/old.jpg', 'old-bytes');
        $employee = User::factory()->create(['profile_picture' => 'profile_pictures/old.jpg']);

        $dataUri = 'data:image/jpeg;base64,' . base64_encode('new-image-bytes');

        $this->actingAs($admin)->post(route('users.photo.update', ['user' => $employee->id]), [
            'photo_base64' => $dataUri,
        ]);

        Storage::disk('s3')->assertMissing('profile_pictures/old.jpg');
    }

    public function test_photo_update_rejects_invalid_data_uri(): void
    {
        $admin = $this->userWithPermissions(['users.view', 'hr.employees.manage']);
        $employee = User::factory()->create();

        $response = $this->actingAs($admin)->post(route('users.photo.update', ['user' => $employee->id]), [
            'photo_base64' => 'not-a-data-uri',
        ]);

        $response->assertStatus(422);
    }

    public function test_photo_update_forbidden_without_permission(): void
    {
        $staff = $this->userWithPermissions(['users.view']);
        $employee = User::factory()->create();

        $this->actingAs($staff)->post(route('users.photo.update', ['user' => $employee->id]), [
            'photo_base64' => 'data:image/jpeg;base64,' . base64_encode('bytes'),
        ])->assertForbidden();
    }

    private function baseFields(User $user): array
    {
        return [
            'name' => $user->name,
            'sex' => $user->sex ?? 'Male',
            'email' => $user->email,
        ];
    }

    private function userWithPermissions(array $permissionNames): User
    {
        $role = Role::create(['name' => 'Test Role '.uniqid()]);
        foreach ($permissionNames as $name) {
            $permission = Permission::firstOrCreate(['name' => $name], ['module' => 'HR', 'description' => $name]);
            $role->permissions()->attach($permission);
        }
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }
}
