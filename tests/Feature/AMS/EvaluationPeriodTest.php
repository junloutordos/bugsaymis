<?php

namespace Tests\Feature\AMS;

use App\Models\AMS\Activity;
use App\Models\AMS\ActivityCoProponent;
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

    public function test_owner_can_close_and_reopen_evaluation_period(): void
    {
        $owner = $this->userWithPermission('activities.manage');
        $activity = $this->makeActivity($owner);

        $this->actingAs($owner)
            ->post(route('ams.activities.evaluation-period.toggle', $activity), ['open' => false])
            ->assertRedirect()
            ->assertSessionHas('success');

        $activity->refresh();
        $this->assertFalse($activity->evaluation_open);
        $this->assertNotNull($activity->evaluation_status_changed_at);
        $this->assertSame($owner->id, $activity->evaluation_status_changed_by);

        $this->actingAs($owner)
            ->post(route('ams.activities.evaluation-period.toggle', $activity), ['open' => true])
            ->assertRedirect();

        $this->assertTrue($activity->fresh()->evaluation_open);
    }

    public function test_co_proponent_can_toggle_evaluation_period(): void
    {
        $owner = $this->userWithPermission('activities.manage');
        $activity = $this->makeActivity($owner);
        $coProponent = $this->userWithPermission('activities.view_all');
        ActivityCoProponent::create(['activity_id' => $activity->id, 'employee_id' => $coProponent->id]);

        $this->actingAs($coProponent)
            ->post(route('ams.activities.evaluation-period.toggle', $activity), ['open' => false])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertFalse($activity->fresh()->evaluation_open);
    }

    public function test_evaluation_committee_permission_holder_can_toggle_evaluation_period(): void
    {
        $owner = $this->userWithPermission('activities.manage');
        $activity = $this->makeActivity($owner);
        $committeeMember = $this->userWithPermission('activities.evaluation_committee');

        $this->actingAs($committeeMember)
            ->post(route('ams.activities.evaluation-period.toggle', $activity), ['open' => false])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertFalse($activity->fresh()->evaluation_open);
    }

    public function test_unrelated_user_cannot_toggle_evaluation_period(): void
    {
        $owner = $this->userWithPermission('activities.manage');
        $activity = $this->makeActivity($owner);
        $stranger = $this->userWithPermission('activities.view_all');

        $this->actingAs($stranger)
            ->post(route('ams.activities.evaluation-period.toggle', $activity), ['open' => false])
            ->assertForbidden();

        $this->assertTrue($activity->fresh()->evaluation_open);
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
