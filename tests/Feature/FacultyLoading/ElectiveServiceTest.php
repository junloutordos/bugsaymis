<?php

namespace Tests\Feature\FacultyLoading;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\Classroom;
use App\Models\FacultyLoading\ClassSchedule;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\User;
use App\Services\FacultyLoading\ElectiveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ElectiveService::getElectiveWindows() reads the Elective band's display
 * window from real ELEC-* class_schedules rows, mirroring how
 * ScienceCoreService::getScienceCoreWindows() already works — unlike
 * SchedulingConstants::getElectiveWindows() (a hardcoded bell-schedule
 * lookup used elsewhere, deliberately left untouched), this reflects
 * whatever actually happened to be scheduled that grade/day.
 */
class ElectiveServiceTest extends TestCase
{
    use RefreshDatabase;

    private SchoolYear $sy;

    private AcademicTerm $term;

    private Classroom $room;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sy = SchoolYear::create([
            'name' => '2026-2027', 'start_date' => '2026-08-01', 'end_date' => '2027-06-30', 'is_current' => true,
        ]);
        $this->term = AcademicTerm::create([
            'school_year_id' => $this->sy->id, 'name' => '1st Semester', 'term_type' => '1st_semester',
            'start_date' => '2026-08-01', 'end_date' => '2026-12-31', 'is_current' => true,
        ]);
        $this->room = Classroom::create([
            'school_year_id' => $this->sy->id, 'name' => 'Room 101', 'code' => 'R101',
            'classroom_type' => 'lecture', 'capacity' => 40, 'is_available' => true,
        ]);
    }

    private function electiveClass(string $sectionName, string $day, string $start, string $end, string $status = 'active'): void
    {
        $section = Section::firstOrCreate(
            ['sectionname' => $sectionName, 'school_year_id' => $this->sy->id],
            ['levelid' => 12, 'syid' => $this->sy->id, 'is_active' => true],
        );
        $subject = Subject::create([
            'school_year_id' => $this->sy->id,
            'code' => 'EL-'.substr(uniqid(), -8),
            'name' => 'Elective Subject',
            'credit_units' => 2, 'lecture_hours' => 2, 'load_units' => 2,
            'subject_type' => 'elective', 'grade_level' => 12,
            'sessions_per_week' => 2, 'minutes_per_session' => 50, 'is_active' => true,
        ]);
        ClassSchedule::create([
            'user_id' => User::factory()->create(['email_verified_at' => now()])->id,
            'subject_id' => $subject->id,
            'section_id' => $section->id,
            'classroom_id' => $this->room->id,
            'school_year_id' => $this->sy->id,
            'academic_term_id' => $this->term->id,
            'day_of_week' => $day,
            'start_time' => $start,
            'end_time' => $end,
            'status' => $status,
        ]);
    }

    public function test_concurrent_elective_sections_at_the_same_slot_collapse_to_one_window(): void
    {
        $this->electiveClass('ELEC-EL-ART-G12', 'Tuesday', '10:20', '11:10');
        $this->electiveClass('ELEC-EL-MUS-G12', 'Tuesday', '10:20', '11:10');

        $windows = app(ElectiveService::class)->getElectiveWindows($this->sy->id, $this->term->id, 12, 'Tuesday');

        $this->assertSame([['start' => '10:20', 'end' => '11:10', 'label' => 'Electives']], $windows);
    }

    public function test_non_contiguous_elective_slots_the_same_day_produce_two_windows(): void
    {
        $this->electiveClass('ELEC-EL-ART-G12', 'Tuesday', '10:20', '11:10');
        $this->electiveClass('ELEC-EL-MUS-G12', 'Tuesday', '13:50', '15:30');

        $windows = app(ElectiveService::class)->getElectiveWindows($this->sy->id, $this->term->id, 12, 'Tuesday');

        $this->assertSame([
            ['start' => '10:20', 'end' => '11:10', 'label' => 'Electives'],
            ['start' => '13:50', 'end' => '15:30', 'label' => 'Electives'],
        ], $windows);
    }

    public function test_cancelled_elective_rows_are_excluded(): void
    {
        $this->electiveClass('ELEC-EL-ART-G12', 'Tuesday', '10:20', '11:10', 'cancelled');

        $windows = app(ElectiveService::class)->getElectiveWindows($this->sy->id, $this->term->id, 12, 'Tuesday');

        $this->assertSame([], $windows);
    }

    public function test_no_elective_sections_for_the_grade_returns_empty(): void
    {
        $windows = app(ElectiveService::class)->getElectiveWindows($this->sy->id, $this->term->id, 12, 'Tuesday');

        $this->assertSame([], $windows);
    }
}
