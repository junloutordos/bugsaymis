<?php

namespace Tests\Feature\FacultyLoading;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\BellScheduleSetting;
use App\Models\FacultyLoading\Classroom;
use App\Models\FacultyLoading\ClassSchedule;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\SectionConsultationOverride;
use App\Models\FacultyLoading\Subject;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\FacultyLoading\SchedulingConstants;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleBlockScopeTest extends TestCase
{
    use RefreshDatabase;

    private User $editor;

    private User $faculty;

    private SchoolYear $schoolYear;

    private AcademicTerm $term;

    private Subject $subject;

    private Classroom $room;

    private Section $calcium;

    private Section $lithium;

    protected function setUp(): void
    {
        parent::setUp();
        SchedulingConstants::flushOverrideCache();

        $permission = Permission::firstOrCreate(
            ['name' => 'faculty_loading.manage'],
            ['module' => 'FacultyLoading', 'description' => 'Manage faculty loading'],
        );
        $cid = Role::firstOrCreate(['name' => 'CID Chief']);
        $cid->permissions()->syncWithoutDetaching($permission->id);

        $this->editor = User::factory()->create(['email_verified_at' => now()]);
        $this->editor->roles()->attach($cid->id);
        $this->faculty = User::factory()->create(['email_verified_at' => now()]);
        $this->schoolYear = SchoolYear::create([
            'name' => '2026-2027',
            'start_date' => '2026-08-01',
            'end_date' => '2027-06-30',
            'is_current' => true,
            'status' => 'active',
        ]);
        $this->term = AcademicTerm::create([
            'school_year_id' => $this->schoolYear->id,
            'name' => '1st Semester',
            'term_type' => '1st_semester',
            'start_date' => '2026-08-01',
            'end_date' => '2026-12-31',
            'is_current' => true,
        ]);
        $this->subject = Subject::create([
            'school_year_id' => $this->schoolYear->id,
            'code' => 'MATH9',
            'name' => 'Mathematics 9',
            'credit_units' => 3,
            'lecture_hours' => 3,
            'load_units' => 3,
            'subject_type' => 'lecture',
            'grade_level' => 9,
            'sessions_per_week' => 5,
            'minutes_per_session' => 50,
            'is_active' => true,
        ]);
        $this->room = Classroom::create([
            'school_year_id' => $this->schoolYear->id,
            'name' => 'Room 9',
            'code' => 'R9',
            'classroom_type' => 'lecture',
            'capacity' => 40,
            'is_available' => true,
        ]);
        $this->calcium = $this->section('Calcium');
        $this->lithium = $this->section('Lithium');
    }

    public function test_vacant_theoretical_period_can_become_grade_consultation(): void
    {
        $this->actingAs($this->editor)
            ->patchJson(route('faculty-loading.bell-schedule.consultation.grade', [
                'grade' => 9,
                'day' => 'Tuesday',
            ]), [
                'start' => '14:00',
                'end' => '14:30',
                'academic_term_id' => $this->term->id,
            ])
            ->assertOk();

        $consultation = collect(SchedulingConstants::getBlockedSlots(9, 'Tuesday'))->firstWhere('type', 'CONSULT');
        $this->assertSame('14:00', $consultation['start']);
        $this->assertSame('14:30', $consultation['end']);
    }

    public function test_occupying_schedule_blocks_grade_scope_but_adjacent_schedule_does_not(): void
    {
        $this->schedule($this->calcium, 'Tuesday', '14:00', '14:30', 'tentative');

        $this->actingAs($this->editor)
            ->patchJson(route('faculty-loading.bell-schedule.consultation.grade', [
                'grade' => 9,
                'day' => 'Tuesday',
            ]), [
                'start' => '14:10',
                'end' => '14:40',
                'academic_term_id' => $this->term->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('time');

        $this->actingAs($this->editor)
            ->patchJson(route('faculty-loading.bell-schedule.consultation.grade', [
                'grade' => 9,
                'day' => 'Tuesday',
            ]), [
                'start' => '14:30',
                'end' => '15:00',
                'academic_term_id' => $this->term->id,
            ])
            ->assertOk();
    }

    public function test_section_scope_is_independent(): void
    {
        $this->schedule($this->lithium, 'Thursday', '14:00', '14:30');

        $this->actingAs($this->editor)
            ->patchJson(route('faculty-loading.bell-schedule.consultation.section', [
                'section' => $this->calcium,
                'day' => 'Thursday',
            ]), [
                'start' => '14:00',
                'end' => '14:30',
                'academic_term_id' => $this->term->id,
            ])
            ->assertOk();

        $this->actingAs($this->editor)
            ->patchJson(route('faculty-loading.bell-schedule.consultation.section', [
                'section' => $this->lithium,
                'day' => 'Thursday',
            ]), [
                'start' => '14:00',
                'end' => '14:30',
                'academic_term_id' => $this->term->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('time');
    }

    public function test_grade_scope_preserves_sections_with_narrower_override(): void
    {
        SectionConsultationOverride::create([
            'section_id' => $this->calcium->id,
            'day_of_week' => 'Tuesday',
            'start_time' => '13:00',
            'end_time' => '13:30',
            'updated_by' => $this->editor->id,
        ]);
        $this->schedule($this->calcium, 'Tuesday', '14:00', '14:30');

        $this->actingAs($this->editor)
            ->patchJson(route('faculty-loading.bell-schedule.consultation.grade', [
                'grade' => 9,
                'day' => 'Tuesday',
            ]), [
                'start' => '14:00',
                'end' => '14:30',
                'academic_term_id' => $this->term->id,
            ])
            ->assertOk();

        $this->assertSame(
            ['start' => '13:00', 'end' => '13:30'],
            $this->calcium->fresh()->consultationOverrideFor('Tuesday'),
        );
        $this->assertSame(
            ['start' => '14:00', 'end' => '14:30'],
            SchedulingConstants::getConsultationWindow(9, 'Tuesday'),
        );
    }

    public function test_consultation_precedence_is_section_then_grade_then_campus(): void
    {
        BellScheduleSetting::create([
            'setting_key' => 'CONSULTATION_CAMPUS_DAY',
            'value' => ['Tuesday' => ['start' => '15:00', 'end' => '15:30']],
            'updated_by' => $this->editor->id,
        ]);
        BellScheduleSetting::create([
            'setting_key' => 'CONSULTATION_BY_GRADE_DAY',
            'value' => [9 => ['Tuesday' => ['start' => '14:00', 'end' => '14:30']]],
            'updated_by' => $this->editor->id,
        ]);
        SchedulingConstants::flushOverrideCache();

        $this->assertSame(
            ['start' => '14:00', 'end' => '14:30'],
            SchedulingConstants::getConsultationWindow(9, 'Tuesday'),
        );
        $this->assertSame(
            ['start' => '13:00', 'end' => '13:30'],
            SchedulingConstants::getConsultationWindow(9, 'Tuesday', ['start' => '13:00', 'end' => '13:30']),
        );
        $this->assertSame(
            ['start' => '15:00', 'end' => '15:30'],
            SchedulingConstants::getConsultationWindow(10, 'Tuesday'),
        );
    }

    private function section(string $name): Section
    {
        return Section::create([
            'levelid' => 9,
            'sectionname' => $name,
            'syid' => $this->schoolYear->id,
            'school_year_id' => $this->schoolYear->id,
            'is_active' => true,
        ]);
    }

    private function schedule(Section $section, string $day, string $start, string $end, string $status = 'active'): ClassSchedule
    {
        return ClassSchedule::create([
            'user_id' => $this->faculty->id,
            'subject_id' => $this->subject->id,
            'section_id' => $section->id,
            'classroom_id' => $this->room->id,
            'school_year_id' => $this->schoolYear->id,
            'academic_term_id' => $this->term->id,
            'day_of_week' => $day,
            'start_time' => $start,
            'end_time' => $end,
            'status' => $status,
        ]);
    }
}
