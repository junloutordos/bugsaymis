<?php

namespace Tests\Feature\Atlas\Dyna;

use App\Models\StudentAttendance\StudentAttendanceLog;
use App\Models\User;
use App\Services\Atlas\Dyna\Tools\GetGateAttendanceTrendTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetGateAttendanceTrendToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_scan_counts_by_day_within_the_given_range(): void
    {
        // raw_barcode is required (no default) — confirmed via
        // database/migrations/*_create_student_attendance_logs_table.php.
        StudentAttendanceLog::create(['student_id' => 1, 'raw_barcode' => 'BC1', 'type' => 'in', 'scan_time' => '2026-07-27 07:00:00']);
        StudentAttendanceLog::create(['student_id' => 2, 'raw_barcode' => 'BC2', 'type' => 'in', 'scan_time' => '2026-07-27 07:05:00']);
        StudentAttendanceLog::create(['student_id' => 1, 'raw_barcode' => 'BC1', 'type' => 'in', 'scan_time' => '2026-07-28 07:00:00']);

        $user = User::factory()->create();

        $result = (new GetGateAttendanceTrendTool())->execute($user, [
            'from_date' => '2026-07-27', 'to_date' => '2026-07-28',
        ]);

        $this->assertEquals(['2026-07-27' => 2, '2026-07-28' => 1], $result);
    }
}
