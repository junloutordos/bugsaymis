<?php

namespace Tests\Feature\FacultyLoading;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\ClassSchedule;
use App\Models\FacultyLoading\Classroom;
use App\Models\FacultyLoading\FacultyLoad;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Subject;
use App\Models\User;
use App\Services\FacultyLoading\ScheduleValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Integration tests for ScheduleValidationService.
 *
 * Tests the full validation pipeline:
 *   1. Input validation (required fields, time ordering, valid days)
 *   2. Conflict detection (faculty, room, section)
 *   3. Daily teaching hours (6 h normal / 8 h exigency)
 *   4. Faculty load limits (admin cap, research cap, overload warning)
 *   5. Classroom availability
 *   6. Subject–room type compatibility
 *
 * All DB operations run against SQLite :memory: via RefreshDatabase.
 */
class ScheduleValidationTest extends TestCase
{
    use RefreshDatabase;

    private ScheduleValidationService $svc;
    private User       $faculty;
    private Subject    $lectureSubject;
    private Subject    $labSubject;
    private Classroom  $lectureRoom;
    private Classroom  $labRoom;
    private SchoolYear $schoolYear;
    private AcademicTerm $term;

    protected function setUp(): void
    {
        parent::setUp();

        $this->svc = app(ScheduleValidationService::class);

        $this->faculty      = User::factory()->create(['email_verified_at' => now()]);
        $this->schoolYear   = SchoolYear::create(['name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30', 'is_current' => true, 'status' => 'active']);
        $this->term         = AcademicTerm::create(['school_year_id' => $this->schoolYear->id, 'name' => '1st Semester', 'term_type' => '1st_semester', 'start_date' => '2025-08-01', 'end_date' => '2025-12-31', 'is_current' => true]);

        $this->lectureSubject = Subject::create(['code' => 'MATH9', 'name' => 'Mathematics 9', 'credit_units' => 4, 'lecture_hours' => 4, 'load_units' => 4, 'subject_type' => 'lecture', 'grade_level' => 9, 'sessions_per_week' => 4, 'minutes_per_session' => 60, 'is_active' => true]);
        $this->labSubject     = Subject::create(['code' => 'CHEM9', 'name' => 'Chemistry 9',   'credit_units' => 3, 'lecture_hours' => 3, 'load_units' => 4, 'subject_type' => 'lecture_lab', 'grade_level' => 9, 'sessions_per_week' => 3, 'minutes_per_session' => 60, 'is_active' => true]);

        $this->lectureRoom = Classroom::create(['name' => 'Room 101', 'code' => 'R101', 'classroom_type' => 'lecture',      'capacity' => 45, 'is_available' => true]);
        $this->labRoom     = Classroom::create(['name' => 'Chem Lab', 'code' => 'CHL1', 'classroom_type' => 'chemistry_lab', 'capacity' => 35, 'is_available' => true]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** Build a minimal valid schedule data payload. */
    private function validData(array $overrides = []): array
    {
        return array_merge([
            'faculty_id'       => $this->faculty->id,
            'subject_id'       => $this->lectureSubject->id,
            'section_id'       => 1,
            'classroom_id'     => $this->lectureRoom->id,
            'school_year_id'   => $this->schoolYear->id,
            'academic_term_id' => $this->term->id,
            'day_of_week'      => 'Monday',
            'start_time'       => '08:00:00',
            'end_time'         => '10:00:00',
        ], $overrides);
    }

    /** Seed an existing schedule to generate conflicts. */
    private function seedSchedule(array $overrides = []): ClassSchedule
    {
        return ClassSchedule::create(array_merge([
            'user_id'          => $this->faculty->id,
            'subject_id'       => $this->lectureSubject->id,
            'section_id'       => 1,
            'classroom_id'     => $this->lectureRoom->id,
            'school_year_id'   => $this->schoolYear->id,
            'academic_term_id' => $this->term->id,
            'day_of_week'      => 'Monday',
            'start_time'       => '08:00:00',
            'end_time'         => '10:00:00',
            'status'           => 'active',
        ], $overrides));
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // 1. Input Validation
    // ═══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function it_blocks_when_required_fields_are_missing(): void
    {
        $result = $this->svc->validate([]);

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
        // All 8 required fields should generate errors
        $this->assertCount(8, $result['errors']);
    }

    /** @test */
    public function it_blocks_when_start_time_equals_end_time(): void
    {
        $result = $this->svc->validate($this->validData([
            'start_time' => '09:00:00',
            'end_time'   => '09:00:00',
        ]));

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('Start time must be earlier than end time', $result['errors'][0]);
    }

    /** @test */
    public function it_blocks_when_start_time_is_after_end_time(): void
    {
        $result = $this->svc->validate($this->validData([
            'start_time' => '11:00:00',
            'end_time'   => '09:00:00',
        ]));

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('Start time must be earlier than end time', $result['errors'][0]);
    }

    /** @test */
    public function it_blocks_on_invalid_day_of_week(): void
    {
        $result = $this->svc->validate($this->validData(['day_of_week' => 'Sunday']));

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('Invalid day_of_week', $result['errors'][0]);
    }

    /** @test */
    public function it_accepts_saturday_as_a_valid_day(): void
    {
        $result = $this->svc->validate($this->validData(['day_of_week' => 'Saturday']));

        // May have warnings (no faculty load record) but should not error on day
        $dayErrors = array_filter($result['errors'], fn ($e) => str_contains($e, 'day_of_week'));
        $this->assertCount(0, $dayErrors);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // 2. Conflict Detection
    // ═══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function it_blocks_on_faculty_conflict(): void
    {
        $this->seedSchedule(['start_time' => '08:00:00', 'end_time' => '10:00:00']);

        $result = $this->svc->validate($this->validData([
            'start_time' => '09:00:00',
            'end_time'   => '11:00:00',
        ]));

        $this->assertFalse($result['valid']);
        $conflictErrors = array_filter($result['errors'], fn ($e) => str_contains($e, 'faculty') || str_contains($e, 'Faculty'));
        $this->assertNotEmpty($conflictErrors);
    }

    /** @test */
    public function it_blocks_on_room_conflict(): void
    {
        $otherFaculty = User::factory()->create(['email_verified_at' => now()]);
        // Different faculty, same room, same slot
        $this->seedSchedule([
            'user_id'      => $otherFaculty->id,
            'start_time'   => '08:00:00',
            'end_time'     => '10:00:00',
            'classroom_id' => $this->lectureRoom->id,
        ]);

        $result = $this->svc->validate($this->validData([
            'faculty_id' => $this->faculty->id,
            'start_time' => '09:00:00',
            'end_time'   => '11:00:00',
        ]));

        $this->assertFalse($result['valid']);
        $conflictErrors = array_filter($result['errors'], fn ($e) => str_contains($e, 'room') || str_contains($e, 'Room'));
        $this->assertNotEmpty($conflictErrors);
    }

    /** @test */
    public function it_blocks_on_section_conflict(): void
    {
        $otherFaculty = User::factory()->create(['email_verified_at' => now()]);
        // Different faculty, same section, overlapping slot
        $this->seedSchedule([
            'user_id'    => $otherFaculty->id,
            'section_id' => 99,
            'start_time' => '08:00:00',
            'end_time'   => '10:00:00',
        ]);

        $result = $this->svc->validate($this->validData([
            'faculty_id'  => $this->faculty->id,
            'section_id'  => 99,
            'start_time'  => '09:00:00',
            'end_time'    => '11:00:00',
        ]));

        $this->assertFalse($result['valid']);
        $conflictErrors = array_filter($result['errors'], fn ($e) => str_contains($e, 'section') || str_contains($e, 'Section'));
        $this->assertNotEmpty($conflictErrors);
    }

    /** @test */
    public function it_passes_when_slot_is_consecutive_not_overlapping(): void
    {
        $this->seedSchedule(['start_time' => '08:00:00', 'end_time' => '10:00:00']);

        // Consecutive (10:00 → 12:00) — should not conflict
        $result = $this->svc->validate($this->validData([
            'start_time' => '10:00:00',
            'end_time'   => '12:00:00',
        ]));

        $this->assertTrue($result['valid']);
    }

    /** @test */
    public function it_passes_conflict_check_on_different_day(): void
    {
        $this->seedSchedule(['day_of_week' => 'Monday']);

        $result = $this->svc->validate($this->validData(['day_of_week' => 'Tuesday']));

        // No conflict errors; may have load warnings
        $conflictErrors = array_filter($result['errors'], fn ($e) =>
            str_contains($e, 'conflict') || str_contains($e, 'faculty') || str_contains($e, 'room')
        );
        $this->assertEmpty($conflictErrors);
    }

    /** @test */
    public function it_excludes_given_schedule_id_from_conflict_check(): void
    {
        $existing = $this->seedSchedule();

        // Updating the same schedule — should not conflict with itself
        $result = $this->svc->validate($this->validData(), $existing->id);

        $this->assertTrue($result['valid']);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // 3. Daily Teaching Hours
    // ═══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function it_warns_when_projected_daily_hours_exceed_normal_limit(): void
    {
        // Existing: 5 hours on Monday
        $this->seedSchedule(['start_time' => '08:00:00', 'end_time' => '13:00:00']);

        // Adding 2 more hours → projected 7 h (>6 h normal, ≤8 h exigency)
        $result = $this->svc->validate($this->validData([
            'start_time' => '14:00:00',
            'end_time'   => '16:00:00',
        ]));

        $hourWarnings = array_filter($result['warnings'], fn ($w) => str_contains($w, 'hours'));
        $this->assertNotEmpty($hourWarnings);
    }

    /** @test */
    public function it_blocks_when_projected_daily_hours_exceed_exigency_limit(): void
    {
        // Existing: 7 hours on Monday
        $this->seedSchedule(['start_time' => '07:00:00', 'end_time' => '14:00:00']);

        // Adding 2 more hours → projected 9 h (>8 h exigency hard limit)
        $result = $this->svc->validate($this->validData([
            'start_time' => '15:00:00',
            'end_time'   => '17:00:00',
        ]));

        $this->assertFalse($result['valid']);
        $hourErrors = array_filter($result['errors'], fn ($e) => str_contains($e, 'hours') || str_contains($e, 'exigency'));
        $this->assertNotEmpty($hourErrors);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // 4. Faculty Load Limits
    // ═══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function it_warns_when_no_faculty_load_record_exists_for_term(): void
    {
        $result = $this->svc->validate($this->validData());

        $loadWarnings = array_filter($result['warnings'], fn ($w) => str_contains($w, 'load record'));
        $this->assertNotEmpty($loadWarnings);
    }

    /** @test */
    public function it_blocks_when_admin_load_exceeds_cap(): void
    {
        FacultyLoad::create([
            'user_id'             => $this->faculty->id,
            'school_year_id'      => $this->schoolYear->id,
            'academic_term_id'    => $this->term->id,
            'admin_units'         => 16.00,   // exceeds MAX_ADMIN_UNITS (15)
            'teaching_units'      => 0,
            'research_units'      => 0,
            'cocurricular_units'  => 0,
            'committee_units'     => 0,
            'total_units'         => 16.00,
            'load_status'         => 'underload',
            'full_load_threshold' => 18.00,
            'overload_approved'   => false,
        ]);

        $result = $this->svc->validate($this->validData());

        $this->assertFalse($result['valid']);
        $adminErrors = array_filter($result['errors'], fn ($e) => str_contains($e, 'Administrative') || str_contains($e, 'admin'));
        $this->assertNotEmpty($adminErrors);
    }

    /** @test */
    public function it_blocks_when_research_load_exceeds_cap(): void
    {
        FacultyLoad::create([
            'user_id'             => $this->faculty->id,
            'school_year_id'      => $this->schoolYear->id,
            'academic_term_id'    => $this->term->id,
            'research_units'      => 6.00,    // exceeds MAX_RESEARCH_UNITS (5)
            'teaching_units'      => 0,
            'admin_units'         => 0,
            'cocurricular_units'  => 0,
            'committee_units'     => 0,
            'total_units'         => 6.00,
            'load_status'         => 'underload',
            'full_load_threshold' => 18.00,
            'overload_approved'   => false,
        ]);

        $result = $this->svc->validate($this->validData());

        $this->assertFalse($result['valid']);
        $researchErrors = array_filter($result['errors'], fn ($e) => str_contains($e, 'Research') || str_contains($e, 'research'));
        $this->assertNotEmpty($researchErrors);
    }

    /** @test */
    public function it_warns_when_load_is_overload_and_not_yet_approved(): void
    {
        FacultyLoad::create([
            'user_id'             => $this->faculty->id,
            'school_year_id'      => $this->schoolYear->id,
            'academic_term_id'    => $this->term->id,
            'teaching_units'      => 20.00,   // overload
            'research_units'      => 0,
            'admin_units'         => 0,
            'cocurricular_units'  => 0,
            'committee_units'     => 0,
            'total_units'         => 20.00,
            'load_status'         => 'overload',
            'full_load_threshold' => 18.00,
            'overload_approved'   => false,
        ]);

        $result = $this->svc->validate($this->validData());

        $overloadWarnings = array_filter($result['warnings'], fn ($w) => str_contains($w, 'overload'));
        $this->assertNotEmpty($overloadWarnings);
    }

    /** @test */
    public function it_does_not_warn_about_overload_when_already_approved(): void
    {
        FacultyLoad::create([
            'user_id'             => $this->faculty->id,
            'school_year_id'      => $this->schoolYear->id,
            'academic_term_id'    => $this->term->id,
            'teaching_units'      => 20.00,
            'research_units'      => 0,
            'admin_units'         => 0,
            'cocurricular_units'  => 0,
            'committee_units'     => 0,
            'total_units'         => 20.00,
            'load_status'         => 'overload',
            'full_load_threshold' => 18.00,
            'overload_approved'   => true,   // already approved
        ]);

        $result = $this->svc->validate($this->validData());

        $overloadWarnings = array_filter($result['warnings'], fn ($w) => str_contains($w, 'overload'));
        $this->assertEmpty($overloadWarnings);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // 5. Classroom Availability
    // ═══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function it_blocks_when_classroom_is_unavailable(): void
    {
        $this->lectureRoom->update(['is_available' => false]);

        $result = $this->svc->validate($this->validData());

        $this->assertFalse($result['valid']);
        $availErrors = array_filter($result['errors'], fn ($e) => str_contains($e, 'unavailable'));
        $this->assertNotEmpty($availErrors);
    }

    /** @test */
    public function it_passes_when_classroom_is_available(): void
    {
        $result = $this->svc->validate($this->validData());

        $availErrors = array_filter($result['errors'], fn ($e) => str_contains($e, 'unavailable'));
        $this->assertEmpty($availErrors);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // 6. Subject–Room Type Compatibility
    // ═══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function it_warns_when_lab_subject_assigned_to_lecture_room(): void
    {
        $result = $this->svc->validate($this->validData([
            'subject_id'   => $this->labSubject->id,
            'classroom_id' => $this->lectureRoom->id,   // lab subject → lecture room
        ]));

        $roomWarnings = array_filter($result['warnings'], fn ($w) => str_contains($w, 'laboratory') || str_contains($w, 'lab'));
        $this->assertNotEmpty($roomWarnings);
    }

    /** @test */
    public function it_warns_when_lecture_subject_assigned_to_lab_room(): void
    {
        $result = $this->svc->validate($this->validData([
            'subject_id'   => $this->lectureSubject->id,
            'classroom_id' => $this->labRoom->id,       // lecture subject → lab room
        ]));

        $roomWarnings = array_filter($result['warnings'], fn ($w) => str_contains($w, 'lecture') || str_contains($w, 'lab'));
        $this->assertNotEmpty($roomWarnings);
    }

    /** @test */
    public function it_does_not_warn_when_lab_subject_is_in_lab_room(): void
    {
        $result = $this->svc->validate($this->validData([
            'subject_id'   => $this->labSubject->id,
            'classroom_id' => $this->labRoom->id,
        ]));

        $roomWarnings = array_filter($result['warnings'], fn ($w) => str_contains($w, 'laboratory') || str_contains($w, 'requires'));
        $this->assertEmpty($roomWarnings);
    }

    /** @test */
    public function it_does_not_warn_when_lecture_subject_is_in_lecture_room(): void
    {
        $result = $this->svc->validate($this->validData([
            'subject_id'   => $this->lectureSubject->id,
            'classroom_id' => $this->lectureRoom->id,
        ]));

        $roomWarnings = array_filter($result['warnings'], fn ($w) => str_contains($w, 'lecture subject'));
        $this->assertEmpty($roomWarnings);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // Full Pipeline: clean valid slot
    // ═══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function it_returns_valid_with_no_errors_or_conflicts_for_a_clean_slot(): void
    {
        // Pre-create a faculty load record to avoid the "no load record" warning
        FacultyLoad::create([
            'user_id'             => $this->faculty->id,
            'school_year_id'      => $this->schoolYear->id,
            'academic_term_id'    => $this->term->id,
            'teaching_units'      => 6.00,
            'research_units'      => 0,
            'admin_units'         => 0,
            'cocurricular_units'  => 0,
            'committee_units'     => 0,
            'total_units'         => 6.00,
            'load_status'         => 'underload',
            'full_load_threshold' => 18.00,
            'overload_approved'   => false,
        ]);

        $result = $this->svc->validate($this->validData());

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    /** @test */
    public function it_returns_result_structure_with_all_expected_keys(): void
    {
        $result = $this->svc->validate($this->validData());

        $this->assertArrayHasKey('valid',       $result);
        $this->assertArrayHasKey('errors',      $result);
        $this->assertArrayHasKey('warnings',    $result);
        $this->assertArrayHasKey('conflicts',   $result);
        $this->assertArrayHasKey('load_check',  $result);
        $this->assertArrayHasKey('hours_check', $result);
    }
}
