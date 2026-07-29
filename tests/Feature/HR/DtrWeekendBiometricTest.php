<?php

namespace Tests\Feature\HR;

use App\Models\HR\BiometricLog;
use App\Models\HR\DtrRecord;
use App\Models\HR\EmployeeSchedule;
use App\Models\User;
use App\Services\HR\DTRService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Weekend (Saturday/Sunday) biometric punches must now be reflected on the
 * DTR instead of being silently discarded, for all employee categories.
 * Regular weekdays with no punches (true rest days) must keep being skipped.
 */
class DtrWeekendBiometricTest extends TestCase
{
    use RefreshDatabase;

    public function test_saturday_biometric_punches_generate_a_dtr_record(): void
    {
        $user = User::factory()->create(['emp_category' => 'COS Non Teaching']);

        EmployeeSchedule::create([
            'user_id'        => $user->id,
            'name'           => 'Default Mon-Fri',
            'work_days'      => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'],
            'time_in'        => '08:00:00',
            'time_out'       => '17:00:00',
            'is_default'     => true,
            'effective_date' => '2026-01-01',
        ]);

        // Saturday, 2026-07-25
        $saturday = '2026-07-25';
        foreach (['08:05:00', '12:00:00', '13:00:00', '16:30:00'] as $time) {
            BiometricLog::create([
                'device_employee_id' => (string) $user->id,
                'user_id'      => $user->id,
                'log_datetime' => "$saturday $time",
                'log_type'     => 'auto',
                'source'       => 'biometric',
                'is_resolved'  => true,
                'is_duplicate' => false,
            ]);
        }

        app(DTRService::class)->generate($user->id, $saturday, $saturday);

        $record = DtrRecord::where('user_id', $user->id)->where('work_date', $saturday)->first();

        $this->assertNotNull($record, 'Expected a DTR record to be generated for the worked Saturday.');
        $this->assertSame('regular', $record->day_type);
        $this->assertSame('present', $record->attendance_status);
        $this->assertSame('08:05:00', $record->time_in_am);
        $this->assertSame('16:30:00', $record->time_out_pm);
        $this->assertEquals(0, (float) $record->late_minutes);
        $this->assertEquals(0, (float) $record->undertime_minutes);
        $this->assertEquals(0, (float) $record->overtime_minutes);
        $this->assertGreaterThan(0, (float) $record->hours_worked);
    }

    public function test_sunday_with_no_punches_is_still_skipped(): void
    {
        $user = User::factory()->create(['emp_category' => 'Plantilla Non-Teaching']);

        EmployeeSchedule::create([
            'user_id'        => $user->id,
            'name'           => 'Default Mon-Fri',
            'work_days'      => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'],
            'time_in'        => '08:00:00',
            'time_out'       => '17:00:00',
            'is_default'     => true,
            'effective_date' => '2026-01-01',
        ]);

        $sunday = '2026-07-26';

        app(DTRService::class)->generate($user->id, $sunday, $sunday);

        $record = DtrRecord::where('user_id', $user->id)->where('work_date', $sunday)->first();

        $this->assertNull($record, 'An un-punched rest day must not generate a DTR record.');
    }

    public function test_plantilla_weekend_punches_are_also_reflected(): void
    {
        $user = User::factory()->create(['emp_category' => 'Plantilla Non-Teaching']);

        EmployeeSchedule::create([
            'user_id'        => $user->id,
            'name'           => 'Default Mon-Fri',
            'work_days'      => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'],
            'time_in'        => '08:00:00',
            'time_out'       => '17:00:00',
            'is_default'     => true,
            'effective_date' => '2026-01-01',
        ]);

        $sunday = '2026-07-26';
        foreach (['09:00:00', '15:00:00'] as $time) {
            BiometricLog::create([
                'device_employee_id' => (string) $user->id,
                'user_id'      => $user->id,
                'log_datetime' => "$sunday $time",
                'log_type'     => 'auto',
                'source'       => 'biometric',
                'is_resolved'  => true,
                'is_duplicate' => false,
            ]);
        }

        app(DTRService::class)->generate($user->id, $sunday, $sunday);

        $record = DtrRecord::where('user_id', $user->id)->where('work_date', $sunday)->first();

        $this->assertNotNull($record, 'Plantilla weekend punches must also be reflected, not just COS.');
        $this->assertSame('regular', $record->day_type);
    }
}
