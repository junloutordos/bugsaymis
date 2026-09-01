<?php

namespace Tests\Feature\HR;

use App\Models\HR\DtrRecord;
use App\Models\HR\EmployeeSchedule;
use App\Models\User;
use App\Services\HR\DTRService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers three DTR computation bugs found while investigating admin time
 * edits not reflecting correct late/undertime/overtime:
 *
 * 1. computeOvertimeMinutes() returned a negative value under Carbon 3
 *    (diffInMinutes() defaults to signed, unlike Carbon 2's always-absolute
 *    default) — every nonzero overtime_minutes in the DB was negative.
 * 2. The AM-in grace period suppressed legitimate lateness — removed so any
 *    arrival after scheduled time-in counts as late from minute 1.
 * 3. generate() unconditionally overwrote time_in_am/out_am/in_pm/out_pm
 *    from raw punch data, silently discarding admin corrections made via
 *    the edit() endpoint on unlocked records.
 */
class DtrRecomputeAndOvertimeTest extends TestCase
{
    use RefreshDatabase;

    private function makeSchedule(User $user, int $grace = 15): EmployeeSchedule
    {
        return EmployeeSchedule::create([
            'user_id'               => $user->id,
            'name'                  => 'Default Mon-Fri',
            'work_days'             => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'],
            'time_in'               => '08:00:00',
            'time_out'              => '17:00:00',
            'lunch_start'           => '12:00:00',
            'lunch_end'             => '13:00:00',
            'grace_period_minutes'  => $grace,
            'is_default'            => true,
            'effective_date'        => '2026-01-01',
        ]);
    }

    public function test_recompute_produces_positive_overtime_when_time_out_is_after_schedule(): void
    {
        $user     = User::factory()->create();
        $schedule = $this->makeSchedule($user);

        $record = DtrRecord::create([
            'user_id'      => $user->id,
            'schedule_id'  => $schedule->id,
            'work_date'    => '2026-08-20',
            'time_in_am'   => '08:00:00',
            'time_out_am'  => '12:00:00',
            'time_in_pm'   => '13:00:00',
            'time_out_pm'  => '17:24:00', // 24 minutes past scheduled 17:00 time-out
        ]);

        app(DTRService::class)->recompute($record->fresh());

        $this->assertEquals(24, (float) $record->fresh()->overtime_minutes);
    }

    public function test_late_minutes_counts_from_minute_one_with_no_grace_period(): void
    {
        $user     = User::factory()->create();
        $schedule = $this->makeSchedule($user, grace: 15);

        $record = DtrRecord::create([
            'user_id'      => $user->id,
            'schedule_id'  => $schedule->id,
            'work_date'    => '2026-08-20',
            'time_in_am'   => '08:04:00', // 4 minutes late — inside old 15-min grace window
        ]);

        app(DTRService::class)->recompute($record->fresh());

        $this->assertEquals(4, (float) $record->fresh()->late_minutes);
    }

    public function test_generate_does_not_overwrite_a_manually_edited_time_field(): void
    {
        $user     = User::factory()->create();
        $schedule = $this->makeSchedule($user);

        // Simulate an admin edit: record exists with a manually-set AM time-in
        // and no matching raw punch source for that slot.
        $record = DtrRecord::create([
            'user_id'              => $user->id,
            'schedule_id'          => $schedule->id,
            'work_date'            => '2026-08-20',
            'time_in_am'           => '08:04:00',
            'time_manually_edited' => true,
            'source'               => 'online_punch',
        ]);

        app(DTRService::class)->generate($user->id, '2026-08-20', '2026-08-20');

        $this->assertSame('08:04:00', $record->fresh()->time_in_am, 'generate() must not overwrite a manually-edited time field.');
    }
}
