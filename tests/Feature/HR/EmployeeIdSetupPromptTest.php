<?php

namespace Tests\Feature\HR;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeIdSetupPromptTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_flags_the_prompt_when_id_number_is_missing(): void
    {
        $user = User::factory()->create(['employee_idno_new' => null, 'password' => bcrypt('secret123')]);

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'secret123',
        ]);

        $response->assertRedirect();
        $this->assertTrue(session('prompt_employee_id_setup'));
    }

    public function test_login_does_not_flag_the_prompt_when_id_number_already_set(): void
    {
        $user = User::factory()->create([
            'employee_idno_new' => 'E13-2020-01-001',
            'password' => bcrypt('secret123'),
        ]);

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'secret123',
        ]);

        $this->assertFalse(session()->has('prompt_employee_id_setup') && session('prompt_employee_id_setup'));
    }

    public function test_inertia_shares_needs_employee_id_setup_prop(): void
    {
        $user = User::factory()->create(['employee_idno_new' => null]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertInertia(fn ($page) => $page->where('needsEmployeeIdSetup', true));
    }

    public function test_inertia_prop_is_false_once_id_number_is_set(): void
    {
        $user = User::factory()->create(['employee_idno_new' => 'E13-2020-01-001']);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertInertia(fn ($page) => $page->where('needsEmployeeIdSetup', false));
    }

    public function test_store_endpoint_generates_id_number_and_clears_prompt(): void
    {
        $user = User::factory()->create(['employee_idno_new' => null]);

        $response = $this->actingAs($user)
            ->withSession(['prompt_employee_id_setup' => true])
            ->post(route('employee-id.setup'), [
                'hired_year' => 2019,
                'hired_month' => 7,
            ]);

        $response->assertRedirect();
        $user->refresh();
        $this->assertNotNull($user->employee_idno_new);
        $this->assertMatchesRegularExpression('/^E13-2019-07-\d{3}$/', $user->employee_idno_new);
        $this->assertSame(2019, $user->hired_year);
        $this->assertSame(7, $user->hired_month);
        $this->assertFalse(session()->has('prompt_employee_id_setup'));
    }

    public function test_store_endpoint_rejects_invalid_month(): void
    {
        $user = User::factory()->create(['employee_idno_new' => null]);

        $response = $this->actingAs($user)->post(route('employee-id.setup'), [
            'hired_year' => 2020,
            'hired_month' => 13,
        ]);

        $response->assertSessionHasErrors('hired_month');
        $user->refresh();
        $this->assertNull($user->employee_idno_new);
    }

    public function test_store_endpoint_rejects_future_year(): void
    {
        $user = User::factory()->create(['employee_idno_new' => null]);
        $futureYear = (int) now()->addYear()->format('Y');

        $response = $this->actingAs($user)->post(route('employee-id.setup'), [
            'hired_year' => $futureYear,
            'hired_month' => 1,
        ]);

        $response->assertSessionHasErrors('hired_year');
    }

    public function test_store_endpoint_is_idempotent_if_id_already_generated(): void
    {
        $user = User::factory()->create(['employee_idno_new' => 'E13-2018-03-005']);

        $this->actingAs($user)->post(route('employee-id.setup'), [
            'hired_year' => 2019,
            'hired_month' => 1,
        ]);

        $user->refresh();
        // Original number preserved — not overwritten by a second submission.
        $this->assertSame('E13-2018-03-005', $user->employee_idno_new);
    }
}
