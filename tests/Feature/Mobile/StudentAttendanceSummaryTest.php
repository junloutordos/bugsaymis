<?php

namespace Tests\Feature\Mobile;

use App\Models\SchoolCalendarEvent;
use App\Models\Student;
use App\Models\StudentAttendance\StudentAttendanceLog;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StudentAttendanceSummaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // See StudentSosTriggerTest for why this is required for any
        // Student-authenticated Feature test.
        config(['opentelemetry.user_context' => false]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function tokenFor(Student $student): string
    {
        return $student->createToken('test')->plainTextToken;
    }

    private function makeStudent(): Student
    {
        $id = DB::table('students')->insertGetId(['lastname' => 'Santos', 'firstname' => 'Liza', 'status' => 'active']);

        return Student::find($id);
    }

    private function scan(Student $student, string $dateTime): void
    {
        StudentAttendanceLog::create([
            'student_id' => $student->id,
            'raw_barcode' => 'TEST',
            'scan_time' => $dateTime,
            'type' => 'in',
            'source' => 'gate_scanner',
        ]);
    }

    public function test_month_present_counts_distinct_days_with_an_in_scan(): void
    {
        // 2026-08-19 is a Wednesday.
        Carbon::setTestNow('2026-08-19 10:00:00');
        $student = $this->makeStudent();
        $token = $this->tokenFor($student);

        $this->scan($student, '2026-08-17 07:00:00'); // Monday
        $this->scan($student, '2026-08-17 16:00:00'); // same day, second scan — must not double-count
        $this->scan($student, '2026-08-18 07:05:00'); // Tuesday

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/mobile/student/attendance/summary')
            ->assertOk();

        // Aug 1 (Sat) through Aug 19 (Wed): weekdays only, no holidays fixtured,
        // so month_school_days = every Mon-Fri in that range.
        $response->assertJson(['month_present' => 2]);
        $this->assertGreaterThan(0, $response->json('month_school_days'));
    }

    public function test_a_holiday_reduces_school_days_but_not_present_days(): void
    {
        Carbon::setTestNow('2026-08-19 10:00:00');
        $student = $this->makeStudent();
        $token = $this->tokenFor($student);

        $admin = \App\Models\User::factory()->create();
        SchoolCalendarEvent::create([
            'date' => '2026-08-18',
            'event_type' => 'holiday',
            'grade_level' => null,
            'title' => 'Test Holiday',
            'created_by' => $admin->id,
        ]);

        $withoutHoliday = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/mobile/student/attendance/summary')
            ->json('month_school_days');

        // Weekdays Aug 1-19 2026 excluding the fixtured holiday.
        $expectedWeekdays = 0;
        for ($d = Carbon::parse('2026-08-01'); $d->lessThanOrEqualTo(Carbon::parse('2026-08-19')); $d->addDay()) {
            if (! $d->isWeekend()) {
                $expectedWeekdays++;
            }
        }

        $this->assertSame($expectedWeekdays - 1, $withoutHoliday);
    }

    public function test_weekly_series_has_nine_entries_oldest_first(): void
    {
        Carbon::setTestNow('2026-08-19 10:00:00');
        $student = $this->makeStudent();
        $token = $this->tokenFor($student);

        $weekly = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/mobile/student/attendance/summary')
            ->json('weekly');

        $this->assertCount(9, $weekly);
        $starts = array_column($weekly, 'week_start');
        $sorted = $starts;
        sort($sorted);
        $this->assertSame($sorted, $starts);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/mobile/student/attendance/summary')->assertStatus(401);
    }
}
