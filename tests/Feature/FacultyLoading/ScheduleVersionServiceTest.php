<?php

namespace Tests\Feature\FacultyLoading;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\ClassSchedule;
use App\Models\FacultyLoading\Classroom;
use App\Models\FacultyLoading\FacultyLoad;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\User;
use App\Services\FacultyLoading\ScheduleVersionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers ScheduleVersionService: save() snapshots the current tentative
 * schedule, compare() diffs two states by load_assignment_id, and restore()
 * replaces the live tentative schedule with a saved snapshot.
 */
class ScheduleVersionServiceTest extends TestCase
{
    use RefreshDatabase;

    private ScheduleVersionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ScheduleVersionService::class);
    }

    private function makeTermWithSession(): array
    {
        $sy   = SchoolYear::create([
            'name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30',
            'is_current' => true, 'status' => 'active',
        ]);
        $term = AcademicTerm::create([
            'school_year_id' => $sy->id, 'name' => '1st Semester', 'term_type' => '1st_semester',
            'start_date' => '2025-08-01', 'end_date' => '2025-12-31', 'is_current' => true,
        ]);
        $faculty = User::factory()->create(['email_verified_at' => now()]);
        $subject = Subject::create([
            'school_year_id' => $sy->id,
            'code' => 'MATH1', 'name' => 'Mathematics 1', 'credit_units' => 3, 'lecture_hours' => 3,
            'load_units' => 3, 'subject_type' => 'lecture', 'grade_level' => 9,
            'sessions_per_week' => 5, 'minutes_per_session' => 60, 'is_active' => true,
        ]);
        $room = Classroom::create([
            'school_year_id' => $sy->id,
            'name' => 'Room 1', 'code' => 'R1', 'classroom_type' => 'lecture', 'capacity' => 40, 'is_available' => true,
        ]);
        $section = Section::create([
            'levelid' => 9, 'sectionname' => 'Diamond', 'syid' => $sy->id, 'school_year_id' => $sy->id, 'is_active' => true,
        ]);
        $facultyLoad = FacultyLoad::create([
            'user_id' => $faculty->id, 'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
            'teaching_units' => 3, 'total_units' => 3, 'full_load_threshold' => 18, 'load_status' => 'underload',
        ]);
        $loadAssignment = LoadAssignment::create([
            'faculty_load_id' => $facultyLoad->id, 'user_id' => $faculty->id,
            'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
            'assignment_type' => 'teaching', 'subject_id' => $subject->id, 'section_id' => $section->id, 'load_units' => 3,
        ]);

        $schedule = ClassSchedule::create([
            'load_assignment_id' => $loadAssignment->id, 'user_id' => $faculty->id, 'subject_id' => $subject->id,
            'section_id' => $section->id, 'classroom_id' => $room->id, 'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
            'day_of_week' => 'Monday', 'start_time' => '08:00:00', 'end_time' => '09:00:00', 'status' => 'tentative',
        ]);

        return compact('sy', 'term', 'faculty', 'subject', 'room', 'loadAssignment', 'schedule');
    }

    public function test_save_snapshots_the_current_tentative_schedule(): void
    {
        $fx = $this->makeTermWithSession();

        $version = $this->service->save($fx['term']->id, 'Draft A', 'first pass', null);

        $this->assertSame(1, $version->session_count);
        $this->assertCount(1, $version->schedule_snapshot);
        $this->assertSame('Monday', $version->schedule_snapshot[0]['day_of_week']);
    }

    public function test_compare_reports_a_moved_session_under_its_load_assignment(): void
    {
        $fx = $this->makeTermWithSession();
        $version = $this->service->save($fx['term']->id, 'Draft A', null, null);

        // Simulate a manual drag/edit: move the session to Tuesday.
        $fx['schedule']->update(['day_of_week' => 'Tuesday', 'start_time' => '10:00:00', 'end_time' => '11:00:00']);

        $result = $this->service->compare($version, null, $fx['term']->id);

        $this->assertSame(1, $result['summary']['a']['total_sessions']);
        $this->assertSame(1, $result['summary']['b']['total_sessions']);
        $this->assertCount(1, $result['diff']);
        $entry = $result['diff'][0];
        $this->assertSame($fx['loadAssignment']->id, $entry['load_assignment_id']);
        $this->assertSame(['Monday|08:00:00-09:00:00'], $entry['removed']);
        $this->assertSame(['Tuesday|10:00:00-11:00:00'], $entry['added']);
    }

    public function test_compare_is_empty_when_nothing_changed(): void
    {
        $fx = $this->makeTermWithSession();
        $version = $this->service->save($fx['term']->id, 'Draft A', null, null);

        $result = $this->service->compare($version, null, $fx['term']->id);

        $this->assertSame([], $result['diff']);
    }

    public function test_restore_replaces_the_live_tentative_schedule_with_the_snapshot(): void
    {
        $fx = $this->makeTermWithSession();
        $version = $this->service->save($fx['term']->id, 'Draft A', null, null);

        $fx['schedule']->update(['day_of_week' => 'Friday', 'start_time' => '14:00:00', 'end_time' => '15:00:00']);

        $this->service->restore($version);

        $this->assertDatabaseHas('class_schedules', [
            'load_assignment_id' => $fx['loadAssignment']->id,
            'day_of_week' => 'Monday', 'start_time' => '08:00:00', 'status' => 'tentative',
        ]);
        $this->assertDatabaseMissing('class_schedules', ['day_of_week' => 'Friday']);
        $this->assertNotNull($version->fresh()->restored_at);
    }
}
