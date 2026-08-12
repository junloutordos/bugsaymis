<?php

namespace Tests\Feature\HR;

use App\Models\HR\EmployeeSchedule;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The printed DTR checklist's "Official Time" block must reflect the
 * employee's latest APPROVED work schedule — not a pending submission
 * awaiting HR review, even if that submission has a later effective_date.
 */
class DtrChecklistOfficialTimeTest extends TestCase
{
    use RefreshDatabase;

    private function viewer(): User
    {
        $permission = Permission::firstOrCreate(
            ['name' => 'hr.dtr.view'],
            ['module' => 'DTR', 'description' => 'View DTR'],
        );
        $role = Role::create(['name' => 'HRDtrViewer_' . uniqid()]);
        $role->permissions()->attach($permission->id);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        return $user->fresh();
    }

    private function withOwnPermission(User $user): User
    {
        $permission = Permission::firstOrCreate(
            ['name' => 'dtr.view_own'],
            ['module' => 'DTR', 'description' => 'View own DTR'],
        );
        $role = Role::create(['name' => 'DtrOwnViewer_' . uniqid()]);
        $role->permissions()->attach($permission->id);
        $user->roles()->attach($role->id);

        return $user->fresh();
    }

    public function test_my_checklist_uses_the_approved_schedule_not_a_later_pending_one(): void
    {
        $employee = User::factory()->create(['emp_category' => 'Plantilla Non-Teaching']);
        $this->withOwnPermission($employee);

        EmployeeSchedule::create([
            'user_id'        => $employee->id,
            'name'           => 'Approved Schedule',
            'work_days'      => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'],
            'time_in'        => '08:00:00',
            'time_out'       => '17:00:00',
            'is_default'     => true,
            'status'         => 'approved',
            'effective_date' => '2026-01-01',
        ]);

        // Pending submission with a later effective_date — must be ignored
        // until HR approves it.
        EmployeeSchedule::create([
            'user_id'        => $employee->id,
            'name'           => 'Pending Submission',
            'work_days'      => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'],
            'time_in'        => '10:00:00',
            'time_out'       => '19:00:00',
            'is_default'     => false,
            'status'         => 'pending',
            'effective_date' => '2026-07-01',
        ]);

        $this->actingAs($employee)
            ->get(route('hr.my-dtr.checklist', ['month' => '2026-07']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('HR/DTR/PrintChecklist')
                ->where('officialTimes.Mon.time_in', '08:00:00')
                ->where('officialTimes.Mon.time_out', '17:00:00'));
    }

    public function test_admin_print_checklist_uses_the_approved_schedule_not_a_later_pending_one(): void
    {
        $employee = User::factory()->create(['emp_category' => 'Plantilla Non-Teaching']);

        EmployeeSchedule::create([
            'user_id'        => $employee->id,
            'name'           => 'Approved Schedule',
            'work_days'      => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'],
            'time_in'        => '08:00:00',
            'time_out'       => '17:00:00',
            'is_default'     => true,
            'status'         => 'approved',
            'effective_date' => '2026-01-01',
        ]);

        EmployeeSchedule::create([
            'user_id'        => $employee->id,
            'name'           => 'Pending Submission',
            'work_days'      => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'],
            'time_in'        => '10:00:00',
            'time_out'       => '19:00:00',
            'is_default'     => false,
            'status'         => 'pending',
            'effective_date' => '2026-07-01',
        ]);

        $this->actingAs($this->viewer())
            ->get(route('hr.dtr.checklist', ['user' => $employee->id, 'month' => '2026-07']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('HR/DTR/PrintChecklist')
                ->where('officialTimes.Mon.time_in', '08:00:00')
                ->where('officialTimes.Mon.time_out', '17:00:00'));
    }

    public function test_checklist_falls_back_to_the_default_schedule_when_none_is_active_yet(): void
    {
        $employee = User::factory()->create(['emp_category' => 'Plantilla Non-Teaching']);

        // Default/approved schedule effective in the future relative to the
        // printed month — activeOnDate() won't match, so the is_default
        // fallback should still resolve it.
        EmployeeSchedule::create([
            'user_id'        => $employee->id,
            'name'           => 'Default Schedule',
            'work_days'      => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'],
            'time_in'        => '08:00:00',
            'time_out'       => '17:00:00',
            'is_default'     => true,
            'status'         => 'approved',
            'effective_date' => '2099-01-01',
        ]);

        $this->actingAs($this->viewer())
            ->get(route('hr.dtr.checklist', ['user' => $employee->id, 'month' => '2026-07']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('officialTimes.Mon.time_in', '08:00:00'));
    }
}
