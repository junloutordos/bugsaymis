<?php

namespace Tests\Feature\FacultyLoading;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\Classroom;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\FacultyLoading\ScheduleValidationService;
use App\Services\FacultyLoading\SchedulingConstants;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Per-section, per-day (Mon-Fri) lunch override — generalized from the
 * Wednesday-only version shipped 2026-07-21 (commit e55f2f6d). Covers:
 *   1. Section model: LUNCH_OVERRIDE_COLUMNS map + lunchOverrideFor()
 *   2. Mass-assignment round-trip for all 8 new columns (the Wednesday
 *      feature's own regression was exactly a missing $fillable entry)
 *   3. SchedulingConstants::getBlockedSlots() applying an override on any day
 *   4. ScheduleValidationService break-time check respecting the correct
 *      day-specific override and leaving other days/sections untouched
 *   5. The PATCH .../sections/{section}/lunch/{day} HTTP endpoint
 */
class SectionLunchOverrideTest extends TestCase
{
    use RefreshDatabase;

    // ── Permission helpers (mirrors FacultyLoadingHttpTest) ─────────────────

    private function userWith(array|string $permissions): User
    {
        $permissions = (array) $permissions;
        $role = Role::create(['name' => 'TestRole_'.uniqid()]);
        foreach ($permissions as $name) {
            $perm = Permission::firstOrCreate(
                ['name' => $name],
                ['module' => 'FacultyLoading', 'description' => $name]
            );
            $role->permissions()->attach($perm->id);
        }
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function manageUser(): User
    {
        return $this->userWith(['faculty_loading.view', 'faculty_loading.manage']);
    }

    private function viewOnlyUser(): User
    {
        return $this->userWith('faculty_loading.view_own');
    }

    // ── Fixtures ──────────────────────────────────────────────────────────────

    private function makeSection(array $overrides = []): Section
    {
        $sy = SchoolYear::query()->latest('id')->first() ?? SchoolYear::create([
            'name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30',
            'is_current' => true, 'status' => 'active',
        ]);

        return Section::create(array_merge([
            'levelid' => 7,
            'sectionname' => 'Turquoise',
            'syid' => $sy->id,
            'school_year_id' => $sy->id,
            'is_active' => true,
        ], $overrides));
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 1-2. Section model — map, helper, $fillable round-trip
    // ═══════════════════════════════════════════════════════════════════════

    public function test_lunch_override_columns_map_covers_all_five_weekdays(): void
    {
        $this->assertSame([
            'Monday'    => ['lunch_start_mon', 'lunch_end_mon'],
            'Tuesday'   => ['lunch_start_tue', 'lunch_end_tue'],
            'Wednesday' => ['lunch_start_wed', 'lunch_end_wed'],
            'Thursday'  => ['lunch_start_thu', 'lunch_end_thu'],
            'Friday'    => ['lunch_start_fri', 'lunch_end_fri'],
        ], Section::LUNCH_OVERRIDE_COLUMNS);
    }

    public function test_lunch_override_for_returns_null_when_unset(): void
    {
        $section = $this->makeSection();

        foreach (SchedulingConstants::DAYS as $day) {
            $this->assertNull($section->lunchOverrideFor($day));
        }
    }

    public function test_lunch_override_for_returns_the_correct_column_pair_per_day(): void
    {
        $section = $this->makeSection([
            'lunch_start_mon' => '11:00:00', 'lunch_end_mon' => '11:45:00',
            'lunch_start_fri' => '13:00:00', 'lunch_end_fri' => '13:45:00',
        ]);

        $this->assertSame(['start' => '11:00:00', 'end' => '11:45:00'], $section->lunchOverrideFor('Monday'));
        $this->assertSame(['start' => '13:00:00', 'end' => '13:45:00'], $section->lunchOverrideFor('Friday'));
        $this->assertNull($section->lunchOverrideFor('Tuesday'));
        $this->assertNull($section->lunchOverrideFor('Wednesday'));
        $this->assertNull($section->lunchOverrideFor('Thursday'));
    }

    /**
     * Regression test for the exact bug class that hit the Wednesday-only
     * version: lunch_start_wed/lunch_end_wed were missing from $fillable, so
     * mass-assignment silently dropped them with zero error. Every one of the
     * 8 new columns is exercised here, refetched fresh from the DB.
     */
    public function test_all_eight_new_lunch_columns_are_mass_assignable(): void
    {
        $section = $this->makeSection();
        $section->update([
            'lunch_start_mon' => '11:00', 'lunch_end_mon' => '11:45',
            'lunch_start_tue' => '11:05', 'lunch_end_tue' => '11:50',
            'lunch_start_thu' => '11:15', 'lunch_end_thu' => '12:00',
            'lunch_start_fri' => '11:20', 'lunch_end_fri' => '12:05',
        ]);

        $fresh = Section::findOrFail($section->id);

        $this->assertSame('11:00:00', $fresh->lunch_start_mon);
        $this->assertSame('11:45:00', $fresh->lunch_end_mon);
        $this->assertSame('11:05:00', $fresh->lunch_start_tue);
        $this->assertSame('11:50:00', $fresh->lunch_end_tue);
        $this->assertSame('11:15:00', $fresh->lunch_start_thu);
        $this->assertSame('12:00:00', $fresh->lunch_end_thu);
        $this->assertSame('11:20:00', $fresh->lunch_start_fri);
        $this->assertSame('12:05:00', $fresh->lunch_end_fri);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 3. SchedulingConstants — pure function, no DB
    // ═══════════════════════════════════════════════════════════════════════

    public function test_get_blocked_slots_applies_override_on_monday(): void
    {
        $override = ['start' => '11:00', 'end' => '11:45'];
        $blocked  = SchedulingConstants::getBlockedSlots(7, 'Monday', $override);

        $lunch = collect($blocked)->firstWhere('type', 'LUNCH');
        $this->assertNotNull($lunch);
        $this->assertSame('11:00', $lunch['start']);
        $this->assertSame('11:45', $lunch['end']);
    }

    public function test_get_blocked_slots_applies_override_on_thursday(): void
    {
        $override = ['start' => '12:30', 'end' => '13:15'];
        $blocked  = SchedulingConstants::getBlockedSlots(9, 'Thursday', $override);

        $lunch = collect($blocked)->firstWhere('type', 'LUNCH');
        $this->assertNotNull($lunch);
        $this->assertSame('12:30', $lunch['start']);
        $this->assertSame('13:15', $lunch['end']);
    }

    public function test_get_blocked_slots_unaffected_when_override_is_null(): void
    {
        $withoutOverride = SchedulingConstants::getBlockedSlots(7, 'Monday');
        $withNullOverride = SchedulingConstants::getBlockedSlots(7, 'Monday', null);

        $this->assertSame($withoutOverride, $withNullOverride);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 4. ScheduleValidationService — day-specific break-time enforcement
    // ═══════════════════════════════════════════════════════════════════════

    private ?ScheduleValidationService $svc = null;
    private ?SchoolYear $sy = null;
    private ?AcademicTerm $term = null;
    private ?User $faculty = null;
    private ?Subject $subject = null;
    private ?Classroom $room = null;

    private function bootValidationFixtures(): void
    {
        $this->svc     = app(ScheduleValidationService::class);
        $this->sy      = SchoolYear::create(['name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30', 'is_current' => true, 'status' => 'active']);
        $this->term    = AcademicTerm::create(['school_year_id' => $this->sy->id, 'name' => '1st Semester', 'term_type' => '1st_semester', 'start_date' => '2025-08-01', 'end_date' => '2025-12-31', 'is_current' => true]);
        $this->faculty = User::factory()->create(['email_verified_at' => now()]);
        $this->subject = Subject::create(['school_year_id' => $this->sy->id, 'code' => 'MATH7', 'name' => 'Mathematics 7', 'credit_units' => 4, 'lecture_hours' => 4, 'load_units' => 4, 'subject_type' => 'lecture', 'grade_level' => 7, 'sessions_per_week' => 4, 'minutes_per_session' => 60, 'is_active' => true]);
        $this->room    = Classroom::create(['school_year_id' => $this->sy->id, 'name' => 'Room 101', 'code' => 'R101', 'classroom_type' => 'lecture', 'capacity' => 45, 'is_available' => true]);
    }

    private function scheduleData(Section $section, array $overrides = []): array
    {
        return array_merge([
            'faculty_id'       => $this->faculty->id,
            'subject_id'       => $this->subject->id,
            'section_id'       => $section->id,
            'classroom_id'     => $this->room->id,
            'school_year_id'   => $this->sy->id,
            'academic_term_id' => $this->term->id,
            'day_of_week'      => 'Monday',
            'start_time'       => '08:00:00',
            'end_time'         => '09:00:00',
        ], $overrides);
    }

    public function test_break_time_check_blocks_the_override_window_on_its_own_day(): void
    {
        $this->bootValidationFixtures();
        $section = $this->makeSection(['lunch_start_mon' => '11:00:00', 'lunch_end_mon' => '11:45:00']);

        $result = $this->svc->validate($this->scheduleData($section, [
            'day_of_week' => 'Monday', 'start_time' => '11:15:00', 'end_time' => '12:00:00',
        ]));

        $this->assertFalse($result['valid']);
        $breakErrors = array_filter($result['errors'], fn ($e) => str_contains($e, 'lunch break'));
        $this->assertNotEmpty($breakErrors);
    }

    public function test_break_time_check_frees_up_the_sections_regular_lunch_slot_once_overridden(): void
    {
        $this->bootValidationFixtures();
        // Regular lunch 12:00-13:00; Monday override moves it to 11:00-11:45.
        $section = $this->makeSection([
            'lunch_start' => '12:00:00', 'lunch_end' => '13:00:00',
            'lunch_start_mon' => '11:00:00', 'lunch_end_mon' => '11:45:00',
        ]);

        // The section's OLD lunch window is no longer blocked on Monday.
        $result = $this->svc->validate($this->scheduleData($section, [
            'day_of_week' => 'Monday', 'start_time' => '12:00:00', 'end_time' => '13:00:00',
        ]));

        $breakErrors = array_filter($result['errors'], fn ($e) => str_contains($e, 'lunch break'));
        $this->assertEmpty($breakErrors);
    }

    public function test_monday_lunch_override_does_not_affect_the_same_sections_other_days(): void
    {
        $this->bootValidationFixtures();
        $section = $this->makeSection([
            'lunch_start_mon' => '11:00:00', 'lunch_end_mon' => '11:45:00',
        ]);

        // Tuesday has no override of its own, so it falls back to the
        // grade-level default lunch window (G7_TueFri, 10:20-11:20) —
        // unaffected by the Monday-only override.
        $result = $this->svc->validate($this->scheduleData($section, [
            'day_of_week' => 'Tuesday', 'start_time' => '10:20:00', 'end_time' => '11:20:00',
        ]));

        $this->assertFalse($result['valid']);
        $breakErrors = array_filter($result['errors'], fn ($e) => str_contains($e, 'lunch break'));
        $this->assertNotEmpty($breakErrors);
    }

    /**
     * Regression test for the reported bug: a section with no per-day lunch
     * override for a given weekday must fall back to the same grade-level
     * default the calendar grid renders — never to the legacy generic
     * lunch_start/lunch_end columns, which predate per-day overrides, can
     * hold stale unrelated values, and are never shown on the calendar.
     */
    public function test_break_time_check_ignores_stale_generic_lunch_columns_when_no_day_override_exists(): void
    {
        $this->bootValidationFixtures();
        $section = $this->makeSection([
            'lunch_start' => '10:00:00', 'lunch_end' => '10:50:00',
        ]);

        // Grade 7 Monday has no per-day override, so validation must use the
        // grade default (11:40-12:40), leaving the stale 10:00-10:50 window
        // unenforced.
        $result = $this->svc->validate($this->scheduleData($section, [
            'day_of_week' => 'Monday', 'start_time' => '10:00:00', 'end_time' => '10:50:00',
        ]));

        $breakErrors = array_filter($result['errors'], fn ($e) => str_contains($e, 'lunch break'));
        $this->assertEmpty($breakErrors);
    }

    public function test_lunch_override_does_not_affect_a_sibling_section(): void
    {
        $this->bootValidationFixtures();
        $overridden = $this->makeSection([
            'sectionname' => 'Turquoise',
            'lunch_start' => '12:00:00', 'lunch_end' => '13:00:00',
            'lunch_start_mon' => '11:00:00', 'lunch_end_mon' => '11:45:00',
        ]);
        $sibling = $this->makeSection([
            'sectionname' => 'Opal',
            'lunch_start' => '12:00:00', 'lunch_end' => '13:00:00',
        ]);

        // Sibling section keeps its regular 12:00-13:00 lunch on Monday.
        $result = $this->svc->validate($this->scheduleData($sibling, [
            'day_of_week' => 'Monday', 'start_time' => '12:00:00', 'end_time' => '13:00:00',
        ]));

        $this->assertFalse($result['valid']);
        $breakErrors = array_filter($result['errors'], fn ($e) => str_contains($e, 'lunch break'));
        $this->assertNotEmpty($breakErrors);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 5. HTTP endpoint — PATCH .../sections/{section}/lunch/{day}
    // ═══════════════════════════════════════════════════════════════════════

    public function test_manage_user_can_set_lunch_override_for_every_weekday(): void
    {
        $suffixes = ['Monday' => 'mon', 'Tuesday' => 'tue', 'Wednesday' => 'wed', 'Thursday' => 'thu', 'Friday' => 'fri'];

        foreach ($suffixes as $day => $suffix) {
            $section = $this->makeSection(['sectionname' => "Sec-{$suffix}"]);

            $this->actingAs($this->manageUser())
                ->patchJson(route('faculty-loading.sections.lunch', ['section' => $section->id, 'day' => $day]), [
                    "lunch_start_{$suffix}" => '11:30',
                    "lunch_end_{$suffix}"   => '12:15',
                ])
                ->assertOk();

            $this->assertDatabaseHas('sections', [
                'id' => $section->id,
                "lunch_start_{$suffix}" => '11:30:00',
                "lunch_end_{$suffix}"   => '12:15:00',
            ]);
        }
    }

    public function test_manage_user_can_clear_an_override(): void
    {
        $section = $this->makeSection(['lunch_start_wed' => '11:00:00', 'lunch_end_wed' => '11:45:00']);

        $this->actingAs($this->manageUser())
            ->patchJson(route('faculty-loading.sections.lunch', ['section' => $section->id, 'day' => 'Wednesday']), [
                'lunch_start_wed' => '',
                'lunch_end_wed'   => '',
            ])
            ->assertOk();

        $this->assertDatabaseHas('sections', [
            'id' => $section->id,
            'lunch_start_wed' => null,
            'lunch_end_wed'   => null,
        ]);
    }

    public function test_both_or_neither_validation_on_the_endpoint(): void
    {
        $section = $this->makeSection();

        $this->actingAs($this->manageUser())
            ->patchJson(route('faculty-loading.sections.lunch', ['section' => $section->id, 'day' => 'Friday']), [
                'lunch_start_fri' => '11:30',
                'lunch_end_fri'   => '',
            ])
            ->assertStatus(422);
    }

    public function test_invalid_day_param_is_rejected_by_the_route(): void
    {
        // Built as a raw path (not via the route() helper) — the helper
        // itself throws when a parameter fails the route's where() pattern,
        // so this is the only way to actually exercise the routing-level
        // rejection instead of a PHP-level exception.
        $section = $this->makeSection();

        $this->actingAs($this->manageUser())
            ->patchJson("/faculty-loading/sections/{$section->id}/lunch/Sunday", [
                'lunch_start_mon' => '11:30',
                'lunch_end_mon'   => '12:15',
            ])
            ->assertStatus(404);
    }

    public function test_unauthorized_user_cannot_set_a_lunch_override(): void
    {
        $section = $this->makeSection();

        $this->actingAs($this->viewOnlyUser())
            ->patchJson(route('faculty-loading.sections.lunch', ['section' => $section->id, 'day' => 'Monday']), [
                'lunch_start_mon' => '11:30',
                'lunch_end_mon'   => '12:15',
            ])
            ->assertForbidden();
    }
}
