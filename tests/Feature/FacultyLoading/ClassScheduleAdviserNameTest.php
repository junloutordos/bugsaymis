<?php

namespace Tests\Feature\FacultyLoading;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\Classroom;
use App\Models\FacultyLoading\ClassSchedule;
use App\Models\FacultyLoading\Designation;
use App\Models\FacultyLoading\DesignationCategory;
use App\Models\FacultyLoading\FacultyLoad;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fixes a real bug reported 2026-08-03: the Class Schedule calendar and
 * print sheets showed the grade-wide Homeroom COORDINATOR's name instead of
 * the section's own HR_ADV/HR_ACAD ADVISER's name whenever both existed for
 * the same section.
 *
 * AdvisoryScheduleScopeService::adviserNamesBySection() deliberately lets a
 * grade-wide COORD-* coordinator win over the section's own adviser — that
 * is correct for the WAT print signatory (captioned "Homeroom Coordinator",
 * see WeeklyAssessmentTrackerTest::test_print_resolves_grade_wide_coordinator_name_over_the_section_adviser).
 * The Class Schedule calendar/print, however, labels this plain "Adviser"
 * and must show the section-specific HR_ADV/HR_ACAD designee, so it now
 * uses the section-scoped sectionAdviserNamesBySection() instead.
 */
class ClassScheduleAdviserNameTest extends TestCase
{
    use RefreshDatabase;

    private SchoolYear $sy;

    private AcademicTerm $term;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sy = SchoolYear::create([
            'name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30',
            'is_current' => true, 'status' => 'active',
        ]);
        $this->term = AcademicTerm::create([
            'school_year_id' => $this->sy->id, 'name' => '1st Semester', 'term_type' => '1st_semester',
            'start_date' => '2025-08-01', 'end_date' => '2025-12-31', 'is_current' => true,
        ]);
    }

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
        return Section::create(array_merge([
            'levelid' => 8, 'sectionname' => 'Emerald', 'syid' => $this->sy->id,
            'school_year_id' => $this->sy->id, 'is_active' => true,
        ], $overrides));
    }

    /** Designation-based adviser/coordinator — mirrors WeeklyAssessmentTrackerTest::assignCoordinator(). */
    private function assignDesignation(User $user, string $categoryCode, string $code, string $name, ?Section $section = null): LoadAssignment
    {
        $category = DesignationCategory::firstOrCreate(
            ['code' => $categoryCode],
            ['name' => $categoryCode, 'is_active' => true],
        );
        $designation = Designation::create([
            'designation_category_id' => $category->id, 'section_id' => $section?->id,
            'code' => $code, 'name' => $name, 'load_units' => 3,
            'assignment_type' => 'admin', 'is_active' => true,
        ]);
        $facultyLoad = FacultyLoad::create([
            'user_id' => $user->id, 'school_year_id' => $this->sy->id, 'academic_term_id' => $this->term->id,
        ]);

        return LoadAssignment::create([
            'faculty_load_id' => $facultyLoad->id, 'user_id' => $user->id,
            'school_year_id' => $this->sy->id, 'academic_term_id' => $this->term->id,
            'assignment_type' => 'admin', 'section_id' => $section?->id, 'load_units' => 3,
            'description' => $name, 'designation_id' => $designation->id,
        ]);
    }

    private function seedOccupyingSchedule(Section $section): void
    {
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
            'classroom_id' => $room->id, 'school_year_id' => $section->school_year_id, 'academic_term_id' => $this->term->id,
            'day_of_week' => 'Monday', 'start_time' => '08:00:00', 'end_time' => '09:00:00', 'status' => 'active',
        ]);
    }

    public function test_calendar_shows_section_adviser_not_grade_wide_coordinator(): void
    {
        $adviser = User::factory()->create(['name' => 'Adviser Person']);
        $coordinator = User::factory()->create(['name' => 'Coordinator Person']);
        $section = $this->makeSection();

        $this->assignDesignation($adviser, 'HR_ADV', 'HRA-G8-EMERALD', 'HR Adviser — G8 Emerald', $section);
        // Grade-wide coordinator covering G7 & G8 — no section_id.
        $this->assignDesignation($coordinator, 'HR_ADV', 'COORD-HRG7&8', 'HR Coordinator (G7-8)');

        $response = $this->actingAs($this->manageUser())
            ->get(route('faculty-loading.schedules.index', ['term_id' => $this->term->id]))
            ->assertOk();

        $response->assertInertia(function ($page) use ($section) {
            $sections = $page->toArray()['props']['sections'];
            $match = collect($sections)->firstWhere('id', $section->id);
            $this->assertNotNull($match, 'section should be present in the sections prop');
            $this->assertSame('Adviser Person', $match['adviser_name']);
        });
    }

    public function test_print_section_shows_section_adviser_not_grade_wide_coordinator(): void
    {
        $adviser = User::factory()->create(['name' => 'Adviser Person']);
        $coordinator = User::factory()->create(['name' => 'Coordinator Person']);
        $section = $this->makeSection();

        $this->assignDesignation($adviser, 'HR_ADV', 'HRA-G8-EMERALD', 'HR Adviser — G8 Emerald', $section);
        $this->assignDesignation($coordinator, 'HR_ADV', 'COORD-HRG7&8', 'HR Coordinator (G7-8)');

        $response = $this->actingAs($this->manageUser())
            ->get(route('faculty-loading.schedules.sections.print', ['section' => $section->id, 'term_id' => $this->term->id]))
            ->assertOk();

        $response->assertInertia(fn ($page) => $page
            ->component('FacultyLoading/Schedules/Print')
            ->where('owner.adviser_name', 'Adviser Person')
        );
    }

    public function test_print_batch_shows_section_adviser_not_grade_wide_coordinator(): void
    {
        $adviser = User::factory()->create(['name' => 'Adviser Person']);
        $coordinator = User::factory()->create(['name' => 'Coordinator Person']);
        $section = $this->makeSection();

        $this->assignDesignation($adviser, 'HR_ADV', 'HRA-G8-EMERALD', 'HR Adviser — G8 Emerald', $section);
        $this->assignDesignation($coordinator, 'HR_ADV', 'COORD-HRG7&8', 'HR Coordinator (G7-8)');
        $this->seedOccupyingSchedule($section);

        $response = $this->actingAs($this->manageUser())
            ->get(route('faculty-loading.schedules.print-batch', ['type' => 'section', 'term_id' => $this->term->id]))
            ->assertOk();

        $response->assertInertia(function ($page) use ($section) {
            $sheets = $page->toArray()['props']['sheets'];
            $sheet = collect($sheets)->firstWhere('owner.id', $section->id);
            $this->assertNotNull($sheet, 'section sheet should be present in the batch');
            $this->assertSame('Adviser Person', $sheet['owner']['adviser_name']);
        });
    }
}
