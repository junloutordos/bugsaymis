<?php

namespace Tests\Feature\AMS;

use App\Models\AMS\Activity;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_update_in_house_activity_with_stored_time_format_and_no_speakers(): void
    {
        $owner = $this->userWithPermission('activities.manage');
        $activity = $this->makeActivity($owner, [
            'banner' => 's3.existing-banner',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
        ]);

        $this->actingAs($owner)
            ->post(route('ams.activities.update', $activity), $this->payload([
                'title' => 'Updated In-house Activity',
                'start_time' => '09:15:00',
                'end_time' => '16:30:00',
                'speakers' => [],
                'meal_plans' => [[
                    'date' => '2026-08-10',
                    'am_snacks' => true,
                    'lunch' => true,
                    'pm_snacks' => false,
                    'dinner' => false,
                ]],
            ]))
            ->assertRedirect(route('ams.activities.show', $activity))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('ams_activities', [
            'id' => $activity->id,
            'title' => 'Updated In-house Activity',
            'start_time' => '09:15:00',
            'end_time' => '16:30:00',
            'banner' => 's3.existing-banner',
        ]);
        $this->assertDatabaseHas('ams_activity_meal_plans', [
            'activity_id' => $activity->id,
            'date' => '2026-08-10',
            'am_snacks' => 1,
            'lunch' => 1,
        ]);
        $this->assertDatabaseCount('ams_activity_speakers', 0);
    }

    public function test_owner_can_update_training_activity_without_replacing_speaker_record(): void
    {
        $owner = $this->userWithPermission('activities.manage');
        $activity = $this->makeActivity($owner, [
            'activity_type' => Activity::TYPE_TRAINING_WORKSHOP_SEMINAR,
        ]);
        $speaker = $activity->speakers()->create([
            'name' => 'Original Speaker',
            'designation' => 'Original Office',
            'topic' => 'Original Topic',
            'sort_order' => 0,
        ]);

        $this->actingAs($owner)
            ->post(route('ams.activities.update', $activity), $this->payload([
                'activity_type' => Activity::TYPE_TRAINING_WORKSHOP_SEMINAR,
                'speakers' => [[
                    'id' => $speaker->id,
                    'name' => 'Updated Speaker',
                    'designation' => 'Updated Office',
                    'topic' => 'Updated Topic',
                ]],
            ]))
            ->assertRedirect(route('ams.activities.show', $activity))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('ams_activity_speakers', [
            'id' => $speaker->id,
            'activity_id' => $activity->id,
            'name' => 'Updated Speaker',
            'designation' => 'Updated Office',
            'topic' => 'Updated Topic',
        ]);
        $this->assertDatabaseCount('ams_activity_speakers', 1);
    }

    public function test_training_activity_still_requires_at_least_one_speaker(): void
    {
        $owner = $this->userWithPermission('activities.manage');
        $activity = $this->makeActivity($owner, [
            'activity_type' => Activity::TYPE_TRAINING_WORKSHOP_SEMINAR,
        ]);

        $this->actingAs($owner)
            ->post(route('ams.activities.update', $activity), $this->payload([
                'title' => 'Should Not Save',
                'activity_type' => Activity::TYPE_TRAINING_WORKSHOP_SEMINAR,
                'speakers' => [],
            ]))
            ->assertSessionHasErrors('speakers');

        $this->assertSame('Original Activity', $activity->fresh()->title);
    }

    public function test_non_owner_cannot_update_activity(): void
    {
        $owner = $this->userWithPermission('activities.manage');
        $viewer = $this->userWithPermission('activities.view_all');
        $activity = $this->makeActivity($owner);

        $this->actingAs($viewer)
            ->post(route('ams.activities.update', $activity), $this->payload([
                'title' => 'Unauthorized Change',
            ]))
            ->assertForbidden();

        $this->assertSame('Original Activity', $activity->fresh()->title);
    }

    private function makeActivity(User $owner, array $overrides = []): Activity
    {
        return Activity::create(array_merge([
            'user_id' => $owner->id,
            'title' => 'Original Activity',
            'activity_type' => Activity::TYPE_IN_HOUSE,
            'start_date' => '2026-08-10',
            'start_time' => '08:00:00',
            'end_date' => '2026-08-10',
            'end_time' => '17:00:00',
            'total_hours' => '8 hours',
            'venue' => 'Training Hall',
            'resource_person' => 'Facilitator',
            'what_to_bring' => 'Laptop',
        ], $overrides));
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Original Activity',
            'activity_type' => Activity::TYPE_IN_HOUSE,
            'start_date' => '2026-08-10',
            'start_time' => '08:00:00',
            'end_date' => '2026-08-10',
            'end_time' => '17:00:00',
            'total_hours' => '8 hours',
            'venue' => 'Training Hall',
            'resource_person' => 'Facilitator',
            'what_to_bring' => 'Laptop',
            'meal_plans' => [],
            'banner' => null,
            'special_order' => null,
            'activity_report' => null,
            'official_documentation' => null,
        ], $overrides);
    }

    private function userWithPermission(string $permissionName): User
    {
        $permission = Permission::firstOrCreate(
            ['name' => $permissionName],
            ['module' => 'Activities', 'description' => $permissionName],
        );
        $role = Role::create(['name' => 'AMS Test '.uniqid()]);
        $role->permissions()->attach($permission);
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->roles()->attach($role);

        return $user;
    }
}
