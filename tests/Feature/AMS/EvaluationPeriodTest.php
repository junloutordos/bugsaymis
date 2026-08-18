<?php

namespace Tests\Feature\AMS;

use App\Models\AMS\Activity;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EvaluationPeriodTest extends TestCase
{
    use RefreshDatabase;

    public function test_evaluation_open_defaults_true_on_new_activity(): void
    {
        $owner = $this->userWithPermission('activities.manage');
        $activity = $this->makeActivity($owner);

        $this->assertTrue($activity->fresh()->evaluation_open);
        $this->assertNull($activity->fresh()->evaluation_status_changed_at);
    }

    public function test_is_multi_day_and_attendance_day_list(): void
    {
        $owner = $this->userWithPermission('activities.manage');

        $singleDay = $this->makeActivity($owner, [
            'start_date' => '2026-08-10', 'end_date' => '2026-08-10',
        ]);
        $this->assertFalse($singleDay->isMultiDay());
        $this->assertSame([], $singleDay->attendanceDayList());

        $multiDay = $this->makeActivity($owner, [
            'start_date' => '2026-08-10', 'end_date' => '2026-08-12',
        ]);
        $this->assertTrue($multiDay->isMultiDay());
        $this->assertSame(
            ['2026-08-10', '2026-08-11', '2026-08-12'],
            $multiDay->attendanceDayList()
        );
    }

    private function makeActivity(User $owner, array $overrides = []): Activity
    {
        return Activity::create(array_merge([
            'user_id' => $owner->id,
            'title' => 'Period Test Activity',
            'activity_type' => Activity::TYPE_IN_HOUSE,
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-10',
        ], $overrides));
    }

    private function userWithPermission(string $permissionName): User
    {
        $permission = Permission::firstOrCreate(
            ['name' => $permissionName],
            ['module' => 'Activities', 'description' => $permissionName],
        );
        $role = Role::create(['name' => 'AMS Period Test '.uniqid()]);
        $role->permissions()->attach($permission);
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->roles()->attach($role);

        return $user;
    }
}
