<?php

namespace Tests\Feature\FacultyLoading;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\FacultyLoading\SchedulingConstants;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Consultation/Home Bound per-grade-per-day override — fixes a real bug found
 * 2026-07-22: Grade X's Tuesday/Wednesday/Thursday/Friday Consultation all
 * come from ONE shared literal row (TUEFRI_730_* applies identically to all
 * four days), so the original implementation — editing that row directly via
 * bell-schedule.update — meant changing "Tuesday's" Consultation time also
 * changed Wed/Thu/Fri. This layers a genuine per-day override
 * (CONSULTATION_BY_GRADE_DAY) on top of the shared row instead, the same
 * "override layered over a shared table" pattern Lunch/Recess already use.
 *
 * Covers:
 *   1. SchedulingConstants::getConsultationOverride() precedence
 *   2. getBlockedSlots()/getDisplayBlockedSlots() apply the override for
 *      ONE day only — the core regression test for the reported bug
 *   3. PATCH .../bell-schedule/consultation/grade/{grade}/{day}
 */
class ConsultationOverrideTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        SchedulingConstants::flushOverrideCache();
    }

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

    private function cidChiefUser(): User
    {
        $user = $this->manageUser();
        $user->roles()->attach(Role::firstOrCreate(['name' => 'CID Chief'])->id);

        return $user;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 1. SchedulingConstants::getConsultationOverride
    // ═══════════════════════════════════════════════════════════════════════

    public function test_get_consultation_override_is_null_when_unset(): void
    {
        $this->assertNull(SchedulingConstants::getConsultationOverride(7, 'Tuesday'));
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 2. Core regression: one day's override must not leak into the others
    // ═══════════════════════════════════════════════════════════════════════

    public function test_setting_tuesdays_consultation_does_not_change_other_days(): void
    {
        $grade = 7;

        // Wednesday and Friday have their own pre-existing, unrelated overlap
        // rules in getBlockedSlots() (CONSULT is replaced by ACTIVITY/ALP on
        // Wednesday and by the Flag Retreat block on Friday when they overlap),
        // so this compares against Thursday specifically — a "plain" day with
        // none of that, but which shares the exact same literal TUEFRI_730_*
        // row as Tuesday, making it the right control for this regression test.
        $baseline = collect(SchedulingConstants::getBlockedSlots($grade, 'Tuesday'))->firstWhere('type', 'CONSULT');
        $thursdayBefore = collect(SchedulingConstants::getBlockedSlots($grade, 'Thursday'))->firstWhere('type', 'CONSULT');
        $this->assertSame($baseline['start'], $thursdayBefore['start'], 'Thursday baseline start');
        $this->assertSame($baseline['end'], $thursdayBefore['end'], 'Thursday baseline end');

        $this->actingAs($this->cidChiefUser())
            ->patchJson(route('faculty-loading.bell-schedule.consultation.grade', ['grade' => $grade, 'day' => 'Tuesday']), [
                'start' => '15:00', 'end' => '15:45',
            ])
            ->assertOk();

        // Tuesday changed...
        $tuesday = collect(SchedulingConstants::getBlockedSlots($grade, 'Tuesday'))->firstWhere('type', 'CONSULT');
        $this->assertSame('15:00', $tuesday['start']);
        $this->assertSame('15:45', $tuesday['end']);

        // ...but Thursday (sharing the same underlying literal row) is
        // completely untouched — this is the bug fix.
        $thursdayAfter = collect(SchedulingConstants::getBlockedSlots($grade, 'Thursday'))->firstWhere('type', 'CONSULT');
        $this->assertSame($baseline['start'], $thursdayAfter['start'], 'Thursday unaffected start');
        $this->assertSame($baseline['end'], $thursdayAfter['end'], 'Thursday unaffected end');
    }

    public function test_get_display_blocked_slots_also_applies_the_override_independently(): void
    {
        $this->actingAs($this->cidChiefUser())
            ->patchJson(route('faculty-loading.bell-schedule.consultation.grade', ['grade' => 9, 'day' => 'Thursday']), [
                'start' => '14:00', 'end' => '14:45',
            ])
            ->assertOk();

        $thursday = collect(SchedulingConstants::getDisplayBlockedSlots(9, 'Thursday'))->firstWhere('type', 'CONSULT');
        $this->assertSame('14:00', $thursday['start']);
        $this->assertSame('14:45', $thursday['end']);

        $friday = collect(SchedulingConstants::getDisplayBlockedSlots(9, 'Friday'))->firstWhere('type', 'CONSULT');
        $this->assertNotSame('14:00', $friday['start']);
    }

    public function test_different_grades_do_not_affect_each_other(): void
    {
        $this->actingAs($this->cidChiefUser())
            ->patchJson(route('faculty-loading.bell-schedule.consultation.grade', ['grade' => 11, 'day' => 'Tuesday']), [
                'start' => '15:00', 'end' => '15:30',
            ])
            ->assertOk();

        $g11 = collect(SchedulingConstants::getBlockedSlots(11, 'Tuesday'))->firstWhere('type', 'CONSULT');
        $g12 = collect(SchedulingConstants::getBlockedSlots(12, 'Tuesday'))->firstWhere('type', 'CONSULT');
        $this->assertSame('15:00', $g11['start']);
        $this->assertNotSame('15:00', $g12['start']);
    }

    public function test_clearing_the_override_reverts_to_the_shared_row(): void
    {
        $original = collect(SchedulingConstants::getBlockedSlots(7, 'Thursday'))->firstWhere('type', 'CONSULT');

        $this->actingAs($this->cidChiefUser())
            ->patchJson(route('faculty-loading.bell-schedule.consultation.grade', ['grade' => 7, 'day' => 'Thursday']), [
                'start' => '15:00', 'end' => '15:30',
            ])
            ->assertOk();
        $this->assertNotSame($original['start'], collect(SchedulingConstants::getBlockedSlots(7, 'Thursday'))->firstWhere('type', 'CONSULT')['start']);

        $this->actingAs($this->cidChiefUser())
            ->patchJson(route('faculty-loading.bell-schedule.consultation.grade', ['grade' => 7, 'day' => 'Thursday']), [
                'start' => null, 'end' => null,
            ])
            ->assertOk();

        $reverted = collect(SchedulingConstants::getBlockedSlots(7, 'Thursday'))->firstWhere('type', 'CONSULT');
        $this->assertSame($original['start'], $reverted['start']);
        $this->assertSame($original['end'], $reverted['end']);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 3. HTTP endpoint
    // ═══════════════════════════════════════════════════════════════════════

    public function test_manage_only_user_cannot_set_consultation_override(): void
    {
        $this->actingAs($this->manageUser())
            ->patchJson(route('faculty-loading.bell-schedule.consultation.grade', ['grade' => 7, 'day' => 'Tuesday']), [
                'start' => '15:00', 'end' => '15:30',
            ])
            ->assertForbidden();
    }

    public function test_invalid_grade_is_rejected(): void
    {
        $this->actingAs($this->cidChiefUser())
            ->patchJson('/faculty-loading/bell-schedule/consultation/grade/6/Tuesday', [
                'start' => '15:00', 'end' => '15:30',
            ])
            ->assertStatus(404);
    }

    public function test_both_or_neither_validation(): void
    {
        $this->actingAs($this->cidChiefUser())
            ->patchJson(route('faculty-loading.bell-schedule.consultation.grade', ['grade' => 7, 'day' => 'Tuesday']), [
                'start' => '15:00', 'end' => '',
            ])
            ->assertStatus(422);
    }
}
