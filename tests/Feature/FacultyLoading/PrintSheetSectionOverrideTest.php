<?php

namespace Tests\Feature\FacultyLoading;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\Classroom;
use App\Models\FacultyLoading\ClassSchedule;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fixes a real bug found 2026-07-22: printing a section's schedule
 * (ClassScheduleController::printSection/printBatchSchedules) ignored that
 * section's own Lunch/Recess/White Space/Wellness overrides entirely — the
 * interactive calendar correctly showed a section's overridden Lunch time,
 * but the print sheet silently fell back to the grade default, because
 * buildPrintSheet() never had access to the Section model to read its
 * overrides from (only a bare grade-level int).
 */
class PrintSheetSectionOverrideTest extends TestCase
{
    use RefreshDatabase;

    private function manageUser(): User
    {
        $role = Role::create(['name' => 'TestRole_'.uniqid()]);
        foreach (['faculty_loading.view', 'faculty_loading.manage'] as $name) {
            $perm = Permission::firstOrCreate(['name' => $name], ['module' => 'FacultyLoading', 'description' => $name]);
            $role->permissions()->attach($perm->id);
        }
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function makeSection(array $overrides = []): Section
    {
        $sy = SchoolYear::query()->latest('id')->first() ?? SchoolYear::create([
            'name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30',
            'is_current' => true, 'status' => 'active',
        ]);
        if (! AcademicTerm::where('is_current', true)->exists()) {
            AcademicTerm::create([
                'school_year_id' => $sy->id, 'name' => '1st Semester', 'term_type' => '1st_semester',
                'start_date' => '2025-08-01', 'end_date' => '2025-12-31', 'is_current' => true,
            ]);
        }

        return Section::create(array_merge([
            'levelid' => 11,
            'sectionname' => 'Mars',
            'syid' => $sy->id,
            'school_year_id' => $sy->id,
            'is_active' => true,
        ], $overrides));
    }

    public function test_print_section_applies_its_own_lunch_override(): void
    {
        // Mirrors the real production case: Grade 11's default Thursday lunch
        // is 12:00-13:00, but this section moved it to 11:10-12:10.
        $section = $this->makeSection([
            'lunch_start_thu' => '11:10:00', 'lunch_end_thu' => '12:10:00',
        ]);

        $response = $this->actingAs($this->manageUser())
            ->get(route('faculty-loading.schedules.sections.print', ['section' => $section->id]))
            ->assertOk();

        $response->assertInertia(fn ($page) => $page
            ->component('FacultyLoading/Schedules/Print')
            ->where('dayConfigs.Thursday.blocked', fn ($blocked) => collect($blocked)->contains(
                fn ($b) => $b['type'] === 'LUNCH' && $b['start'] === '11:10' && $b['end'] === '12:10'
            ))
        );
    }

    public function test_print_section_falls_back_to_grade_default_when_unset(): void
    {
        $section = $this->makeSection();

        $response = $this->actingAs($this->manageUser())
            ->get(route('faculty-loading.schedules.sections.print', ['section' => $section->id]))
            ->assertOk();

        $response->assertInertia(fn ($page) => $page
            ->where('dayConfigs.Thursday.blocked', fn ($blocked) => collect($blocked)->contains(
                fn ($b) => $b['type'] === 'LUNCH' && $b['start'] === '12:00' && $b['end'] === '13:00'
            ))
        );
    }

    /** Batch print only includes sections with at least one occupying schedule row. */
    private function seedOccupyingSchedule(Section $section): void
    {
        $term = AcademicTerm::where('is_current', true)->first();
        $faculty = User::factory()->create(['email_verified_at' => now()]);
        $subject = Subject::create([
            'school_year_id' => $section->school_year_id, 'code' => 'SUBJ-'.$section->id, 'name' => 'Test Subject',
            'credit_units' => 4, 'lecture_hours' => 4, 'load_units' => 4, 'subject_type' => 'lecture',
            'grade_level' => $section->levelid, 'sessions_per_week' => 4, 'minutes_per_session' => 60, 'is_active' => true,
        ]);
        $room = Classroom::create([
            'school_year_id' => $section->school_year_id, 'name' => 'Room-'.$section->id, 'code' => 'R'.$section->id,
            'classroom_type' => 'lecture', 'capacity' => 45, 'is_available' => true,
        ]);
        ClassSchedule::create([
            'user_id' => $faculty->id, 'subject_id' => $subject->id, 'section_id' => $section->id,
            'classroom_id' => $room->id, 'school_year_id' => $section->school_year_id, 'academic_term_id' => $term->id,
            'day_of_week' => 'Monday', 'start_time' => '08:00:00', 'end_time' => '09:00:00', 'status' => 'active',
        ]);
    }

    public function test_print_batch_also_applies_the_sections_own_lunch_override(): void
    {
        $overridden = $this->makeSection([
            'sectionname' => 'Mars',
            'lunch_start_thu' => '11:10:00', 'lunch_end_thu' => '12:10:00',
        ]);
        $sibling = $this->makeSection(['sectionname' => 'Venus']);
        $this->seedOccupyingSchedule($overridden);
        $this->seedOccupyingSchedule($sibling);

        $response = $this->actingAs($this->manageUser())
            ->get(route('faculty-loading.schedules.print-batch', ['type' => 'section']))
            ->assertOk();

        $response->assertInertia(function ($page) use ($overridden, $sibling) {
            $sheets = $page->toArray()['props']['sheets'];
            $marsSheet = collect($sheets)->firstWhere('owner.id', $overridden->id);
            $venusSheet = collect($sheets)->firstWhere('owner.id', $sibling->id);

            $this->assertNotNull($marsSheet, 'Mars sheet should be present in the batch');
            $this->assertNotNull($venusSheet, 'Venus sheet should be present in the batch');

            $this->assertTrue(collect($marsSheet['dayConfigs']['Thursday']['blocked'])->contains(
                fn ($b) => $b['type'] === 'LUNCH' && $b['start'] === '11:10' && $b['end'] === '12:10'
            ), 'Mars should show its own overridden Thursday lunch');

            $this->assertTrue(collect($venusSheet['dayConfigs']['Thursday']['blocked'])->contains(
                fn ($b) => $b['type'] === 'LUNCH' && $b['start'] === '12:00' && $b['end'] === '13:00'
            ), 'Venus (no override) should show the grade default Thursday lunch');
        });
    }
}
