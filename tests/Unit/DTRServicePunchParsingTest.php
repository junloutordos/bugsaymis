<?php

namespace Tests\Unit;

use App\Models\HR\BiometricLog;
use App\Models\HR\EmployeeSchedule;
use App\Models\HR\OnlineTimePunch;
use App\Services\HR\DTRService;
use Illuminate\Support\Collection;
use ReflectionClass;
use Tests\TestCase;

class DTRServicePunchParsingTest extends TestCase
{
    public function test_final_lunch_window_punch_is_pm_in_not_pm_out(): void
    {
        $result = $this->parsePunches([
            '2026-06-22 07:53:32',
            '2026-06-22 07:53:34',
            '2026-06-22 12:28:57',
            '2026-06-22 12:28:58',
            '2026-06-22 12:30:07',
        ]);

        $this->assertSame([
            '07:53:32',
            '12:28:57',
            '12:30:07',
            null,
        ], $result);
    }

    public function test_real_afternoon_departure_stays_pm_out(): void
    {
        $result = $this->parsePunches([
            '2026-06-23 07:53:32',
            '2026-06-23 12:28:57',
            '2026-06-23 12:30:07',
            '2026-06-23 17:04:31',
        ]);

        $this->assertSame([
            '07:53:32',
            '12:28:57',
            '12:30:07',
            '17:04:31',
        ], $result);
    }

    public function test_online_pm_out_merges_with_earlier_physical_punches(): void
    {
        $result = $this->mergePunches(
            ['2026-06-23 07:53:32', '2026-06-23 12:05:00'],
            ['time_out_pm' => '2026-06-23 17:04:31']
        );

        $this->assertSame([
            '07:53:32',
            '12:05:00',
            null,
            '17:04:31',
        ], $result);
    }

    public function test_online_slot_does_not_populate_an_unselected_slot(): void
    {
        $result = $this->mergePunches(
            ['2026-06-23 07:53:32'],
            ['time_out_am' => '2026-06-23 12:05:00']
        );

        $this->assertSame([
            '07:53:32',
            '12:05:00',
            null,
            null,
        ], $result);
    }

    public function test_online_only_punches_keep_their_selected_slots(): void
    {
        $result = $this->mergePunches(
            [],
            [
                'time_in_pm'  => '2026-06-23 13:02:00',
                'time_out_pm' => '2026-06-23 17:04:31',
            ]
        );

        $this->assertSame([
            null,
            null,
            '13:02:00',
            '17:04:31',
        ], $result);
    }

    private function parsePunches(array $datetimes): array
    {
        $logs = new Collection(array_map(function (string $datetime) {
            return new BiometricLog([
                'log_datetime' => $datetime,
            ]);
        }, $datetimes));

        $schedule = new EmployeeSchedule([
            'daily_schedules' => [
                'Mon' => ['time_in' => '08:00', 'time_out' => '17:00'],
                'Tue' => ['time_in' => '08:00', 'time_out' => '17:00'],
                'Wed' => ['time_in' => '08:00', 'time_out' => '17:00'],
                'Thu' => ['time_in' => '08:00', 'time_out' => '17:00'],
                'Fri' => ['time_in' => '08:00', 'time_out' => '17:00'],
            ],
        ]);

        $method = (new ReflectionClass(DTRService::class))->getMethod('parsePunches');
        $method->setAccessible(true);

        return $method->invoke(new DTRService(), $logs, substr($datetimes[0], 0, 10), $schedule);
    }

    private function mergePunches(array $biometricDatetimes, array $onlineByType): array
    {
        $biometricLogs = new Collection(array_map(function (string $datetime) {
            return new BiometricLog(['log_datetime' => $datetime]);
        }, $biometricDatetimes));

        $onlinePunches = new Collection();
        foreach ($onlineByType as $type => $datetime) {
            $onlinePunches->push(new OnlineTimePunch([
                'punch_type' => $type,
                'punched_at' => $datetime,
            ]));
        }

        $schedule = new EmployeeSchedule([
            'daily_schedules' => [
                'Mon' => ['time_in' => '08:00', 'time_out' => '17:00'],
                'Tue' => ['time_in' => '08:00', 'time_out' => '17:00'],
                'Wed' => ['time_in' => '08:00', 'time_out' => '17:00'],
                'Thu' => ['time_in' => '08:00', 'time_out' => '17:00'],
                'Fri' => ['time_in' => '08:00', 'time_out' => '17:00'],
            ],
        ]);

        $method = (new ReflectionClass(DTRService::class))->getMethod('mergePunches');
        $method->setAccessible(true);

        return $method->invoke(
            new DTRService(),
            $biometricLogs,
            $onlinePunches,
            '2026-06-23',
            $schedule
        );
    }
}
