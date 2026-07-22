<?php

namespace Tests\Feature;

use App\Models\Pds;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PdsAddressPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_residential_and_permanent_regions_persist_and_reload_with_the_pds(): void
    {
        $user = User::factory()->create();
        $pds = Pds::create(['user_id' => $user->id]);

        $payload = [
            'personal_info' => [
                'surname' => 'Dela Cruz',
                'first_name' => 'Juan',
                'date_of_birth' => '1990-01-15',
                'mobile_no' => '09171234567',
                'citizenship_type' => 'Filipino',
                'citizenship_filipino' => true,
                'residential_house' => '12',
                'residential_street' => 'Narrah Street',
                'residential_subdivision' => 'Sample Village',
                'residential_barangay' => 'Ampayon',
                'residential_city' => 'City of Butuan',
                'residential_province' => '',
                'residential_region' => 'Region XIII (Caraga)',
                'residential_zip_code' => '8600',
                'permanent_house' => '34',
                'permanent_street' => 'Acacia Street',
                'permanent_subdivision' => '',
                'permanent_barangay' => 'Fort Bonifacio',
                'permanent_city' => 'City of Taguig',
                'permanent_province' => '',
                'permanent_region' => 'National Capital Region (NCR)',
                'permanent_zip_code' => '1630',
            ],
            'family_background' => [],
            'children' => [],
            'education' => [],
            'eligibility' => [],
            'work_experience' => [],
            'voluntary_work' => [],
            'trainings' => [],
            'skills_hobbies' => [],
            'non_academic_recognition' => [],
            'membership_organizations' => [],
            'questions' => [],
            'references' => [],
            'other_info' => [],
            'work_experience_sheets' => [],
        ];

        $this->actingAs($user)
            ->from(route('pds.edit', $pds))
            ->put(route('pds.update', $pds), $payload)
            ->assertRedirect(route('pds.edit', $pds))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('pds_personal_info', [
            'pds_id' => $pds->id,
            'residential_region' => 'Region XIII (Caraga)',
            'residential_city' => 'City of Butuan',
            'residential_barangay' => 'Ampayon',
            'permanent_region' => 'National Capital Region (NCR)',
            'permanent_city' => 'City of Taguig',
            'permanent_barangay' => 'Fort Bonifacio',
        ]);

        $this->actingAs($user)
            ->get(route('pds.edit', $pds))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('pds.personal_info.residential_region', 'Region XIII (Caraga)')
                ->where('pds.personal_info.residential_city', 'City of Butuan')
                ->where('pds.personal_info.residential_barangay', 'Ampayon')
                ->where('pds.personal_info.permanent_region', 'National Capital Region (NCR)')
                ->where('pds.personal_info.permanent_city', 'City of Taguig')
                ->where('pds.personal_info.permanent_barangay', 'Fort Bonifacio'));
    }
}
