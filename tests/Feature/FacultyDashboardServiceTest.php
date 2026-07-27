<?php

namespace Tests\Feature;

use App\Models\ClassRecord\ClassRecord;
use App\Models\ClassRecord\GradingOption;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\ClassSchedule;
use App\Models\FacultyLoading\FacultyLoad;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Subject;
use App\Models\User;
use App\Services\FacultyDashboardService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FacultyDashboardServiceTest extends TestCase
{
    use RefreshDatabase;

    private SchoolYear $sy;
    private AcademicTerm $term;
    private Subject $subject;
    private User $faculty;
    private GradingOption $option;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sy = SchoolYear::create([
            'name' => '2098-2099', 'start_date' => '2098-06-01', 'end_date' => '2099-03-31',
            'is_current' => true, 'status' => 'active',
        ]);
        $this->term = AcademicTerm::create([
            'school_year_id' => $this->sy->id, 'name' => 'Full Term', 'term_type' => '1st_semester',
            'start_date' => '2098-06-01', 'end_date' => '2099-03-31', 'is_current' => true,
        ]);
        $this->subject = Subject::create([
            'school_year_id' => $this->sy->id, 'code' => 'CS7', 'name' => 'Computer Science 7',
            'credit_units' => 3, 'lecture_hours' => 3, 'load_units' => 3, 'subject_type' => 'lecture',
            'grade_level' => 7, 'sessions_per_week' => 3, 'minutes_per_session' => 60, 'is_active' => true,
        ]);
        $this->option = GradingOption::create(['name' => 'Default', 'is_active' => true]);
        $this->faculty = User::factory()->create();

        // Make the user faculty via a teaching load assignment.
        $load = FacultyLoad::create([
            'user_id' => $this->faculty->id, 'school_year_id' => $this->sy->id, 'academic_term_id' => $this->term->id,
            'teaching_units' => 3, 'total_units' => 3, 'full_load_threshold' => 18, 'load_status' => 'underload',
        ]);
        LoadAssignment::create([
            'faculty_load_id' => $load->id, 'user_id' => $this->faculty->id,
            'school_year_id' => $this->sy->id, 'academic_term_id' => $this->term->id,
            'assignment_type' => 'teaching', 'subject_id' => $this->subject->id, 'section_id' => null, 'load_units' => 3,
        ]);
    }

    private function schedule(string $entryType, string $start, string $end, string $status = 'active', ?int $termId = null): ClassSchedule
    {
        return ClassSchedule::create([
            'user_id' => $this->faculty->id,
            'subject_id' => $entryType === 'class' ? $this->subject->id : null,
            'section_id' => null,
            'classroom_id' => null,
            'school_year_id' => $this->sy->id,
            'academic_term_id' => $termId ?? $this->term->id,
            'entry_type' => $entryType,
            'title' => $entryType === 'class' ? null : 'Consultation',
            'day_of_week' => Carbon::today()->format('l'),
            'start_time' => $start,
            'end_time' => $end,
            'status' => $status,
        ]);
    }

    private function record(string $status): ClassRecord
    {
        return ClassRecord::create([
            'grading_option_id' => $this->option->id,
            'school_year_id' => $this->sy->id,
            'school_year' => $this->sy->name,
            'subject_name' => 'Computer Science 7',
            'year_level_section' => 'G-7 Rizal',
            'teacher_id' => $this->faculty->id,
            'status' => $status,
        ]);
    }

    public function test_classes_today_and_today_schedule_exclude_non_teaching(): void
    {
        $this->schedule('class', '08:00', '09:00');
        $this->schedule('non_teaching', '09:00', '10:00');

        $payload = app(FacultyDashboardService::class)->payload($this->faculty);

        $this->assertSame(1, $payload['cards']['classes_today']);
        $this->assertCount(1, $payload['todaySchedule']);
        $this->assertSame('Computer Science 7', $payload['todaySchedule'][0]['title']);
    }

    public function test_cancelled_schedule_rows_are_excluded_from_classes_today(): void
    {
        // Regression: the status filter used to compare against 'inactive', a
        // value that doesn't exist in the enum('active','tentative','cancelled')
        // column — so it never actually excluded anything, including cancelled
        // rows left behind by a room swap or schedule change.
        $this->schedule('class', '08:00', '09:00', status: 'active');
        $this->schedule('class', '14:30', '15:30', status: 'cancelled');

        $payload = app(FacultyDashboardService::class)->payload($this->faculty);

        $this->assertSame(1, $payload['cards']['classes_today']);
        $this->assertCount(1, $payload['todaySchedule']);
    }

    public function test_schedule_from_a_different_academic_term_is_excluded(): void
    {
        // Regression: classes_today/todaySchedule only scoped by school_year_id,
        // not academic_term_id — a stale row from a past term for the same
        // school year would bleed into "today's classes" for the current term.
        $otherTerm = AcademicTerm::create([
            'school_year_id' => $this->sy->id, 'name' => 'Other Term', 'term_type' => '2nd_semester',
            'start_date' => '2098-06-01', 'end_date' => '2099-03-31', 'is_current' => false,
        ]);

        $this->schedule('class', '08:00', '09:00');
        $this->schedule('class', '14:30', '15:30', termId: $otherTerm->id);

        $payload = app(FacultyDashboardService::class)->payload($this->faculty);

        $this->assertSame(1, $payload['cards']['classes_today']);
        $this->assertCount(1, $payload['todaySchedule']);
    }

    public function test_archived_records_excluded_from_dashboard_panel_and_counts(): void
    {
        $this->record('draft');
        $this->record('submitted');
        $archived = $this->record('draft');
        $archived->update(['status' => 'archived']);

        $payload = app(FacultyDashboardService::class)->payload($this->faculty);

        $this->assertSame(2, $payload['cards']['records_total']);
        $this->assertCount(2, $payload['classRecords']);
    }

    public function test_class_record_card_links_to_inertia_page_not_json_api(): void
    {
        $rec = $this->record('draft');

        $payload = app(FacultyDashboardService::class)->payload($this->faculty);
        $url = collect($payload['classRecords'])->firstWhere('id', $rec->id)['url'];

        $this->assertSame(route('class-records.page.show', $rec->id), $url);
        $this->assertNotSame(route('class-records.show', $rec->id), $url);
    }
}
