<?php

namespace Tests\Feature\StudentAttendance;

use App\Models\Permission;
use App\Models\Role;
use App\Models\StudentAttendance\ParentContact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParentContactControllerTest extends TestCase
{
    use RefreshDatabase;

    private function staffUser(): User
    {
        $role = Role::create(['name' => 'Front Desk '.uniqid()]);
        $permission = Permission::firstOrCreate(
            ['name' => 'students.attendance.view'],
            ['module' => 'StudentAttendance', 'description' => 'students.attendance.view']
        );
        $role->permissions()->attach($permission);

        $user = User::factory()->create(['account_type' => 'employee']);
        $user->roles()->attach($role);

        return $user;
    }

    public function test_staff_can_list_parent_contacts(): void
    {
        ParentContact::create(['name' => 'Juan Dela Cruz', 'email' => 'juan@example.com']);

        $response = $this->actingAs($this->staffUser())->get(route('student-attendance.parents.index'));

        $response->assertOk();
    }

    public function test_staff_can_create_a_front_desk_parent_contact_without_login_credentials(): void
    {
        $response = $this->actingAs($this->staffUser())->post(route('student-attendance.parents.store'), [
            'name'         => 'Juan Dela Cruz',
            'email'        => 'juan@example.com',
            'mobile_phone' => '09171234567',
            'notify_email' => true,
            'notify_push'  => false,
            'notify_sms'   => false,
        ]);

        $response->assertRedirect();

        $contact = ParentContact::where('email', 'juan@example.com')->first();
        $this->assertNotNull($contact);
        $this->assertNull($contact->password);
        $this->assertNull($contact->user_id);
    }
}
