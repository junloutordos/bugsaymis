<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentParentLoginRejectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_student_account_type_user_cannot_login_to_main_atlas(): void
    {
        $user = User::factory()->create(['account_type' => 'student']);

        $response = $this->post('/login', [
            'email'    => $user->email,
            'password' => 'password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }

    public function test_legacy_parent_account_type_user_cannot_login_to_main_atlas(): void
    {
        $user = User::factory()->create(['account_type' => 'parent']);

        $response = $this->post('/login', [
            'email'    => $user->email,
            'password' => 'password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }

    public function test_employee_account_type_user_can_still_login_to_main_atlas(): void
    {
        $user = User::factory()->create(['account_type' => 'employee', 'status' => 'active']);

        $this->post('/login', [
            'email'    => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
    }
}
