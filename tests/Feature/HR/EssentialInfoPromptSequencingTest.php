<?php

namespace Tests\Feature\HR;

use App\Models\HR\EmployeeProfile;
use App\Models\Pds;
use App\Models\PDSPersonalInfo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EssentialInfoPromptSequencingTest extends TestCase
{
    use RefreshDatabase;

    public function test_essential_info_prompt_does_not_fire_when_employee_id_still_missing(): void
    {
        $user = User::factory()->create([
            'employee_idno_new' => null,
            'password' => bcrypt('secret123'),
        ]);

        $this->post(route('login'), ['email' => $user->email, 'password' => 'secret123']);

        $this->assertTrue(session('prompt_employee_id_setup'));
        $this->assertFalse(session()->has('prompt_essential_info_setup') && session('prompt_essential_info_setup'));
    }

    public function test_essential_info_prompt_fires_once_employee_id_is_set_and_fields_are_missing(): void
    {
        $user = User::factory()->create([
            'employee_idno_new' => 'E13-2020-01-001',
            'password' => bcrypt('secret123'),
        ]);

        $this->post(route('login'), ['email' => $user->email, 'password' => 'secret123']);

        $this->assertFalse(session()->has('prompt_employee_id_setup') && session('prompt_employee_id_setup'));
        $this->assertTrue(session('prompt_essential_info_setup'));
    }

    public function test_essential_info_prompt_does_not_fire_when_all_fields_complete(): void
    {
        $user = User::factory()->create([
            'employee_idno_new' => 'E13-2020-01-001',
            'password' => bcrypt('secret123'),
        ]);
        $pds = Pds::create(['user_id' => $user->id]);
        PDSPersonalInfo::create([
            'pds_id' => $pds->id, 'surname' => 'Cruz', 'first_name' => 'Juan',
            'date_of_birth' => '1990-01-01',
            'residential_house' => '1', 'residential_barangay' => 'A',
            'residential_city' => 'B', 'residential_province' => 'C',
        ]);
        EmployeeProfile::create([
            'user_id' => $user->id,
            'emergency_contact_name' => 'X', 'emergency_contact_phone' => 'Y', 'emergency_contact_address' => 'Z',
        ]);

        $this->post(route('login'), ['email' => $user->email, 'password' => 'secret123']);

        $this->assertFalse(session()->has('prompt_essential_info_setup') && session('prompt_essential_info_setup'));
    }

    public function test_inertia_shares_needs_essential_info_setup_only_after_employee_id_resolved(): void
    {
        $userNoId = User::factory()->create(['employee_idno_new' => null]);
        $response1 = $this->actingAs($userNoId)->get(route('dashboard'));
        $response1->assertInertia(fn ($page) => $page->where('needsEssentialInfoSetup', false));

        $userWithId = User::factory()->create(['employee_idno_new' => 'E13-2020-01-001']);
        $response2 = $this->actingAs($userWithId)->get(route('dashboard'));
        $response2->assertInertia(fn ($page) => $page->where('needsEssentialInfoSetup', true));
    }

    public function test_store_endpoint_only_validates_missing_fields(): void
    {
        $user = User::factory()->create(['employee_idno_new' => 'E13-2020-01-001']);
        $pds = Pds::create(['user_id' => $user->id]);
        PDSPersonalInfo::create([
            'pds_id' => $pds->id, 'surname' => 'Cruz', 'first_name' => 'Juan',
            'date_of_birth' => '1990-01-01',
        ]);

        // DOB already present — should not be required; address + emergency contact are.
        $response = $this->actingAs($user)->post(route('essential-info.setup'), [
            'residential_house' => '123',
            'residential_barangay' => 'Ampayon',
            'residential_city' => 'Butuan City',
            'residential_province' => 'Agusan del Norte',
            'emergency_contact_name' => 'Maria Cruz',
            'emergency_contact_phone' => '09171234567',
            'emergency_contact_address' => 'Brgy. Ampayon',
        ]);

        $response->assertRedirect();
        $user->refresh();
        $this->assertSame('Ampayon', $user->pds->personalInfo->residential_barangay);
        $this->assertSame('Maria Cruz', $user->employeeProfile->emergency_contact_name);
    }

    public function test_store_endpoint_rejects_missing_required_address_fields(): void
    {
        $user = User::factory()->create(['employee_idno_new' => 'E13-2020-01-001']);

        $response = $this->actingAs($user)->post(route('essential-info.setup'), [
            'date_of_birth' => '1990-01-01',
            // residential_house/barangay/city/province intentionally omitted
            'emergency_contact_name' => 'Maria Cruz',
            'emergency_contact_phone' => '09171234567',
            'emergency_contact_address' => 'Brgy. Ampayon',
        ]);

        $response->assertSessionHasErrors(['residential_house', 'residential_barangay', 'residential_city', 'residential_province']);
    }
}
