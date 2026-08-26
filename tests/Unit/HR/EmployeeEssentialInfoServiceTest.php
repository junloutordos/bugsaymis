<?php

namespace Tests\Unit\HR;

use App\Models\HR\EmployeeProfile;
use App\Models\Pds;
use App\Models\PDSPersonalInfo;
use App\Models\User;
use App\Services\HR\EmployeeEssentialInfoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeEssentialInfoServiceTest extends TestCase
{
    use RefreshDatabase;

    private EmployeeEssentialInfoService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EmployeeEssentialInfoService::class);
    }

    public function test_all_fields_missing_when_no_pds_or_profile_exists(): void
    {
        $user = User::factory()->create();

        $missing = $this->service->missingFields($user);

        $this->assertContains('date_of_birth', $missing);
        $this->assertContains('residential_address', $missing);
        $this->assertContains('emergency_contact', $missing);
    }

    public function test_date_of_birth_not_missing_when_present_in_pds(): void
    {
        $user = User::factory()->create();
        $pds = Pds::create(['user_id' => $user->id]);
        PDSPersonalInfo::create([
            'pds_id' => $pds->id, 'surname' => 'Dela Cruz', 'first_name' => 'Juan',
            'date_of_birth' => '1990-05-15',
        ]);

        $missing = $this->service->missingFields($user->refresh());

        $this->assertNotContains('date_of_birth', $missing);
    }

    public function test_residential_address_missing_when_required_subfields_incomplete(): void
    {
        $user = User::factory()->create();
        $pds = Pds::create(['user_id' => $user->id]);
        PDSPersonalInfo::create([
            'pds_id' => $pds->id, 'surname' => 'Dela Cruz', 'first_name' => 'Juan',
            'residential_house' => '123 Main St',
            // barangay/city/province intentionally left blank
        ]);

        $missing = $this->service->missingFields($user->refresh());

        $this->assertContains('residential_address', $missing);
    }

    public function test_residential_address_not_missing_when_all_required_subfields_present(): void
    {
        $user = User::factory()->create();
        $pds = Pds::create(['user_id' => $user->id]);
        PDSPersonalInfo::create([
            'pds_id' => $pds->id, 'surname' => 'Dela Cruz', 'first_name' => 'Juan',
            'residential_house' => '123 Main St',
            'residential_barangay' => 'Ampayon',
            'residential_city' => 'Butuan City',
            'residential_province' => 'Agusan del Norte',
        ]);

        $missing = $this->service->missingFields($user->refresh());

        $this->assertNotContains('residential_address', $missing);
    }

    public function test_emergency_contact_missing_when_any_of_three_fields_blank(): void
    {
        $user = User::factory()->create();
        EmployeeProfile::create([
            'user_id' => $user->id,
            'emergency_contact_name' => 'Maria Dela Cruz',
            'emergency_contact_phone' => '09171234567',
            // address intentionally missing
        ]);

        $missing = $this->service->missingFields($user->refresh());

        $this->assertContains('emergency_contact', $missing);
    }

    public function test_emergency_contact_not_missing_when_all_three_present(): void
    {
        $user = User::factory()->create();
        EmployeeProfile::create([
            'user_id' => $user->id,
            'emergency_contact_name' => 'Maria Dela Cruz',
            'emergency_contact_phone' => '09171234567',
            'emergency_contact_address' => 'Brgy. Ampayon, Butuan City',
        ]);

        $missing = $this->service->missingFields($user->refresh());

        $this->assertNotContains('emergency_contact', $missing);
    }

    public function test_save_creates_pds_from_scratch_when_none_exists(): void
    {
        $user = User::factory()->create(['name' => 'Dela Cruz, Juan A.']);

        $this->service->save($user, [
            'date_of_birth' => '1990-05-15',
            'residential_house' => '123 Main St',
            'residential_barangay' => 'Ampayon',
            'residential_city' => 'Butuan City',
            'residential_province' => 'Agusan del Norte',
        ]);

        $user->refresh();
        $this->assertNotNull($user->pds);
        $this->assertSame('1990-05-15', $user->pds->personalInfo->date_of_birth);
        $this->assertSame('Dela Cruz', $user->pds->personalInfo->surname);
        $this->assertSame('Juan A.', $user->pds->personalInfo->first_name);
        $this->assertSame('Ampayon', $user->pds->personalInfo->residential_barangay);
    }

    public function test_save_does_not_overwrite_existing_pds_data(): void
    {
        $user = User::factory()->create();
        $pds = Pds::create(['user_id' => $user->id]);
        PDSPersonalInfo::create([
            'pds_id' => $pds->id, 'surname' => 'Dela Cruz', 'first_name' => 'Juan',
            'date_of_birth' => '1990-05-15',
        ]);

        // Only emergency contact submitted — DOB/address fields not sent (blank).
        $this->service->save($user->refresh(), [
            'emergency_contact_name' => 'Maria Dela Cruz',
            'emergency_contact_phone' => '09171234567',
            'emergency_contact_address' => 'Brgy. Ampayon, Butuan City',
        ]);

        $user->refresh();
        $this->assertSame('1990-05-15', $user->pds->personalInfo->date_of_birth);
        $this->assertSame('Maria Dela Cruz', $user->employeeProfile->emergency_contact_name);
    }

    public function test_save_writes_emergency_contact_to_employee_profile(): void
    {
        $user = User::factory()->create();

        $this->service->save($user, [
            'emergency_contact_name' => 'Pedro Santos',
            'emergency_contact_phone' => '09181234567',
            'emergency_contact_address' => 'Brgy. Libertad, Butuan City',
        ]);

        $user->refresh();
        $this->assertSame('Pedro Santos', $user->employeeProfile->emergency_contact_name);
        $this->assertSame('09181234567', $user->employeeProfile->emergency_contact_phone);
        $this->assertSame('Brgy. Libertad, Butuan City', $user->employeeProfile->emergency_contact_address);
    }
}
