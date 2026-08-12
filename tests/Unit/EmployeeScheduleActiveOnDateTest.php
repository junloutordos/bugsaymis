<?php

namespace Tests\Unit;

use App\Models\HR\EmployeeSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * EmployeeSchedule::scopeActiveOnDate() must only ever resolve an approved
 * schedule. A pending submission (awaiting HR review) or a rejected one
 * must never outrank the currently-approved schedule just because it has
 * a later effective_date — that would silently feed the wrong official
 * time into DTR computation and the printed checklist.
 */
class EmployeeScheduleActiveOnDateTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_resolves_the_approved_schedule_and_ignores_a_later_pending_one(): void
    {
        $user = User::factory()->create();

        $approved = EmployeeSchedule::create([
            'user_id'        => $user->id,
            'name'           => 'Approved Schedule',
            'work_days'      => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'],
            'time_in'        => '08:00:00',
            'time_out'       => '17:00:00',
            'is_default'     => true,
            'status'         => 'approved',
            'effective_date' => '2026-01-01',
        ]);

        // A pending submission with a LATER effective_date — awaiting HR review,
        // must not be picked up yet.
        EmployeeSchedule::create([
            'user_id'        => $user->id,
            'name'           => 'Pending Submission',
            'work_days'      => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'],
            'time_in'        => '09:00:00',
            'time_out'       => '18:00:00',
            'is_default'     => false,
            'status'         => 'pending',
            'effective_date' => '2026-06-01',
        ]);

        $resolved = EmployeeSchedule::where('user_id', $user->id)
            ->activeOnDate('2026-07-01')
            ->orderByDesc('effective_date')
            ->first();

        $this->assertNotNull($resolved);
        $this->assertSame($approved->id, $resolved->id);
        $this->assertSame('08:00:00', (string) $resolved->time_in);
    }

    public function test_it_ignores_a_rejected_schedule_even_with_a_later_effective_date(): void
    {
        $user = User::factory()->create();

        $approved = EmployeeSchedule::create([
            'user_id'        => $user->id,
            'name'           => 'Approved Schedule',
            'work_days'      => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'],
            'time_in'        => '08:00:00',
            'time_out'       => '17:00:00',
            'is_default'     => true,
            'status'         => 'approved',
            'effective_date' => '2026-01-01',
        ]);

        EmployeeSchedule::create([
            'user_id'          => $user->id,
            'name'             => 'Rejected Submission',
            'work_days'        => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'],
            'time_in'          => '10:00:00',
            'time_out'         => '19:00:00',
            'is_default'       => false,
            'status'           => 'rejected',
            'rejection_reason' => 'Conflicts with class schedule.',
            'effective_date'   => '2026-06-01',
        ]);

        $resolved = EmployeeSchedule::where('user_id', $user->id)
            ->activeOnDate('2026-07-01')
            ->orderByDesc('effective_date')
            ->first();

        $this->assertNotNull($resolved);
        $this->assertSame($approved->id, $resolved->id);
    }

    public function test_it_picks_up_a_newer_approved_schedule_once_hr_approves_it(): void
    {
        $user = User::factory()->create();

        EmployeeSchedule::create([
            'user_id'        => $user->id,
            'name'           => 'Old Approved Schedule',
            'work_days'      => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'],
            'time_in'        => '08:00:00',
            'time_out'       => '17:00:00',
            'is_default'     => true,
            'status'         => 'approved',
            'effective_date' => '2026-01-01',
            'end_date'       => '2026-05-31',
        ]);

        $newlyApproved = EmployeeSchedule::create([
            'user_id'        => $user->id,
            'name'           => 'Newly Approved Schedule',
            'work_days'      => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'],
            'time_in'        => '09:00:00',
            'time_out'       => '18:00:00',
            'is_default'     => true,
            'status'         => 'approved',
            'effective_date' => '2026-06-01',
        ]);

        $resolved = EmployeeSchedule::where('user_id', $user->id)
            ->activeOnDate('2026-07-01')
            ->orderByDesc('effective_date')
            ->first();

        $this->assertNotNull($resolved);
        $this->assertSame($newlyApproved->id, $resolved->id);
        $this->assertSame('09:00:00', (string) $resolved->time_in);
    }
}
