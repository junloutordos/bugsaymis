<?php

namespace Tests\Feature\HR;

use App\Models\HR\EmployeeProfile;
use App\Models\Pds;
use App\Models\PDSPersonalInfo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeDigitalIdViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_digital_id_route_renders_the_flip_card_component(): void
    {
        $user = User::factory()->create(['employee_idno_new' => 'E13-2020-01-001']);

        $response = $this->actingAs($user)->get(route('profile.digital-id'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Profile/DigitalId')
            ->where('employee.employee_no', 'E13-2020-01-001')
            ->has('qr_svg')
            ->has('back_route')
            ->has('print_route')
        );
    }

    public function test_digital_id_view_includes_same_data_as_print_view(): void
    {
        $user = User::factory()->create(['employee_idno_new' => 'E13-2020-01-001']);
        $pds = Pds::create(['user_id' => $user->id]);
        PDSPersonalInfo::create([
            'pds_id' => $pds->id, 'surname' => 'Cruz', 'first_name' => 'Juan',
            'date_of_birth' => '1990-05-15',
            'residential_house' => '123 Main St', 'residential_barangay' => 'Ampayon',
            'residential_city' => 'Butuan City', 'residential_province' => 'Agusan del Norte',
        ]);
        EmployeeProfile::create([
            'user_id' => $user->id,
            'emergency_contact_name' => 'Maria Cruz',
            'emergency_contact_phone' => '09171234567',
            'emergency_contact_address' => 'Brgy. Libertad, Butuan City',
        ]);

        $response = $this->actingAs($user)->get(route('profile.digital-id'));

        $response->assertInertia(fn ($page) => $page
            ->where('employee.date_of_birth', 'May 15, 1990')
            ->where('employee.residential_address', '123 Main St, Brgy. Ampayon, Butuan City, Agusan del Norte')
            ->where('emergency.contact_name', 'Maria Cruz')
        );
    }

    public function test_digital_id_route_requires_authentication(): void
    {
        $this->get(route('profile.digital-id'))->assertRedirect(route('login'));
    }

    public function test_print_route_is_unaffected_by_the_digital_id_split(): void
    {
        $user = User::factory()->create(['employee_idno_new' => 'E13-2020-01-001']);

        $response = $this->actingAs($user)->get(route('profile.id-card'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Profile/IdCard')
            ->where('employee.employee_no', 'E13-2020-01-001')
        );
    }

    public function test_print_route_and_digital_id_route_are_distinct(): void
    {
        $this->assertNotEquals(route('profile.id-card'), route('profile.digital-id'));
    }
}
