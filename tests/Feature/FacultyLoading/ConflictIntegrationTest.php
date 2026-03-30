<?php

namespace Tests\Feature\FacultyLoading;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\ClassSchedule;
use App\Models\FacultyLoading\Classroom;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Subject;
use App\Models\User;
use App\Services\FacultyLoading\ConflictDetectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Integration tests for ConflictDetectionService.
 *
 * These tests hit the actual SQLite :memory: database to verify that the
 * DB-backed conflict queries (detectFacultyConflict, detectRoomConflict,
 * detectSectionConflict, getDailyTeachingHours, etc.) work correctly
 * with real model records.
 *
 * Pure time-overlap logic tests are in ConflictDetectionTest (no DB needed).
 */
class ConflictIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private ConflictDetectionService $service;
    private User       $faculty;
    private Subject    $subject;
    private Classroom  $room;
    private SchoolYear $schoolYear;
    private AcademicTerm $term;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ConflictDetectionService();

        $this->faculty    = User::factory()->create(['email_verified_at' => now()]);
        $this->schoolYear = SchoolYear::create(['name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30', 'is_current' => true, 'status' => 'active']);
        $this->term       = AcademicTerm::create(['school_year_id' => $this->schoolYear->id, 'name' => '1st Semester', 'term_type' => '1st_semester', 'start_date' => '2025-08-01', 'end_date' => '2025-12-31', 'is_current' => true]);
        $this->subject    = Subject::create(['code' => 'MATH101', 'name' => 'Math', 'credit_units' => 3, 'lecture_hours' => 3, 'load_units' => 3, 'subject_type' => 'lecture', 'grade_level' => 9, 'sessions_per_week' => 5, 'minutes_per_session' => 60, 'is_active' => true]);
        $this->room       = Classroom::create(['name' => 'Room 101', 'code' => 'R101', 'classroom_type' => 'lecture', 'capacity' => 40, 'is_available' => true]);
    }

    // ── Helper ────────────────────────────────────────────────────────────────

    private function makeSchedule(array $overrides = []): ClassSchedule
    {
        return ClassSchedule::create(array_merge([
            'user_id'          => $this->faculty->id,
            'subject_id'       => $this->subject->id,
            'section_id'       => 1,
            'classroom_id'     => $this->room->id,
            'school_year_id'   => $this->schoolYear->id,
            'academic_term_id' => $this->term->id,
            'day_of_week'      => 'Monday',
            'start_time'       => '08:00:00',
            'end_time'         => '10:00:00',
            'status'           => 'active',
        ], $overrides));
    }

    // ── Faculty Conflict ──────────────────────────────────────────────────────

    /** @test */
    public function it_detects_faculty_conflict_on_overlapping_slot(): void
    {
        $this->makeSchedule(['start_time' => '08:00:00', 'end_time' => '10:00:00']);

        $conflicts = $this->service->detectFacultyConflict(
            $this->faculty->id, 'Monday', '09:00:00', '11:00:00', $this->term->id
        );

        $this->assertCount(1, $conflicts);
    }

    /** @test */
    public function it_does_not_flag_faculty_conflict_on_consecutive_slot(): void
    {
        $this->makeSchedule(['start_time' => '08:00:00', 'end_time' => '10:00:00']);

        $conflicts = $this->service->detectFacultyConflict(
            $this->faculty->id, 'Monday', '10:00:00', '12:00:00', $this->term->id
        );

        $this->assertCount(0, $conflicts);
    }

    /** @test */
    public function it_does_not_flag_faculty_conflict_on_different_day(): void
    {
        $this->makeSchedule(['day_of_week' => 'Monday']);

        $conflicts = $this->service->detectFacultyConflict(
            $this->faculty->id, 'Tuesday', '08:00:00', '10:00:00', $this->term->id
        );

        $this->assertCount(0, $conflicts);
    }

    /** @test */
    public function it_does_not_flag_faculty_conflict_on_different_term(): void
    {
        $this->makeSchedule();
        $otherTerm = AcademicTerm::create(['school_year_id' => $this->schoolYear->id, 'name' => '2nd Semester', 'term_type' => '2nd_semester', 'start_date' => '2026-01-01', 'end_date' => '2026-06-30', 'is_current' => false]);

        $conflicts = $this->service->detectFacultyConflict(
            $this->faculty->id, 'Monday', '08:00:00', '10:00:00', $otherTerm->id
        );

        $this->assertCount(0, $conflicts);
    }

    /** @test */
    public function it_excludes_given_schedule_id_from_faculty_conflict(): void
    {
        $existing = $this->makeSchedule();

        // Updating the same schedule — should not conflict with itself
        $conflicts = $this->service->detectFacultyConflict(
            $this->faculty->id, 'Monday', '08:00:00', '10:00:00', $this->term->id,
            $existing->id
        );

        $this->assertCount(0, $conflicts);
    }

    /** @test */
    public function it_ignores_cancelled_schedules_in_faculty_conflict_check(): void
    {
        $this->makeSchedule(['status' => 'cancelled']);

        $conflicts = $this->service->detectFacultyConflict(
            $this->faculty->id, 'Monday', '08:00:00', '10:00:00', $this->term->id
        );

        $this->assertCount(0, $conflicts);
    }

    // ── Room Conflict ─────────────────────────────────────────────────────────

    /** @test */
    public function it_detects_room_conflict_on_overlapping_slot(): void
    {
        $otherFaculty = User::factory()->create(['email_verified_at' => now()]);
        $this->makeSchedule(['start_time' => '08:00:00', 'end_time' => '10:00:00']);

        $conflicts = $this->service->detectRoomConflict(
            $this->room->id, 'Monday', '09:00:00', '11:00:00', $this->term->id
        );

        $this->assertCount(1, $conflicts);
    }

    /** @test */
    public function it_does_not_flag_room_conflict_on_consecutive_slot(): void
    {
        $this->makeSchedule(['start_time' => '08:00:00', 'end_time' => '10:00:00']);

        $conflicts = $this->service->detectRoomConflict(
            $this->room->id, 'Monday', '10:00:00', '12:00:00', $this->term->id
        );

        $this->assertCount(0, $conflicts);
    }

    /** @test */
    public function it_does_not_flag_room_conflict_for_different_room(): void
    {
        $otherRoom = Classroom::create(['name' => 'Room 102', 'code' => 'R102', 'classroom_type' => 'lecture', 'capacity' => 40, 'is_available' => true]);
        $this->makeSchedule(['classroom_id' => $otherRoom->id]);

        $conflicts = $this->service->detectRoomConflict(
            $this->room->id, 'Monday', '08:00:00', '10:00:00', $this->term->id
        );

        $this->assertCount(0, $conflicts);
    }

    // ── Section Conflict ──────────────────────────────────────────────────────

    /** @test */
    public function it_detects_section_conflict_on_overlapping_slot(): void
    {
        $this->makeSchedule(['section_id' => 5, 'start_time' => '08:00:00', 'end_time' => '10:00:00']);

        $conflicts = $this->service->detectSectionConflict(
            5, 'Monday', '09:00:00', '11:00:00', $this->term->id
        );

        $this->assertCount(1, $conflicts);
    }

    /** @test */
    public function it_does_not_flag_section_conflict_for_different_section(): void
    {
        $this->makeSchedule(['section_id' => 5]);

        $conflicts = $this->service->detectSectionConflict(
            6, 'Monday', '08:00:00', '10:00:00', $this->term->id
        );

        $this->assertCount(0, $conflicts);
    }

    // ── detectAllConflicts ────────────────────────────────────────────────────

    /** @test */
    public function detect_all_conflicts_returns_all_three_axes(): void
    {
        $this->makeSchedule(['start_time' => '08:00:00', 'end_time' => '10:00:00', 'section_id' => 3]);

        $result = $this->service->detectAllConflicts([
            'faculty_id'       => $this->faculty->id,
            'classroom_id'     => $this->room->id,
            'section_id'       => 3,
            'day_of_week'      => 'Monday',
            'start_time'       => '09:00:00',
            'end_time'         => '11:00:00',
            'academic_term_id' => $this->term->id,
        ]);

        $this->assertTrue($result['has_conflicts']);
        $this->assertNotEmpty($result['conflicts']['faculty']);
        $this->assertNotEmpty($result['conflicts']['room']);
        $this->assertNotEmpty($result['conflicts']['section']);
        $this->assertNotEmpty($result['messages']);
    }

    /** @test */
    public function detect_all_conflicts_returns_no_conflicts_for_clear_slot(): void
    {
        $this->makeSchedule(['start_time' => '08:00:00', 'end_time' => '10:00:00']);

        $result = $this->service->detectAllConflicts([
            'faculty_id'       => $this->faculty->id,
            'classroom_id'     => $this->room->id,
            'section_id'       => 1,
            'day_of_week'      => 'Tuesday',  // different day
            'start_time'       => '08:00:00',
            'end_time'         => '10:00:00',
            'academic_term_id' => $this->term->id,
        ]);

        $this->assertFalse($result['has_conflicts']);
        $this->assertEmpty($result['messages']);
    }

    // ── Daily Teaching Hours ──────────────────────────────────────────────────

    /** @test */
    public function get_daily_teaching_hours_sums_active_schedules_for_the_day(): void
    {
        // Two 1-hour slots on Monday = 2 hours
        $this->makeSchedule(['start_time' => '08:00:00', 'end_time' => '09:00:00']);
        $this->makeSchedule(['start_time' => '10:00:00', 'end_time' => '11:00:00']);

        $hours = $this->service->getDailyTeachingHours(
            $this->faculty->id, 'Monday', $this->term->id
        );

        $this->assertSame(2.0, $hours);
    }

    /** @test */
    public function get_daily_teaching_hours_excludes_cancelled_schedules(): void
    {
        $this->makeSchedule(['start_time' => '08:00:00', 'end_time' => '09:00:00', 'status' => 'cancelled']);
        $this->makeSchedule(['start_time' => '10:00:00', 'end_time' => '11:00:00']);

        $hours = $this->service->getDailyTeachingHours(
            $this->faculty->id, 'Monday', $this->term->id
        );

        $this->assertSame(1.0, $hours);
    }

    /** @test */
    public function get_daily_teaching_hours_excludes_other_days(): void
    {
        $this->makeSchedule(['day_of_week' => 'Monday', 'start_time' => '08:00:00', 'end_time' => '09:00:00']);

        $hours = $this->service->getDailyTeachingHours(
            $this->faculty->id, 'Tuesday', $this->term->id
        );

        $this->assertSame(0.0, $hours);
    }

    /** @test */
    public function get_daily_teaching_hours_excludes_specified_schedule_id(): void
    {
        $s = $this->makeSchedule(['start_time' => '08:00:00', 'end_time' => '10:00:00']); // 2 hrs

        $hours = $this->service->getDailyTeachingHours(
            $this->faculty->id, 'Monday', $this->term->id, $s->id
        );

        $this->assertSame(0.0, $hours);
    }

    /** @test */
    public function get_projected_daily_hours_adds_new_slot_to_existing_total(): void
    {
        $this->makeSchedule(['start_time' => '08:00:00', 'end_time' => '10:00:00']); // 2 hrs existing

        // Adding a 1.5-hour slot
        $projected = $this->service->getProjectedDailyHours(
            $this->faculty->id, 'Monday', '11:00:00', '12:30:00', $this->term->id
        );

        $this->assertSame(3.5, $projected);
    }

    // ── Multiple conflicts ────────────────────────────────────────────────────

    /** @test */
    public function two_overlapping_schedules_both_appear_in_conflict_list(): void
    {
        $this->makeSchedule(['start_time' => '08:00:00', 'end_time' => '10:00:00']);
        $this->makeSchedule(['start_time' => '09:00:00', 'end_time' => '11:00:00']);

        $conflicts = $this->service->detectFacultyConflict(
            $this->faculty->id, 'Monday', '08:30:00', '09:30:00', $this->term->id
        );

        $this->assertCount(2, $conflicts);
    }

    /** @test */
    public function tentative_schedule_is_not_included_in_conflict_check(): void
    {
        // Only 'active' schedules are checked — tentative are skipped intentionally
        $this->makeSchedule(['status' => 'tentative']);

        $conflicts = $this->service->detectFacultyConflict(
            $this->faculty->id, 'Monday', '08:00:00', '10:00:00', $this->term->id
        );

        $this->assertCount(0, $conflicts);
    }
}
