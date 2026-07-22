<?php

namespace Tests\Feature\FacultyLoading;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\BellScheduleSetting;
use App\Models\FacultyLoading\Classroom;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\FacultyLoading\HardConstraintChecker;
use App\Services\FacultyLoading\ScheduleValidationService;
use App\Services\FacultyLoading\SchedulingConstants;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Wellness — the same three-scope mechanism as White Space (section, grade,
 * campus, narrowest wins), generalized from the old Wednesday-only
 * WEDNESDAY_WELLNESS setting (retired). Two differences from White Space:
 *   1. It DOES have one hardcoded default — the historical Wednesday
 *      9:50-10:20 window, preserved as WELLNESS_CAMPUS's Wednesday value.
 *   2. A full-Wednesday grade (wednesdayFullGrades(), currently [8]) is
 *      structurally exempt from Wellness on Wednesday at any scope.
 *
 * Covers:
 *   1. Section model: WELLNESS_OVERRIDE_COLUMNS map + wellnessOverrideFor()
 *   2. Mass-assignment round-trip for all 10 new columns
 *   3. SchedulingConstants::getBlockedSlots() applying a section override,
 *      any day (not just Wednesday)
 *   4. SchedulingConstants::getWellnessWindow() precedence + Wednesday default
 *   5. Full-Wednesday-grade exemption at every layer (resolveWellnessBlock,
 *      HardConstraintChecker H11, ScheduleValidationService)
 *   6. ScheduleValidationService blocking a manual placement inside the window
 *   7. PATCH .../sections/{section}/wellness/{day} (section scope)
 *   8. PATCH .../bell-schedule/wellness/grade/{grade}/{day} and .../campus/{day}
 */
class SectionWellnessOverrideTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Same static-cache leak guard as SectionWhiteSpaceOverrideTest /
        // Grade10ElectiveBandOverrideTest.
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

    public function test_wellness_override_columns_map_covers_all_five_weekdays(): void
    {
        $this->assertSame([
            'Monday'    => ['wellness_start_mon', 'wellness_end_mon'],
            'Tuesday'   => ['wellness_start_tue', 'wellness_end_tue'],
            'Wednesday' => ['wellness_start_wed', 'wellness_end_wed'],
            'Thursday'  => ['wellness_start_thu', 'wellness_end_thu'],
            'Friday'    => ['wellness_start_fri', 'wellness_end_fri'],
        ], Section::WELLNESS_OVERRIDE_COLUMNS);
    }

    public function test_wellness_override_for_returns_null_when_unset(): void
    {
        $section = $this->makeSection();

        foreach (SchedulingConstants::DAYS as $day) {
            $this->assertNull($section->wellnessOverrideFor($day));
        }
    }

    public function test_wellness_override_for_returns_the_correct_column_pair_per_day(): void
    {
        $section = $this->makeSection([
            'wellness_start_mon' => '09:00:00', 'wellness_end_mon' => '09:30:00',
            'wellness_start_fri' => '10:20:00', 'wellness_end_fri' => '10:50:00',
        ]);

        $this->assertSame(['start' => '09:00:00', 'end' => '09:30:00'], $section->wellnessOverrideFor('Monday'));
        $this->assertSame(['start' => '10:20:00', 'end' => '10:50:00'], $section->wellnessOverrideFor('Friday'));
        $this->assertNull($section->wellnessOverrideFor('Tuesday'));
        $this->assertNull($section->wellnessOverrideFor('Wednesday'));
        $this->assertNull($section->wellnessOverrideFor('Thursday'));
    }

    public function test_all_ten_new_wellness_columns_are_mass_assignable(): void
    {
        $section = $this->makeSection();
        $section->update([
            'wellness_start_mon' => '09:00', 'wellness_end_mon' => '09:30',
            'wellness_start_tue' => '08:15', 'wellness_end_tue' => '08:45',
            'wellness_start_wed' => '09:50', 'wellness_end_wed' => '10:20',
            'wellness_start_thu' => '08:25', 'wellness_end_thu' => '08:55',
            'wellness_start_fri' => '08:10', 'wellness_end_fri' => '08:40',
        ]);

        $fresh = Section::findOrFail($section->id);

        $this->assertSame('09:00:00', $fresh->wellness_start_mon);
        $this->assertSame('09:30:00', $fresh->wellness_end_mon);
        $this->assertSame('08:15:00', $fresh->wellness_start_tue);
        $this->assertSame('08:45:00', $fresh->wellness_end_tue);
        $this->assertSame('09:50:00', $fresh->wellness_start_wed);
        $this->assertSame('10:20:00', $fresh->wellness_end_wed);
        $this->assertSame('08:25:00', $fresh->wellness_start_thu);
        $this->assertSame('08:55:00', $fresh->wellness_end_thu);
        $this->assertSame('08:10:00', $fresh->wellness_start_fri);
        $this->assertSame('08:40:00', $fresh->wellness_end_fri);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 3. SchedulingConstants::getBlockedSlots — pure function, no DB
    // ═══════════════════════════════════════════════════════════════════════

    public function test_get_blocked_slots_applies_wellness_default_on_wednesday(): void
    {
        // Grade 7 (not full-Wednesday) picks up the campus default with no
        // override needed at all.
        $blocked = SchedulingConstants::getBlockedSlots(7, 'Wednesday');

        $wellness = collect($blocked)->firstWhere('type', 'WELLNESS');
        $this->assertNotNull($wellness);
        $this->assertSame('09:50', $wellness['start']);
        $this->assertSame('10:20', $wellness['end']);
    }

    public function test_get_blocked_slots_has_no_wellness_on_other_days_by_default(): void
    {
        foreach (['Monday', 'Tuesday', 'Thursday', 'Friday'] as $day) {
            $blocked = SchedulingConstants::getBlockedSlots(7, $day);
            $this->assertNull(collect($blocked)->firstWhere('type', 'WELLNESS'), "Grade 7 {$day}");
        }
    }

    public function test_get_blocked_slots_applies_a_wellness_override_on_a_non_wednesday_day(): void
    {
        $override = ['start' => '08:15', 'end' => '08:45'];
        $blocked  = SchedulingConstants::getBlockedSlots(9, 'Tuesday', null, null, null, $override);

        $wellness = collect($blocked)->firstWhere('type', 'WELLNESS');
        $this->assertNotNull($wellness);
        $this->assertSame('08:15', $wellness['start']);
        $this->assertSame('08:45', $wellness['end']);
        $this->assertSame('Wellness Break', $wellness['label']);
    }

    public function test_get_blocked_slots_applies_wellness_alongside_white_space(): void
    {
        $whiteSpaceOverride = ['start' => '13:00', 'end' => '13:30'];
        $wellnessOverride   = ['start' => '08:15', 'end' => '08:45'];
        $blocked = SchedulingConstants::getBlockedSlots(9, 'Tuesday', null, null, $whiteSpaceOverride, $wellnessOverride);

        $this->assertNotNull(collect($blocked)->firstWhere('type', 'WHITE_SPACE'));
        $wellness = collect($blocked)->firstWhere('type', 'WELLNESS');
        $this->assertSame('08:15', $wellness['start']);
        $this->assertSame('08:45', $wellness['end']);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 4. SchedulingConstants::getWellnessWindow — precedence + Wednesday default
    // ═══════════════════════════════════════════════════════════════════════

    public function test_get_wellness_window_defaults_to_the_wednesday_campus_window(): void
    {
        $this->assertSame(['start' => '09:50', 'end' => '10:20'], SchedulingConstants::getWellnessWindow(7, 'Wednesday'));
    }

    public function test_get_wellness_window_is_null_on_other_days_by_default(): void
    {
        $this->assertNull(SchedulingConstants::getWellnessWindow(7, 'Tuesday'));
    }

    public function test_grade_wide_wellness_setting_beats_campus_wide(): void
    {
        BellScheduleSetting::create([
            'setting_key' => 'WELLNESS_BY_GRADE',
            'value' => [10 => ['Wednesday' => ['start' => '08:00', 'end' => '08:30']]],
        ]);

        // Grade 10 gets its own grade-wide override.
        $this->assertSame(['start' => '08:00', 'end' => '08:30'], SchedulingConstants::getWellnessWindow(10, 'Wednesday'));
        // Every other grade still falls back to the campus-wide Wednesday default.
        $this->assertSame(['start' => '09:50', 'end' => '10:20'], SchedulingConstants::getWellnessWindow(11, 'Wednesday'));
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 5. Full-Wednesday-grade exemption — every layer
    // ═══════════════════════════════════════════════════════════════════════

    public function test_grade_8_gets_no_wellness_block_on_wednesday(): void
    {
        $blocked = SchedulingConstants::getBlockedSlots(8, 'Wednesday');

        $this->assertNull(collect($blocked)->firstWhere('type', 'WELLNESS'));
    }

    public function test_grade_8_exemption_holds_even_with_an_explicit_override(): void
    {
        $override = ['start' => '08:00', 'end' => '08:30'];
        $blocked  = SchedulingConstants::getBlockedSlots(8, 'Wednesday', null, null, null, $override);

        $this->assertNull(collect($blocked)->firstWhere('type', 'WELLNESS'));
    }

    public function test_grade_8_still_gets_wellness_on_a_non_wednesday_day_with_an_override(): void
    {
        // The exemption is Wednesday-specific, not a blanket Grade 8 ban.
        $override = ['start' => '08:15', 'end' => '08:45'];
        $blocked  = SchedulingConstants::getBlockedSlots(8, 'Tuesday', null, null, null, $override);

        $this->assertNotNull(collect($blocked)->firstWhere('type', 'WELLNESS'));
    }

    public function test_h11_grade_8_is_exempt_from_wellness_block(): void
    {
        $this->assertTrue(
            HardConstraintChecker::checkWednesdayWellness('Wednesday', '09:50', '10:20', 8)['passes']
        );
    }

    public function test_h11_other_grades_still_blocked_on_wednesday(): void
    {
        $this->assertFalse(
            HardConstraintChecker::checkWednesdayWellness('Wednesday', '09:50', '10:20', 7)['passes']
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 6. ScheduleValidationService — manual placement respects Wellness
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
            'day_of_week'      => 'Wednesday',
            'start_time'       => '08:00:00',
            'end_time'         => '09:00:00',
        ], $overrides);
    }

    public function test_manual_placement_is_blocked_inside_the_wednesday_wellness_default(): void
    {
        $this->bootValidationFixtures();
        $section = $this->makeSection(['levelid' => 7]);

        $result = $this->svc->validate($this->scheduleData($section, [
            'day_of_week' => 'Wednesday', 'start_time' => '09:50:00', 'end_time' => '10:20:00',
        ]));

        $this->assertFalse($result['valid']);
        $wellnessErrors = array_filter($result['errors'], fn ($e) => str_contains($e, 'Wellness'));
        $this->assertNotEmpty($wellnessErrors);
    }

    public function test_manual_placement_for_grade_8_is_unaffected_on_wednesday(): void
    {
        $this->bootValidationFixtures();
        $section = $this->makeSection(['levelid' => 8]);

        $result = $this->svc->validate($this->scheduleData($section, [
            'day_of_week' => 'Wednesday', 'start_time' => '09:50:00', 'end_time' => '10:20:00',
        ]));

        $wellnessErrors = array_filter($result['errors'], fn ($e) => str_contains($e, 'Wellness'));
        $this->assertEmpty($wellnessErrors);
    }

    public function test_wellness_override_does_not_affect_a_sibling_section(): void
    {
        $this->bootValidationFixtures();
        $this->makeSection([
            'sectionname' => 'Turquoise', 'levelid' => 7,
            'wellness_start_tue' => '08:15:00', 'wellness_end_tue' => '08:45:00',
        ]);
        $sibling = $this->makeSection(['sectionname' => 'Opal', 'levelid' => 7]);

        $result = $this->svc->validate($this->scheduleData($sibling, [
            'day_of_week' => 'Tuesday', 'start_time' => '08:15:00', 'end_time' => '08:45:00',
        ]));

        $wellnessErrors = array_filter($result['errors'], fn ($e) => str_contains($e, 'Wellness'));
        $this->assertEmpty($wellnessErrors);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 7. HTTP — PATCH .../sections/{section}/wellness/{day} (section scope)
    // ═══════════════════════════════════════════════════════════════════════

    public function test_manage_user_can_set_wellness_override_for_every_weekday(): void
    {
        $suffixes = ['Monday' => 'mon', 'Tuesday' => 'tue', 'Wednesday' => 'wed', 'Thursday' => 'thu', 'Friday' => 'fri'];

        foreach ($suffixes as $day => $suffix) {
            $section = $this->makeSection(['sectionname' => "Sec-{$suffix}"]);

            $this->actingAs($this->manageUser())
                ->patchJson(route('faculty-loading.sections.wellness', ['section' => $section->id, 'day' => $day]), [
                    "wellness_start_{$suffix}" => '08:15',
                    "wellness_end_{$suffix}"   => '08:45',
                ])
                ->assertOk();

            $this->assertDatabaseHas('sections', [
                'id' => $section->id,
                "wellness_start_{$suffix}" => '08:15:00',
                "wellness_end_{$suffix}"   => '08:45:00',
            ]);
        }
    }

    public function test_manage_user_can_clear_a_wellness_override(): void
    {
        $section = $this->makeSection(['wellness_start_wed' => '09:50:00', 'wellness_end_wed' => '10:20:00']);

        $this->actingAs($this->manageUser())
            ->patchJson(route('faculty-loading.sections.wellness', ['section' => $section->id, 'day' => 'Wednesday']), [
                'wellness_start_wed' => '',
                'wellness_end_wed'   => '',
            ])
            ->assertOk();

        $this->assertDatabaseHas('sections', [
            'id' => $section->id,
            'wellness_start_wed' => null,
            'wellness_end_wed'   => null,
        ]);
    }

    public function test_unauthorized_user_cannot_set_a_wellness_override(): void
    {
        $section = $this->makeSection();

        $this->actingAs($this->viewOnlyUser())
            ->patchJson(route('faculty-loading.sections.wellness', ['section' => $section->id, 'day' => 'Monday']), [
                'wellness_start_mon' => '08:15',
                'wellness_end_mon'   => '08:45',
            ])
            ->assertForbidden();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 8. HTTP — bell-schedule Wellness (grade/campus scope)
    // ═══════════════════════════════════════════════════════════════════════

    public function test_cid_chief_can_set_and_clear_grade_wide_wellness(): void
    {
        $this->actingAs($this->cidChiefUser())
            ->patchJson(route('faculty-loading.bell-schedule.wellness.grade', ['grade' => 10, 'day' => 'Tuesday']), [
                'start' => '08:15', 'end' => '08:45',
            ])
            ->assertOk();

        $this->assertSame(['start' => '08:15', 'end' => '08:45'], SchedulingConstants::getWellnessWindow(10, 'Tuesday'));

        $this->actingAs($this->cidChiefUser())
            ->patchJson(route('faculty-loading.bell-schedule.wellness.grade', ['grade' => 10, 'day' => 'Tuesday']), [
                'start' => null, 'end' => null,
            ])
            ->assertOk();

        $this->assertNull(SchedulingConstants::getWellnessWindow(10, 'Tuesday'));
    }

    public function test_cid_chief_can_set_and_clear_campus_wide_wellness(): void
    {
        $this->actingAs($this->cidChiefUser())
            ->patchJson(route('faculty-loading.bell-schedule.wellness.campus', ['day' => 'Thursday']), [
                'start' => '08:15', 'end' => '08:45',
            ])
            ->assertOk();

        $this->assertSame(['start' => '08:15', 'end' => '08:45'], SchedulingConstants::getWellnessWindow(9, 'Thursday'));

        $this->actingAs($this->cidChiefUser())
            ->patchJson(route('faculty-loading.bell-schedule.wellness.campus', ['day' => 'Thursday']), [
                'start' => null, 'end' => null,
            ])
            ->assertOk();

        $this->assertNull(SchedulingConstants::getWellnessWindow(9, 'Thursday'));
    }

    public function test_manage_only_user_cannot_set_grade_wide_wellness(): void
    {
        $this->actingAs($this->manageUser())
            ->patchJson(route('faculty-loading.bell-schedule.wellness.grade', ['grade' => 10, 'day' => 'Tuesday']), [
                'start' => '08:15', 'end' => '08:45',
            ])
            ->assertForbidden();
    }
}
