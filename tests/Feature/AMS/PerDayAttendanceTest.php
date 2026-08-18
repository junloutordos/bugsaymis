<?php

namespace Tests\Feature\AMS;

use App\Models\AMS\Activity;
use App\Models\AMS\ActivityAttendanceDay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PerDayAttendanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_attendance_day_belongs_to_activity_and_enforces_unique_combo(): void
    {
        $activity = Activity::create([
            'user_id' => \App\Models\User::factory()->create()->id,
            'title' => 'Multi-day Activity',
            'activity_type' => Activity::TYPE_IN_HOUSE,
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-12',
        ]);

        $day = ActivityAttendanceDay::create([
            'activity_id' => $activity->id,
            'participant_type' => 'employee',
            'participant_id' => 999,
            'date' => '2026-08-10',
            'attended' => 'yes',
            'hours_attended' => 8,
        ]);

        $this->assertSame($activity->id, $day->activity->id);
        $this->assertCount(1, $activity->attendanceDays);

        $this->expectException(\Illuminate\Database\QueryException::class);
        ActivityAttendanceDay::create([
            'activity_id' => $activity->id,
            'participant_type' => 'employee',
            'participant_id' => 999,
            'date' => '2026-08-10',
            'attended' => 'no',
            'hours_attended' => 0,
        ]);
    }
}
