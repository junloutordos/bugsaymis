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
 * Per-section, per-day (Mon-Fri) recess override — mirrors the lunch override
 * feature (SectionLunchOverrideTest, commits e55f2f6d/533b1f9c). Covers:
 *   1. Section model: RECESS_OVERRIDE_COLUMNS map + recessOverrideFor()
 *   2. Mass-assignment round-trip for all 10 new columns
 *   3. SchedulingConstants::getBlockedSlots() applying an override on any day
 *   4. ScheduleValidationService break-time check respecting the correct
 *      day-specific override and leaving other days/sections untouched
 *   5. The PATCH .../sections/{section}/recess/{day} HTTP endpoint
 */
class SectionRecessOverrideTest extends TestCase
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

    public function test_recess_override_columns_map_covers_all_five_weekdays(): void
    {
        $this->assertSame([
            'Monday'    => ['recess_start_mon', 'recess_end_mon'],
            'Tuesday'   => ['recess_start_tue', 'recess_end_tue'],
            'Wednesday' => ['recess_start_wed', 'recess_end_wed'],
            'Thursday'  => ['recess_start_thu', 'recess_end_thu'],
            'Friday'    => ['recess_start_fri', 'recess_end_fri'],
        ], Section::RECESS_OVERRIDE_COLUMNS);
    }

    public function test_recess_override_for_returns_null_when_unset(): void
    {
        $section = $this->makeSection();

        foreach (SchedulingConstants::DAYS as $day) {
            $this->assertNull($section->recessOverrideFor($day));
        }
    }

    public function test_recess_override_for_returns_the_correct_column_pair_per_day(): void
    {
        $section = $this->makeSection([
            'recess_start_mon' => '09:30:00', 'recess_end_mon' => '09:50:00',
            'recess_start_fri' => '08:10:00', 'recess_end_fri' => '08:30:00',
        ]);

        $this->assertSame(['start' => '09:30:00', 'end' => '09:50:00'], $section->recessOverrideFor('Monday'));
        $this->assertSame(['start' => '08:10:00', 'end' => '08:30:00'], $section->recessOverrideFor('Friday'));
        $this->assertNull($section->recessOverrideFor('Tuesday'));
        $this->assertNull($section->recessOverrideFor('Wednesday'));
        $this->assertNull($section->recessOverrideFor('Thursday'));
    }

    /**
     * Regression test for the exact bug class that hit the Wednesday-only
     * lunch feature: new override columns missing from $fillable, so
     * mass-assignment silently drops them with zero error. Every one of the
     * 10 new columns is exercised here, refetched fresh from the DB.
     */
    public function test_all_ten_new_recess_columns_are_mass_assignable(): void
    {
        $section = $this->makeSection();
        $section->update([
            'recess_start_mon' => '09:30', 'recess_end_mon' => '09:50',
            'recess_start_tue' => '08:15', 'recess_end_tue' => '08:35',
            'recess_start_wed' => '09:35', 'recess_end_wed' => '09:55',
            'recess_start_thu' => '08:25', 'recess_end_thu' => '08:45',
            'recess_start_fri' => '08:10', 'recess_end_fri' => '08:30',
        ]);

        $fresh = Section::findOrFail($section->id);

        $this->assertSame('09:30:00', $fresh->recess_start_mon);
        $this->assertSame('09:50:00', $fresh->recess_end_mon);
        $this->assertSame('08:15:00', $fresh->recess_start_tue);
        $this->assertSame('08:35:00', $fresh->recess_end_tue);
        $this->assertSame('09:35:00', $fresh->recess_start_wed);
        $this->assertSame('09:55:00', $fresh->recess_end_wed);
        $this->assertSame('08:25:00', $fresh->recess_start_thu);
        $this->assertSame('08:45:00', $fresh->recess_end_thu);
        $this->assertSame('08:10:00', $fresh->recess_start_fri);
        $this->assertSame('08:30:00', $fresh->recess_end_fri);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 3. SchedulingConstants — pure function, no DB
    // ═══════════════════════════════════════════════════════════════════════

    public function test_get_blocked_slots_applies_recess_override_on_monday(): void
    {
        $override = ['start' => '09:30', 'end' => '09:50'];
        $blocked  = SchedulingConstants::getBlockedSlots(7, 'Monday', null, $override);

        $recess = collect($blocked)->firstWhere('type', 'RECESS');
        $this->assertNotNull($recess);
        $this->assertSame('09:30', $recess['start']);
        $this->assertSame('09:50', $recess['end']);
    }

    public function test_get_blocked_slots_applies_recess_override_on_thursday(): void
    {
        $override = ['start' => '08:25', 'end' => '08:45'];
        $blocked  = SchedulingConstants::getBlockedSlots(9, 'Thursday', null, $override);

        $recess = collect($blocked)->firstWhere('type', 'RECESS');
        $this->assertNotNull($recess);
        $this->assertSame('08:25', $recess['start']);
        $this->assertSame('08:45', $recess['end']);
    }

    public function test_get_blocked_slots_unaffected_when_recess_override_is_null(): void
    {
        $withoutOverride  = SchedulingConstants::getBlockedSlots(7, 'Monday');
        $withNullOverride = SchedulingConstants::getBlockedSlots(7, 'Monday', null, null);

        $this->assertSame($withoutOverride, $withNullOverride);
    }

    public function test_get_blocked_slots_applies_both_lunch_and_recess_overrides_together(): void
    {
        $lunchOverride  = ['start' => '11:00', 'end' => '11:45'];
        $recessOverride = ['start' => '09:30', 'end' => '09:50'];
        $blocked = SchedulingConstants::getBlockedSlots(7, 'Monday', $lunchOverride, $recessOverride);

        $lunch  = collect($blocked)->firstWhere('type', 'LUNCH');
        $recess = collect($blocked)->firstWhere('type', 'RECESS');
        $this->assertSame('11:00', $lunch['start']);
        $this->assertSame('11:45', $lunch['end']);
        $this->assertSame('09:30', $recess['start']);
        $this->assertSame('09:50', $recess['end']);
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

    public function test_break_time_check_blocks_the_recess_override_window_on_its_own_day(): void
    {
        $this->bootValidationFixtures();
        $section = $this->makeSection(['recess_start_mon' => '09:00:00', 'recess_end_mon' => '09:20:00']);

        $result = $this->svc->validate($this->scheduleData($section, [
            'day_of_week' => 'Monday', 'start_time' => '09:00:00', 'end_time' => '09:20:00',
        ]));

        $this->assertFalse($result['valid']);
        $breakErrors = array_filter($result['errors'], fn ($e) => str_contains($e, 'recess break'));
        $this->assertNotEmpty($breakErrors);
    }

    public function test_break_time_check_frees_up_the_grade_defaults_recess_slot_once_overridden(): void
    {
        $this->bootValidationFixtures();
        // Grade 7 Monday default recess is 09:40-10:00; override moves it to 09:00-09:20.
        $section = $this->makeSection([
            'recess_start_mon' => '09:00:00', 'recess_end_mon' => '09:20:00',
        ]);

        // The grade's OLD recess window is no longer blocked on Monday for this section.
        $result = $this->svc->validate($this->scheduleData($section, [
            'day_of_week' => 'Monday', 'start_time' => '09:40:00', 'end_time' => '10:00:00',
        ]));

        $breakErrors = array_filter($result['errors'], fn ($e) => str_contains($e, 'recess break'));
        $this->assertEmpty($breakErrors);
    }

    public function test_monday_recess_override_does_not_affect_the_same_sections_other_days(): void
    {
        $this->bootValidationFixtures();
        $section = $this->makeSection([
            'recess_start_mon' => '09:00:00', 'recess_end_mon' => '09:20:00',
        ]);

        // Tuesday has no override of its own, so it falls back to the
        // grade-level default recess window (G7_TueFri, 08:20-08:40) —
        // unaffected by the Monday-only override.
        $result = $this->svc->validate($this->scheduleData($section, [
            'day_of_week' => 'Tuesday', 'start_time' => '08:20:00', 'end_time' => '08:40:00',
        ]));

        $this->assertFalse($result['valid']);
        $breakErrors = array_filter($result['errors'], fn ($e) => str_contains($e, 'recess break'));
        $this->assertNotEmpty($breakErrors);
    }

    /**
     * A section with no per-day recess override for a given weekday must fall
     * back to the same grade-level default the calendar grid renders — never
     * to the legacy generic recess_start/recess_end columns, which predate
     * per-day overrides, can hold stale unrelated values, and are never shown
     * on the calendar. Mirrors the lunch fix from commit 501c9f77.
     */
    public function test_break_time_check_ignores_stale_generic_recess_columns_when_no_day_override_exists(): void
    {
        $this->bootValidationFixtures();
        $section = $this->makeSection([
            'recess_start' => '10:00:00', 'recess_end' => '10:20:00',
        ]);

        // Grade 7 Monday has no per-day override, so validation must use the
        // grade default (09:40-10:00), leaving the stale 10:00-10:20 window
        // unenforced.
        $result = $this->svc->validate($this->scheduleData($section, [
            'day_of_week' => 'Monday', 'start_time' => '10:00:00', 'end_time' => '10:20:00',
        ]));

        $breakErrors = array_filter($result['errors'], fn ($e) => str_contains($e, 'recess break'));
        $this->assertEmpty($breakErrors);
    }

    public function test_recess_override_does_not_affect_a_sibling_section(): void
    {
        $this->bootValidationFixtures();
        $overridden = $this->makeSection([
            'sectionname' => 'Turquoise',
            'recess_start_mon' => '09:00:00', 'recess_end_mon' => '09:20:00',
        ]);
        $sibling = $this->makeSection([
            'sectionname' => 'Opal',
        ]);

        // Sibling section keeps its grade-default 09:40-10:00 recess on Monday.
        $result = $this->svc->validate($this->scheduleData($sibling, [
            'day_of_week' => 'Monday', 'start_time' => '09:40:00', 'end_time' => '10:00:00',
        ]));

        $this->assertFalse($result['valid']);
        $breakErrors = array_filter($result['errors'], fn ($e) => str_contains($e, 'recess break'));
        $this->assertNotEmpty($breakErrors);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 5. HTTP endpoint — PATCH .../sections/{section}/recess/{day}
    // ═══════════════════════════════════════════════════════════════════════

    public function test_manage_user_can_set_recess_override_for_every_weekday(): void
    {
        $suffixes = ['Monday' => 'mon', 'Tuesday' => 'tue', 'Wednesday' => 'wed', 'Thursday' => 'thu', 'Friday' => 'fri'];

        foreach ($suffixes as $day => $suffix) {
            $section = $this->makeSection(['sectionname' => "Sec-{$suffix}"]);

            $this->actingAs($this->manageUser())
                ->patchJson(route('faculty-loading.sections.recess', ['section' => $section->id, 'day' => $day]), [
                    "recess_start_{$suffix}" => '09:30',
                    "recess_end_{$suffix}"   => '09:50',
                ])
                ->assertOk();

            $this->assertDatabaseHas('sections', [
                'id' => $section->id,
                "recess_start_{$suffix}" => '09:30:00',
                "recess_end_{$suffix}"   => '09:50:00',
            ]);
        }
    }

    public function test_manage_user_can_clear_a_recess_override(): void
    {
        $section = $this->makeSection(['recess_start_wed' => '09:30:00', 'recess_end_wed' => '09:50:00']);

        $this->actingAs($this->manageUser())
            ->patchJson(route('faculty-loading.sections.recess', ['section' => $section->id, 'day' => 'Wednesday']), [
                'recess_start_wed' => '',
                'recess_end_wed'   => '',
            ])
            ->assertOk();

        $this->assertDatabaseHas('sections', [
            'id' => $section->id,
            'recess_start_wed' => null,
            'recess_end_wed'   => null,
        ]);
    }

    public function test_both_or_neither_validation_on_the_recess_endpoint(): void
    {
        $section = $this->makeSection();

        $this->actingAs($this->manageUser())
            ->patchJson(route('faculty-loading.sections.recess', ['section' => $section->id, 'day' => 'Friday']), [
                'recess_start_fri' => '09:30',
                'recess_end_fri'   => '',
            ])
            ->assertStatus(422);
    }

    public function test_invalid_day_param_is_rejected_by_the_recess_route(): void
    {
        // Built as a raw path (not via the route() helper) — the helper
        // itself throws when a parameter fails the route's where() pattern,
        // so this is the only way to actually exercise the routing-level
        // rejection instead of a PHP-level exception.
        $section = $this->makeSection();

        $this->actingAs($this->manageUser())
            ->patchJson("/faculty-loading/sections/{$section->id}/recess/Sunday", [
                'recess_start_mon' => '09:30',
                'recess_end_mon'   => '09:50',
            ])
            ->assertStatus(404);
    }

    public function test_unauthorized_user_cannot_set_a_recess_override(): void
    {
        $section = $this->makeSection();

        $this->actingAs($this->viewOnlyUser())
            ->patchJson(route('faculty-loading.sections.recess', ['section' => $section->id, 'day' => 'Monday']), [
                'recess_start_mon' => '09:30',
                'recess_end_mon'   => '09:50',
            ])
            ->assertForbidden();
    }
}
