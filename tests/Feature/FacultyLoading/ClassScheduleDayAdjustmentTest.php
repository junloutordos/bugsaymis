<?php

namespace Tests\Feature\FacultyLoading;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\Classroom;
use App\Models\FacultyLoading\ClassSchedule;
use App\Models\FacultyLoading\ClassScheduleDayAdjustment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SchoolCalendarEvent;
use App\Models\User;
use App\Services\SchoolCalendarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassScheduleDayAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;
    private AcademicTerm $term;
    private ClassSchedule $tuesdayClass;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => 'AdjustmentManager_'.uniqid()]);
        $permission = Permission::firstOrCreate(
            ['name' => 'faculty_loading.manage'],
            ['module' => 'FacultyLoading', 'description' => 'Manage faculty loading'],
        );
        $role->permissions()->attach($permission->id);
        $this->manager = User::factory()->create(['email_verified_at' => now()]);
        $this->manager->roles()->attach($role->id);

        $schoolYear = SchoolYear::create([
            'name' => '2026-2027',
            'start_date' => '2026-08-01',
            'end_date' => '2027-06-30',
            'is_current' => true,
            'status' => 'active',
        ]);
        $this->term = AcademicTerm::create([
            'school_year_id' => $schoolYear->id,
            'name' => '1st Semester',
            'term_type' => '1st_semester',
            'start_date' => '2026-08-01',
            'end_date' => '2026-12-31',
            'is_current' => true,
        ]);

        $section = Section::create([
            'levelid' => 7,
            'sectionname' => 'Aquamarine',
            'syid' => $schoolYear->id,
            'school_year_id' => $schoolYear->id,
            'is_active' => true,
        ]);
        $faculty = User::factory()->create(['email_verified_at' => now()]);
        $subject = Subject::create([
            'school_year_id' => $schoolYear->id,
            'code' => 'MATH7',
            'name' => 'Mathematics 1',
            'credit_units' => 4,
            'lecture_hours' => 4,
            'load_units' => 4,
            'subject_type' => 'lecture',
            'grade_level' => 7,
            'sessions_per_week' => 4,
            'minutes_per_session' => 50,
            'is_active' => true,
        ]);
        $room = Classroom::create([
            'school_year_id' => $schoolYear->id,
            'name' => 'Room 101',
            'code' => 'R101',
            'classroom_type' => 'lecture',
            'capacity' => 40,
            'is_available' => true,
        ]);
        $this->tuesdayClass = ClassSchedule::create([
            'user_id' => $faculty->id,
            'subject_id' => $subject->id,
            'section_id' => $section->id,
            'classroom_id' => $room->id,
            'school_year_id' => $schoolYear->id,
            'academic_term_id' => $this->term->id,
            'day_of_week' => 'Tuesday',
            'start_time' => '07:30',
            'end_time' => '08:20',
            'status' => 'active',
        ]);

        SchoolCalendarEvent::create([
            'date' => '2026-08-03',
            'event_type' => 'holiday',
            'grade_level' => null,
            'title' => 'Campus Holiday',
            'created_by' => $this->manager->id,
        ]);
    }

    public function test_next_school_day_skips_consecutive_campus_closures(): void
    {
        SchoolCalendarEvent::create([
            'date' => '2026-08-04',
            'event_type' => 'suspension',
            'grade_level' => null,
            'title' => 'Classes Suspended',
            'created_by' => $this->manager->id,
        ]);

        $this->assertSame(
            '2026-08-05',
            app(SchoolCalendarService::class)->nextSchoolDayAfter('2026-08-03'),
        );
    }

    public function test_draft_and_publish_freeze_a_shifted_copy_without_changing_weekly_schedule(): void
    {
        $this->actingAs($this->manager)->post(route('faculty-loading.schedules.day-adjustments.store'), [
            'academic_term_id' => $this->term->id,
            'postponed_from_date' => '2026-08-03',
            'effective_date' => '2026-08-04',
            'reason' => 'Monday campus holiday',
        ])->assertRedirect();

        $adjustment = ClassScheduleDayAdjustment::firstOrFail();
        $this->assertSame('draft', $adjustment->status);
        $this->assertSame('07:30:00', $this->tuesdayClass->fresh()->start_time);

        $this->actingAs($this->manager)
            ->post(route('faculty-loading.schedules.day-adjustments.publish', $adjustment))
            ->assertRedirect();

        $adjustment->refresh();
        $entry = collect($adjustment->schedule_snapshot['grades'])
            ->firstWhere('grade_level', 7)['sections'][0]['entries'][0];

        $this->assertSame('published', $adjustment->status);
        $this->assertSame('08:00', $entry['start_time']);
        $this->assertSame('08:50', $entry['end_time']);
        $this->assertSame('07:30:00', $this->tuesdayClass->fresh()->start_time);

        $this->tuesdayClass->update(['start_time' => '09:00', 'end_time' => '09:50']);

        $this->actingAs($this->manager)
            ->get(route('faculty-loading.schedules.day-adjustments.print', $adjustment))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('FacultyLoading/Schedules/PrintAdjustedDay')
                ->has('snapshot.grades', 6)
                ->where('snapshot.grades.0.sections.0.entries.0.start_time', '08:00')
            );

        $this->actingAs($this->manager)
            ->post(route('faculty-loading.schedules.day-adjustments.cancel', $adjustment))
            ->assertRedirect();

        $adjustment->refresh();
        $this->assertSame('cancelled', $adjustment->status);
        $this->assertSame($this->manager->id, $adjustment->cancelled_by);
        $this->assertNotNull($adjustment->cancelled_at);
    }

    public function test_date_without_adjustment_leaves_regular_schedule_unchanged(): void
    {
        $this->assertDatabaseCount('class_schedule_day_adjustments', 0);
        $this->assertSame('Tuesday', $this->tuesdayClass->fresh()->day_of_week);
        $this->assertSame('07:30:00', $this->tuesdayClass->fresh()->start_time);
    }
}
