<?php

namespace Tests\Feature;

use App\Exports\TeacherAttendanceExport;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\Classroom;
use App\Models\FacultyLoading\ClassSchedule;
use App\Models\FacultyLoading\ClassScheduleDayAdjustment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\FacultyLoading\TeacherTapLog;
use App\Models\User;
use App\Services\FacultyLoading\AdjustedClassScheduleService;
use App\Services\TeacherAttendanceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherAttendanceServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_today_schedule_history_and_export_use_legacy_sectionname_column(): void
    {
        Carbon::setTestNow('2026-07-22 10:00:00');

        $teacher = User::factory()->create();
        $schoolYear = SchoolYear::create([
            'name' => '2026-2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_current' => true,
            'status' => 'active',
        ]);
        $term = AcademicTerm::create([
            'school_year_id' => $schoolYear->id,
            'name' => '1st Semester',
            'term_type' => '1st_semester',
            'start_date' => '2026-07-01',
            'end_date' => '2026-12-31',
            'is_current' => true,
        ]);
        $section = Section::create([
            'levelid' => 9,
            'sectionname' => 'Diamond',
            'syid' => $schoolYear->id,
            'school_year_id' => $schoolYear->id,
            'is_active' => true,
        ]);
        $subject = Subject::create([
            'school_year_id' => $schoolYear->id,
            'code' => 'BIO9',
            'name' => 'Biology 9',
            'credit_units' => 3,
            'lecture_hours' => 3,
            'load_units' => 3,
            'subject_type' => 'lecture',
            'grade_level' => 9,
            'sessions_per_week' => 3,
            'minutes_per_session' => 60,
            'is_active' => true,
        ]);
        $classroom = Classroom::create([
            'school_year_id' => $schoolYear->id,
            'name' => 'Biology Laboratory',
            'code' => 'BIO-LAB',
            'classroom_type' => 'laboratory',
            'capacity' => 30,
            'is_available' => true,
        ]);
        $schedule = ClassSchedule::create([
            'user_id' => $teacher->id,
            'subject_id' => $subject->id,
            'section_id' => $section->id,
            'classroom_id' => $classroom->id,
            'school_year_id' => $schoolYear->id,
            'academic_term_id' => $term->id,
            'day_of_week' => 'Wednesday',
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
            'status' => 'active',
        ]);
        TeacherTapLog::create([
            'user_id' => $teacher->id,
            'classroom_id' => $classroom->id,
            'class_schedule_id' => $schedule->id,
            'tapped_at' => now(),
            'status' => 'on_time',
            'is_late' => false,
            'late_minutes' => 0,
        ]);

        $service = app(TeacherAttendanceService::class);

        $this->assertSame('Diamond', $service->todaySchedules()->first()['section']['name']);

        $history = $service->history([]);
        $this->assertSame('Diamond', $history->first()->classSchedule->section->sectionname);

        $exportRows = (new TeacherAttendanceExport($history->items()))->array();
        $this->assertSame('Diamond', $exportRows[0][3]);
    }

    public function test_tap_defaults_to_nfc_channel_when_called_with_legacy_two_args(): void
    {
        Carbon::setTestNow('2026-07-22 10:00:00');

        [$teacher, $classroom] = $this->makeScheduledTeacherAndClassroom();

        $service = app(TeacherAttendanceService::class);
        $result = $service->tap($classroom->nfc_uuid, $teacher);

        $this->assertSame('late', $result['status']); // within the tap window, 20 min after start
        $this->assertSame('nfc', $result['tap']->channel);
        $this->assertNull($result['tap']->ip_address);
    }

    public function test_tap_persists_qr_channel_and_location_metadata(): void
    {
        Carbon::setTestNow('2026-07-22 10:00:00');

        [$teacher, $classroom] = $this->makeScheduledTeacherAndClassroom();

        $service = app(TeacherAttendanceService::class);
        $result = $service->tap(
            $classroom->nfc_uuid,
            $teacher,
            channel: 'qr',
            ip: '203.0.113.5',
            lat: 9.0,
            lng: 125.5,
            locationStatus: 'ok',
            networkStatus: 'unknown',
        );

        $tap = $result['tap']->fresh();
        $this->assertSame('qr', $tap->channel);
        $this->assertSame('203.0.113.5', $tap->ip_address);
        $this->assertSame('ok', $tap->location_status);
        $this->assertSame('unknown', $tap->network_status);
        $this->assertEquals(9.0, (float) $tap->latitude);
        $this->assertEquals(125.5, (float) $tap->longitude);
    }

    public function test_tap_matches_schedule_when_tapped_right_at_class_start(): void
    {
        // Class starts 09:40; teacher taps at the literal start time.
        Carbon::setTestNow('2026-07-22 09:40:00');

        [$teacher, $classroom] = $this->makeScheduledTeacherAndClassroom();

        $service = app(TeacherAttendanceService::class);
        $result = $service->tap($classroom->nfc_uuid, $teacher);

        $this->assertSame('on_time', $result['status']);
        $this->assertNotNull($result['schedule']);
    }

    public function test_tap_matches_schedule_when_tapped_before_class_start(): void
    {
        // Class starts 09:40; teacher taps 10 minutes early (within the 15-min early window).
        Carbon::setTestNow('2026-07-22 09:30:00');

        [$teacher, $classroom] = $this->makeScheduledTeacherAndClassroom();

        $service = app(TeacherAttendanceService::class);
        $result = $service->tap($classroom->nfc_uuid, $teacher);

        $this->assertSame('on_time', $result['status']);
        $this->assertNotNull($result['schedule']);
    }

    public function test_tap_matches_schedule_any_time_before_class_ends(): void
    {
        // Class runs 09:40-11:00; teacher taps 50 min after start (past the old
        // 30-min late cutoff) but still well before end_time.
        Carbon::setTestNow('2026-07-22 10:30:00');

        [$teacher, $classroom] = $this->makeScheduledTeacherAndClassroom();

        $service = app(TeacherAttendanceService::class);
        $result = $service->tap($classroom->nfc_uuid, $teacher);

        $this->assertSame('late', $result['status']);
        $this->assertNotNull($result['schedule']);
    }

    public function test_tap_does_not_match_after_class_has_ended(): void
    {
        // Class runs 09:40-11:00; teacher taps after end_time.
        Carbon::setTestNow('2026-07-22 11:05:00');

        [$teacher, $classroom] = $this->makeScheduledTeacherAndClassroom();

        $service = app(TeacherAttendanceService::class);
        $result = $service->tap($classroom->nfc_uuid, $teacher);

        $this->assertSame('no_match', $result['status']);
    }

    public function test_tap_matches_adjusted_day_shifted_schedule_not_regular_schedule(): void
    {
        // Regular schedule: Wed 09:40-11:00. A published shortened-classes
        // adjustment on the same date compresses periods to 20 min, so the
        // effective slot becomes 09:40-10:00 — teacher taps at 09:55, which
        // is on_time against the adjusted end but would be well before the
        // regular end_time too, so exercise the *start* boundary instead by
        // tapping right at the compressed class's end.
        Carbon::setTestNow('2026-07-22 09:59:00');

        [$teacher, $classroom, $schedule, $term] = $this->makeScheduledTeacherClassroomAndSchedule();
        $this->publishAdjustment($term->id, '2026-07-22', ['class_duration_minutes' => 20]);

        $service = app(TeacherAttendanceService::class);
        $result = $service->tap($classroom->nfc_uuid, $teacher);

        // Under the regular schedule (09:40-11:00) this tap would be
        // 'on_time'. Under the adjusted 09:40-10:00 slot it's still within
        // the window but past the 5-min grace period, so 'late'.
        $this->assertSame('late', $result['status']);
        $this->assertSame('09:40', $result['schedule']->start_time);
        $this->assertSame('10:00', $result['schedule']->end_time);
    }

    public function test_tap_returns_no_match_once_adjusted_schedule_window_has_closed(): void
    {
        // Adjusted end is 10:00 (regular end is 11:00) — a tap at 10:30
        // must be rejected even though it's well inside the regular window.
        Carbon::setTestNow('2026-07-22 10:30:00');

        [$teacher, $classroom, , $term] = $this->makeScheduledTeacherClassroomAndSchedule();
        $this->publishAdjustment($term->id, '2026-07-22', ['class_duration_minutes' => 20]);

        $service = app(TeacherAttendanceService::class);
        $result = $service->tap($classroom->nfc_uuid, $teacher);

        $this->assertSame('no_match', $result['status']);
    }

    public function test_tap_returns_no_match_when_class_is_unplaced_on_adjusted_day(): void
    {
        Carbon::setTestNow('2026-07-22 09:45:00');

        [$teacher, $classroom, $schedule, $term] = $this->makeScheduledTeacherClassroomAndSchedule();
        $this->publishAdjustment(
            $term->id,
            '2026-07-22',
            ['class_duration_minutes' => 20],
            unplacedScheduleIds: [$schedule->id],
        );

        $service = app(TeacherAttendanceService::class);
        $result = $service->tap($classroom->nfc_uuid, $teacher);

        $this->assertSame('no_match', $result['status']);
    }

    public function test_tap_uses_regular_schedule_while_adjustment_is_still_draft(): void
    {
        // Same tap timing as the "closed adjusted window" test above, but
        // the adjustment is left in draft — should have zero effect.
        Carbon::setTestNow('2026-07-22 10:30:00');

        [$teacher, $classroom, , $term] = $this->makeScheduledTeacherClassroomAndSchedule();
        ClassScheduleDayAdjustment::create([
            'academic_term_id' => $term->id,
            'effective_date' => '2026-07-22',
            'adjustment_type' => 'shortened_classes',
            'class_duration_minutes' => 20,
            'reason' => 'Draft only, never published',
            'status' => 'draft',
        ]);

        $service = app(TeacherAttendanceService::class);
        $result = $service->tap($classroom->nfc_uuid, $teacher);

        $this->assertSame('late', $result['status']); // matches the regular 09:40-11:00 window
        $this->assertSame('11:00:00', $result['schedule']->end_time);
    }

    public function test_tap_flags_and_persists_the_day_adjustment_used(): void
    {
        Carbon::setTestNow('2026-07-22 09:45:00');

        [$teacher, $classroom, , $term] = $this->makeScheduledTeacherClassroomAndSchedule();
        $adjustment = $this->publishAdjustment($term->id, '2026-07-22', ['class_duration_minutes' => 20]);

        $service = app(TeacherAttendanceService::class);
        $result = $service->tap($classroom->nfc_uuid, $teacher);

        $this->assertTrue($result['schedule']->is_adjusted_day);
        $this->assertSame($adjustment->id, $result['tap']->class_schedule_day_adjustment_id);
    }

    public function test_today_schedules_reflects_adjusted_times_and_marks_unplaced_class_not_held(): void
    {
        Carbon::setTestNow('2026-07-22 09:00:00');

        [, , $schedule, $term] = $this->makeScheduledTeacherClassroomAndSchedule();
        $this->publishAdjustment(
            $term->id,
            '2026-07-22',
            ['class_duration_minutes' => 20],
            unplacedScheduleIds: [$schedule->id],
        );

        $service = app(TeacherAttendanceService::class);
        $today = $service->todaySchedules()->first();

        $this->assertSame('not_held', $today['tap_status']);
    }

    private function makeScheduledTeacherAndClassroom(): array
    {
        [$teacher, $classroom] = $this->makeScheduledTeacherClassroomAndSchedule();

        return [$teacher, $classroom];
    }

    private function makeScheduledTeacherClassroomAndSchedule(): array
    {
        $teacher = User::factory()->create();
        $schoolYear = SchoolYear::create([
            'name' => '2026-2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_current' => true,
            'status' => 'active',
        ]);
        $term = AcademicTerm::create([
            'school_year_id' => $schoolYear->id,
            'name' => '1st Semester',
            'term_type' => '1st_semester',
            'start_date' => '2026-07-01',
            'end_date' => '2026-12-31',
            'is_current' => true,
        ]);
        $section = Section::create([
            'levelid' => 9,
            'sectionname' => 'Diamond',
            'syid' => $schoolYear->id,
            'school_year_id' => $schoolYear->id,
            'is_active' => true,
        ]);
        $subject = Subject::create([
            'school_year_id' => $schoolYear->id,
            'code' => 'BIO9',
            'name' => 'Biology 9',
            'credit_units' => 3,
            'lecture_hours' => 3,
            'load_units' => 3,
            'subject_type' => 'lecture',
            'grade_level' => 9,
            'sessions_per_week' => 3,
            'minutes_per_session' => 60,
            'is_active' => true,
        ]);
        $classroom = Classroom::create([
            'school_year_id' => $schoolYear->id,
            'name' => 'Biology Laboratory',
            'code' => 'BIO-LAB',
            'classroom_type' => 'laboratory',
            'capacity' => 30,
            'is_available' => true,
            'nfc_uuid' => (string) \Illuminate\Support\Str::uuid(),
        ]);
        $schedule = ClassSchedule::create([
            'user_id' => $teacher->id,
            'subject_id' => $subject->id,
            'section_id' => $section->id,
            'classroom_id' => $classroom->id,
            'school_year_id' => $schoolYear->id,
            'academic_term_id' => $term->id,
            'day_of_week' => 'Wednesday',
            'start_time' => '09:40:00',
            'end_time' => '11:00:00',
            'status' => 'active',
        ]);

        return [$teacher, $classroom, $schedule, $term];
    }

    private function publishAdjustment(
        int $termId,
        string $effectiveDate,
        array $overrides = [],
        array $unplacedScheduleIds = [],
    ): ClassScheduleDayAdjustment {
        $adjustment = ClassScheduleDayAdjustment::create(array_merge([
            'academic_term_id' => $termId,
            'effective_date' => $effectiveDate,
            'adjustment_type' => 'shortened_classes',
            'class_duration_minutes' => 30,
            'reason' => 'Test adjustment',
            'status' => 'draft',
        ], $overrides));

        foreach ($unplacedScheduleIds as $scheduleId) {
            $adjustment->unplacedEntries()->create(['class_schedule_id' => $scheduleId]);
        }

        $adjustment->update([
            'schedule_snapshot' => app(AdjustedClassScheduleService::class)->generate($adjustment->fresh()),
            'status' => 'published',
            'published_at' => now(),
        ]);

        return $adjustment->fresh();
    }
}
