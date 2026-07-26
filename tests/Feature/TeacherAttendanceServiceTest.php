<?php

namespace Tests\Feature;

use App\Exports\TeacherAttendanceExport;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\Classroom;
use App\Models\FacultyLoading\ClassSchedule;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\FacultyLoading\TeacherTapLog;
use App\Models\User;
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

    private function makeScheduledTeacherAndClassroom(): array
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
        ClassSchedule::create([
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

        return [$teacher, $classroom];
    }
}
