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
 * White Space — a free/flexible block with NO grade default, set at one of
 * three scopes (narrowest wins): section (Section::WHITE_SPACE_OVERRIDE_COLUMNS,
 * mirrors the lunch/recess override pattern), grade (WHITE_SPACE_BY_GRADE in
 * bell_schedule_settings), or campus-wide (WHITE_SPACE_CAMPUS). Covers:
 *   1. Section model: WHITE_SPACE_OVERRIDE_COLUMNS map + whiteSpaceOverrideFor()
 *   2. Mass-assignment round-trip for all 10 new columns
 *   3. SchedulingConstants::getBlockedSlots() applying a section override
 *   4. SchedulingConstants::getWhiteSpaceWindow() precedence (grade > campus > none)
 *   5. ScheduleValidationService blocking a manual placement inside the window
 *   6. PATCH .../sections/{section}/white-space/{day} (section scope)
 *   7. PATCH .../bell-schedule/white-space/grade/{grade}/{day} and .../campus/{day}
 *      (grade/campus scope — CID Chief/Administrator only)
 */
class SectionWhiteSpaceOverrideTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // SchedulingConstants caches bell_schedule_settings in a static,
        // request-scoped variable — RefreshDatabase resets the DB between
        // tests but not this, so it must be flushed explicitly or an earlier
        // test's WHITE_SPACE_CAMPUS/WHITE_SPACE_BY_GRADE value leaks into a
        // later test running in the same process (see Grade10ElectiveBandOverrideTest).
        SchedulingConstants::flushOverrideCache();
    }

    // ── Permission helpers ───────────────────────────────────────────────────

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

    /** A manage-permission user who is ALSO CID Chief — the only role (besides
     *  Administrator) BellScheduleController::authorizeEditor() accepts. */
    private function cidChiefUser(): User
    {
        $user = $this->manageUser();
        $user->roles()->attach(Role::firstOrCreate(['name' => 'CID Chief'])->id);

        return $user;
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

    public function test_white_space_override_columns_map_covers_all_five_weekdays(): void
    {
        $this->assertSame([
            'Monday'    => ['white_space_start_mon', 'white_space_end_mon'],
            'Tuesday'   => ['white_space_start_tue', 'white_space_end_tue'],
            'Wednesday' => ['white_space_start_wed', 'white_space_end_wed'],
            'Thursday'  => ['white_space_start_thu', 'white_space_end_thu'],
            'Friday'    => ['white_space_start_fri', 'white_space_end_fri'],
        ], Section::WHITE_SPACE_OVERRIDE_COLUMNS);
    }

    public function test_white_space_override_for_returns_null_when_unset(): void
    {
        $section = $this->makeSection();

        foreach (SchedulingConstants::DAYS as $day) {
            $this->assertNull($section->whiteSpaceOverrideFor($day));
        }
    }

    public function test_white_space_override_for_returns_the_correct_column_pair_per_day(): void
    {
        $section = $this->makeSection([
            'white_space_start_wed' => '12:10:00', 'white_space_end_wed' => '13:10:00',
            'white_space_start_fri' => '10:20:00', 'white_space_end_fri' => '11:10:00',
        ]);

        $this->assertSame(['start' => '12:10:00', 'end' => '13:10:00'], $section->whiteSpaceOverrideFor('Wednesday'));
        $this->assertSame(['start' => '10:20:00', 'end' => '11:10:00'], $section->whiteSpaceOverrideFor('Friday'));
        $this->assertNull($section->whiteSpaceOverrideFor('Monday'));
        $this->assertNull($section->whiteSpaceOverrideFor('Tuesday'));
        $this->assertNull($section->whiteSpaceOverrideFor('Thursday'));
    }

    public function test_all_ten_new_white_space_columns_are_mass_assignable(): void
    {
        $section = $this->makeSection();
        $section->update([
            'white_space_start_mon' => '09:30', 'white_space_end_mon' => '09:50',
            'white_space_start_tue' => '08:15', 'white_space_end_tue' => '08:35',
            'white_space_start_wed' => '12:10', 'white_space_end_wed' => '13:10',
            'white_space_start_thu' => '08:25', 'white_space_end_thu' => '08:45',
            'white_space_start_fri' => '08:10', 'white_space_end_fri' => '08:30',
        ]);

        $fresh = Section::findOrFail($section->id);

        $this->assertSame('09:30:00', $fresh->white_space_start_mon);
        $this->assertSame('09:50:00', $fresh->white_space_end_mon);
        $this->assertSame('08:15:00', $fresh->white_space_start_tue);
        $this->assertSame('08:35:00', $fresh->white_space_end_tue);
        $this->assertSame('12:10:00', $fresh->white_space_start_wed);
        $this->assertSame('13:10:00', $fresh->white_space_end_wed);
        $this->assertSame('08:25:00', $fresh->white_space_start_thu);
        $this->assertSame('08:45:00', $fresh->white_space_end_thu);
        $this->assertSame('08:10:00', $fresh->white_space_start_fri);
        $this->assertSame('08:30:00', $fresh->white_space_end_fri);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 3. SchedulingConstants::getBlockedSlots — pure function, no DB
    // ═══════════════════════════════════════════════════════════════════════

    public function test_get_blocked_slots_has_no_white_space_by_default(): void
    {
        $blocked = SchedulingConstants::getBlockedSlots(7, 'Wednesday');

        $this->assertNull(collect($blocked)->firstWhere('type', 'WHITE_SPACE'));
    }

    public function test_get_blocked_slots_applies_a_white_space_override(): void
    {
        $override = ['start' => '12:10', 'end' => '13:10'];
        $blocked  = SchedulingConstants::getBlockedSlots(10, 'Wednesday', null, null, $override);

        $whiteSpace = collect($blocked)->firstWhere('type', 'WHITE_SPACE');
        $this->assertNotNull($whiteSpace);
        $this->assertSame('12:10', $whiteSpace['start']);
        $this->assertSame('13:10', $whiteSpace['end']);
        $this->assertSame('White Space', $whiteSpace['label']);
    }

    public function test_get_blocked_slots_applies_white_space_alongside_lunch_and_recess(): void
    {
        $lunchOverride      = ['start' => '11:00', 'end' => '11:45'];
        $recessOverride     = ['start' => '09:30', 'end' => '09:50'];
        $whiteSpaceOverride = ['start' => '13:00', 'end' => '13:30'];
        $blocked = SchedulingConstants::getBlockedSlots(7, 'Monday', $lunchOverride, $recessOverride, $whiteSpaceOverride);

        $this->assertNotNull(collect($blocked)->firstWhere('type', 'LUNCH'));
        $this->assertNotNull(collect($blocked)->firstWhere('type', 'RECESS'));
        $whiteSpace = collect($blocked)->firstWhere('type', 'WHITE_SPACE');
        $this->assertSame('13:00', $whiteSpace['start']);
        $this->assertSame('13:30', $whiteSpace['end']);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 4. SchedulingConstants::getWhiteSpaceWindow — grade/campus precedence
    // ═══════════════════════════════════════════════════════════════════════

    public function test_get_white_space_window_is_null_when_nothing_is_set(): void
    {
        $this->assertNull(SchedulingConstants::getWhiteSpaceWindow(10, 'Wednesday'));
    }

    public function test_get_white_space_window_reads_the_campus_wide_setting(): void
    {
        \App\Models\FacultyLoading\BellScheduleSetting::create([
            'setting_key' => 'WHITE_SPACE_CAMPUS',
            'value' => ['Wednesday' => ['start' => '12:10', 'end' => '13:10']],
        ]);

        $this->assertSame(['start' => '12:10', 'end' => '13:10'], SchedulingConstants::getWhiteSpaceWindow(10, 'Wednesday'));
        // A different day/grade is unaffected.
        $this->assertNull(SchedulingConstants::getWhiteSpaceWindow(10, 'Thursday'));
    }

    public function test_grade_wide_white_space_setting_beats_campus_wide(): void
    {
        \App\Models\FacultyLoading\BellScheduleSetting::create([
            'setting_key' => 'WHITE_SPACE_CAMPUS',
            'value' => ['Wednesday' => ['start' => '10:00', 'end' => '10:30']],
        ]);
        \App\Models\FacultyLoading\BellScheduleSetting::create([
            'setting_key' => 'WHITE_SPACE_BY_GRADE',
            'value' => [10 => ['Wednesday' => ['start' => '12:10', 'end' => '13:10']]],
        ]);

        // Grade 10 gets its own grade-wide override.
        $this->assertSame(['start' => '12:10', 'end' => '13:10'], SchedulingConstants::getWhiteSpaceWindow(10, 'Wednesday'));
        // Every other grade still falls back to the campus-wide setting.
        $this->assertSame(['start' => '10:00', 'end' => '10:30'], SchedulingConstants::getWhiteSpaceWindow(11, 'Wednesday'));
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 5. ScheduleValidationService — manual placement respects White Space
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

    public function test_manual_placement_is_blocked_inside_a_sections_white_space_override(): void
    {
        $this->bootValidationFixtures();
        $section = $this->makeSection([
            'white_space_start_mon' => '10:00:00', 'white_space_end_mon' => '10:30:00',
        ]);

        $result = $this->svc->validate($this->scheduleData($section, [
            'day_of_week' => 'Monday', 'start_time' => '10:00:00', 'end_time' => '10:30:00',
        ]));

        $this->assertFalse($result['valid']);
        $wsErrors = array_filter($result['errors'], fn ($e) => str_contains($e, 'White Space'));
        $this->assertNotEmpty($wsErrors);
    }

    public function test_manual_placement_outside_white_space_is_unaffected(): void
    {
        $this->bootValidationFixtures();
        $section = $this->makeSection([
            'white_space_start_mon' => '10:00:00', 'white_space_end_mon' => '10:30:00',
        ]);

        $result = $this->svc->validate($this->scheduleData($section, [
            'day_of_week' => 'Monday', 'start_time' => '08:00:00', 'end_time' => '09:00:00',
        ]));

        $wsErrors = array_filter($result['errors'], fn ($e) => str_contains($e, 'White Space'));
        $this->assertEmpty($wsErrors);
    }

    public function test_white_space_override_does_not_affect_a_sibling_section(): void
    {
        $this->bootValidationFixtures();
        $this->makeSection([
            'sectionname' => 'Turquoise',
            'white_space_start_mon' => '10:00:00', 'white_space_end_mon' => '10:30:00',
        ]);
        $sibling = $this->makeSection(['sectionname' => 'Opal']);

        $result = $this->svc->validate($this->scheduleData($sibling, [
            'day_of_week' => 'Monday', 'start_time' => '10:00:00', 'end_time' => '10:30:00',
        ]));

        $wsErrors = array_filter($result['errors'], fn ($e) => str_contains($e, 'White Space'));
        $this->assertEmpty($wsErrors);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 6. HTTP — PATCH .../sections/{section}/white-space/{day} (section scope)
    // ═══════════════════════════════════════════════════════════════════════

    public function test_manage_user_can_set_white_space_override_for_every_weekday(): void
    {
        $suffixes = ['Monday' => 'mon', 'Tuesday' => 'tue', 'Wednesday' => 'wed', 'Thursday' => 'thu', 'Friday' => 'fri'];

        foreach ($suffixes as $day => $suffix) {
            $section = $this->makeSection(['sectionname' => "Sec-{$suffix}"]);

            $this->actingAs($this->manageUser())
                ->patchJson(route('faculty-loading.sections.white-space', ['section' => $section->id, 'day' => $day]), [
                    "white_space_start_{$suffix}" => '12:10',
                    "white_space_end_{$suffix}"   => '13:10',
                ])
                ->assertOk();

            $this->assertDatabaseHas('sections', [
                'id' => $section->id,
                "white_space_start_{$suffix}" => '12:10:00',
                "white_space_end_{$suffix}"   => '13:10:00',
            ]);
        }
    }

    public function test_manage_user_can_clear_a_white_space_override(): void
    {
        $section = $this->makeSection(['white_space_start_wed' => '12:10:00', 'white_space_end_wed' => '13:10:00']);

        $this->actingAs($this->manageUser())
            ->patchJson(route('faculty-loading.sections.white-space', ['section' => $section->id, 'day' => 'Wednesday']), [
                'white_space_start_wed' => '',
                'white_space_end_wed'   => '',
            ])
            ->assertOk();

        $this->assertDatabaseHas('sections', [
            'id' => $section->id,
            'white_space_start_wed' => null,
            'white_space_end_wed'   => null,
        ]);
    }

    public function test_both_or_neither_validation_on_the_white_space_endpoint(): void
    {
        $section = $this->makeSection();

        $this->actingAs($this->manageUser())
            ->patchJson(route('faculty-loading.sections.white-space', ['section' => $section->id, 'day' => 'Friday']), [
                'white_space_start_fri' => '12:10',
                'white_space_end_fri'   => '',
            ])
            ->assertStatus(422);
    }

    public function test_invalid_day_param_is_rejected_by_the_white_space_route(): void
    {
        $section = $this->makeSection();

        $this->actingAs($this->manageUser())
            ->patchJson("/faculty-loading/sections/{$section->id}/white-space/Sunday", [
                'white_space_start_mon' => '12:10',
                'white_space_end_mon'   => '13:10',
            ])
            ->assertStatus(404);
    }

    public function test_unauthorized_user_cannot_set_a_white_space_override(): void
    {
        $section = $this->makeSection();

        $this->actingAs($this->viewOnlyUser())
            ->patchJson(route('faculty-loading.sections.white-space', ['section' => $section->id, 'day' => 'Monday']), [
                'white_space_start_mon' => '12:10',
                'white_space_end_mon'   => '13:10',
            ])
            ->assertForbidden();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 7. HTTP — bell-schedule White Space (grade/campus scope)
    // ═══════════════════════════════════════════════════════════════════════

    public function test_cid_chief_can_set_and_clear_grade_wide_white_space(): void
    {
        $this->actingAs($this->cidChiefUser())
            ->patchJson(route('faculty-loading.bell-schedule.white-space.grade', ['grade' => 10, 'day' => 'Wednesday']), [
                'start' => '12:10', 'end' => '13:10',
            ])
            ->assertOk();

        $this->assertSame(['start' => '12:10', 'end' => '13:10'], SchedulingConstants::getWhiteSpaceWindow(10, 'Wednesday'));

        $this->actingAs($this->cidChiefUser())
            ->patchJson(route('faculty-loading.bell-schedule.white-space.grade', ['grade' => 10, 'day' => 'Wednesday']), [
                'start' => null, 'end' => null,
            ])
            ->assertOk();

        $this->assertNull(SchedulingConstants::getWhiteSpaceWindow(10, 'Wednesday'));
    }

    public function test_cid_chief_can_set_and_clear_campus_wide_white_space(): void
    {
        $this->actingAs($this->cidChiefUser())
            ->patchJson(route('faculty-loading.bell-schedule.white-space.campus', ['day' => 'Thursday']), [
                'start' => '12:10', 'end' => '13:10',
            ])
            ->assertOk();

        $this->assertSame(['start' => '12:10', 'end' => '13:10'], SchedulingConstants::getWhiteSpaceWindow(7, 'Thursday'));
        $this->assertSame(['start' => '12:10', 'end' => '13:10'], SchedulingConstants::getWhiteSpaceWindow(12, 'Thursday'));

        $this->actingAs($this->cidChiefUser())
            ->patchJson(route('faculty-loading.bell-schedule.white-space.campus', ['day' => 'Thursday']), [
                'start' => null, 'end' => null,
            ])
            ->assertOk();

        $this->assertNull(SchedulingConstants::getWhiteSpaceWindow(7, 'Thursday'));
    }

    public function test_manage_only_user_cannot_set_grade_wide_white_space(): void
    {
        // Has faculty_loading.manage (route-level permission) but is not
        // CID Chief/Administrator — authorizeEditor() must still reject.
        $this->actingAs($this->manageUser())
            ->patchJson(route('faculty-loading.bell-schedule.white-space.grade', ['grade' => 10, 'day' => 'Wednesday']), [
                'start' => '12:10', 'end' => '13:10',
            ])
            ->assertForbidden();
    }

    public function test_invalid_grade_is_rejected_by_the_grade_white_space_route(): void
    {
        $this->actingAs($this->cidChiefUser())
            ->patchJson('/faculty-loading/bell-schedule/white-space/grade/6/Wednesday', [
                'start' => '12:10', 'end' => '13:10',
            ])
            ->assertStatus(404);
    }
}
