<?php

namespace Tests\Feature;

use App\Models\Pds;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PdsSaveValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_partial_nested_row_returns_a_field_error_without_deleting_existing_data(): void
    {
        $user = User::factory()->create();
        $pds = Pds::create(['user_id' => $user->id]);
        $pds->education()->create([
            'level' => 'College',
            'school_name' => 'Existing University',
        ]);

        $payload = $this->validPayload();
        $payload['education'] = [['level' => 'College', 'degree' => 'BS Biology']];

        $this->actingAs($user)
            ->from(route('pds.edit', $pds))
            ->put(route('pds.update', $pds), $payload)
            ->assertRedirect(route('pds.edit', $pds))
            ->assertSessionHasErrors('education.0.school_name');

        $this->assertDatabaseHas('pds_education', [
            'pds_id' => $pds->id,
            'school_name' => 'Existing University',
        ]);
    }

    public function test_invalid_training_hours_returns_a_specific_field_error(): void
    {
        $user = User::factory()->create();
        $pds = Pds::create(['user_id' => $user->id]);
        $payload = $this->validPayload();
        $payload['trainings'] = [[
            'training_title' => 'First Aid',
            'hours' => 'eight',
        ]];

        $this->actingAs($user)
            ->put(route('pds.update', $pds), $payload)
            ->assertSessionHasErrors('trainings.0.hours');
    }

    public function test_formatted_mobile_and_legacy_citizenship_values_are_normalized_on_save(): void
    {
        $user = User::factory()->create();
        $pds = Pds::create(['user_id' => $user->id]);
        $payload = $this->validPayload();
        $payload['personal_info']['mobile_no'] = '+63 917 123 4567';
        $payload['personal_info']['citizenship_filipino'] = 'Yes';
        $payload['personal_info']['citizenship_dual'] = 'No';

        $this->actingAs($user)
            ->put(route('pds.update', $pds), $payload)
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('pds_personal_info', [
            'pds_id' => $pds->id,
            'mobile_no' => '639171234567',
            'citizenship_filipino' => 'Yes',
            'citizenship_dual' => 'No',
        ]);
    }

    public function test_non_owner_cannot_update_another_users_pds(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $pds = Pds::create(['user_id' => $owner->id]);

        $this->actingAs($otherUser)
            ->put(route('pds.update', $pds), $this->validPayload())
            ->assertForbidden();
    }

    public function test_pds_create_route_is_not_shadowed_by_a_duplicate_post_route(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => $route->uri() === 'pds' && in_array('POST', $route->methods(), true));

        $this->assertCount(1, $routes);
        $this->assertSame('pds.store', $routes->first()->getName());
        $this->assertStringEndsWith('@store', $routes->first()->getActionName());
        $this->assertFalse(Route::has('pds.newpds'));
    }

    private function validPayload(): array
    {
        return [
            'personal_info' => [
                'surname' => 'Dela Cruz',
                'first_name' => 'Juan',
                'date_of_birth' => '1990-01-15',
                'mobile_no' => '09171234567',
                'citizenship_type' => 'Filipino',
                'citizenship_filipino' => true,
                'citizenship_dual' => false,
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
    }
}
