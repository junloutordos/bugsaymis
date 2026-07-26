<?php

namespace Tests\Feature;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\Classroom;
use App\Models\FacultyLoading\ClassSchedule;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\HR\OnlinePunchGeofenceZone;
use App\Models\FacultyLoading\TeacherTapLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TeacherAttendanceQrConfirmTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
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
            'nfc_uuid' => (string) Str::uuid(),
            'qr_token' => Str::random(40),
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

    public function test_confirm_records_tap_when_inside_geofence(): void
    {
        Carbon::setTestNow('2026-07-22 10:00:00');
        [$teacher, $classroom] = $this->makeScheduledTeacherAndClassroom();
        OnlinePunchGeofenceZone::create([
            'label' => 'Campus', 'latitude' => 9.0, 'longitude' => 125.5, 'radius_meters' => 250, 'is_active' => true,
        ]);

        $response = $this->actingAs($teacher)->postJson(
            route('class-tap-qr.confirm', $classroom->qr_token),
            ['latitude' => 9.0, 'longitude' => 125.5, 'accuracy' => 20]
        );

        $response->assertOk();
        $response->assertJsonPath('tapStatus', 'late'); // within the tap window, 20 min after start
        $this->assertDatabaseHas('teacher_tap_logs', [
            'user_id' => $teacher->id,
            'classroom_id' => $classroom->id,
            'channel' => 'qr',
        ]);
    }

    public function test_confirm_rejects_and_does_not_record_when_outside_geofence(): void
    {
        Carbon::setTestNow('2026-07-22 10:00:00');
        [$teacher, $classroom] = $this->makeScheduledTeacherAndClassroom();
        OnlinePunchGeofenceZone::create([
            'label' => 'Campus', 'latitude' => 9.0, 'longitude' => 125.5, 'radius_meters' => 250, 'is_active' => true,
        ]);

        $response = $this->actingAs($teacher)->postJson(
            route('class-tap-qr.confirm', $classroom->qr_token),
            ['latitude' => 14.5995, 'longitude' => 120.9842, 'accuracy' => 20] // Manila
        );

        $response->assertStatus(422);
        $response->assertJsonPath('gateStatus', 'outside');
        $this->assertSame(0, TeacherTapLog::count());
    }

    public function test_confirm_records_tap_when_no_zones_configured_fail_open(): void
    {
        Carbon::setTestNow('2026-07-22 10:00:00');
        [$teacher, $classroom] = $this->makeScheduledTeacherAndClassroom();

        $response = $this->actingAs($teacher)->postJson(
            route('class-tap-qr.confirm', $classroom->qr_token),
            ['latitude' => 14.5995, 'longitude' => 120.9842, 'accuracy' => 20]
        );

        $response->assertOk();
        $this->assertSame(1, TeacherTapLog::count());
    }

    public function test_confirm_requires_latitude_and_longitude(): void
    {
        Carbon::setTestNow('2026-07-22 10:00:00');
        [$teacher, $classroom] = $this->makeScheduledTeacherAndClassroom();

        $response = $this->actingAs($teacher)->postJson(
            route('class-tap-qr.confirm', $classroom->qr_token),
            []
        );

        $response->assertStatus(422);
        $this->assertSame(0, TeacherTapLog::count());
    }

    /**
     * Regression guard: the QR identifier (qr_token) must never grant access
     * to the ungated NFC route. Since qr_token is 40 alphanumeric chars and
     * nfc_uuid is a 36-char UUID, the route pattern itself rejects a
     * qr_token — there is no client-controlled way to skip the location gate.
     */
    public function test_qr_token_does_not_match_the_bare_nfc_route(): void
    {
        Carbon::setTestNow('2026-07-22 10:00:00');
        [, $classroom] = $this->makeScheduledTeacherAndClassroom();

        $response = $this->get('/class-tap/'.$classroom->qr_token);

        $response->assertNotFound();
    }

    /**
     * Regression guard: an nfc_uuid (36 chars, contains hyphens) can never
     * satisfy the qr_token route pattern (40 alphanumeric chars) — there is
     * no overlap between the two identifier spaces.
     */
    public function test_confirm_rejects_nfc_uuid_in_place_of_qr_token(): void
    {
        Carbon::setTestNow('2026-07-22 10:00:00');
        [$teacher, $classroom] = $this->makeScheduledTeacherAndClassroom();

        $response = $this->actingAs($teacher)->postJson(
            '/class-tap-qr/'.$classroom->nfc_uuid.'/confirm',
            ['latitude' => 9.0, 'longitude' => 125.5, 'accuracy' => 20]
        );

        $response->assertStatus(404);
        $this->assertSame(0, TeacherTapLog::count());
    }
}
