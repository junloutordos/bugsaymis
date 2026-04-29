<?php

namespace Tests\Feature\FacultyLoading;

use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\SectionFixedSlot;
use App\Services\FacultyLoading\FixedSlotService;
use App\Services\FacultyLoading\SchedulingConstants as SC;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Tests for FixedSlotService
 *
 * Verifies that sealFixedSlots() produces structurally correct rows and
 * that the static helpers agree with SchedulingConstants.
 *
 * Uses DatabaseTransactions (not RefreshDatabase) so tests run against
 * the already-migrated DB and roll back cleanly — avoids the pre-existing
 * migrate:fresh failures in this test environment.
 */
class FixedSlotServiceTest extends TestCase
{
    use DatabaseTransactions;

    private FixedSlotService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new FixedSlotService();
    }

    // ── Static fixedSlotsForDay ───────────────────────────────────────────────

    public function test_monday_g7_has_flag_homeroom_dead_recess_lunch_consult(): void
    {
        $slots = FixedSlotService::fixedSlotsForDay(7, 'G7G8', 'Monday');
        $types = array_column($slots, 'type');

        $this->assertContains('FLAG',     $types, 'G7 Monday must have FLAG');
        $this->assertContains('HOMEROOM', $types, 'G7 Monday must have HOMEROOM');
        $this->assertContains('DEAD',     $types, 'G7 Monday must have DEAD zone');
        $this->assertContains('RECESS',   $types, 'G7 Monday must have RECESS');
        $this->assertContains('LUNCH',    $types, 'G7 Monday must have LUNCH');
        $this->assertContains('CONSULT',  $types, 'G7 Monday must have CONSULT');
    }

    public function test_monday_g11_has_advising_not_homeroom(): void
    {
        $slots = FixedSlotService::fixedSlotsForDay(11, 'G11G12', 'Monday');
        $types = array_column($slots, 'type');

        $this->assertContains('ADVISING', $types,    'G11 Monday must have ADVISING');
        $this->assertNotContains('HOMEROOM', $types, 'G11 Monday must NOT have HOMEROOM');
    }

    public function test_friday_g7_contains_ila_slots(): void
    {
        $slots = FixedSlotService::fixedSlotsForDay(7, 'G7G8', 'Friday');
        $types = array_column($slots, 'type');

        $this->assertContains('ILA', $types, 'G7 Friday must contain ILA blocks');
    }

    public function test_friday_g7_has_no_class_windows_unblocked(): void
    {
        // Every time window in the Tue-Fri timetable for G7 must be
        // either a structural non-CLASS slot OR an ILA block — nothing unblocked.
        $classSlotsInTimetable = SC::getClassSlots(7, 'Friday');
        $fixedSlots            = FixedSlotService::fixedSlotsForDay(7, 'G7G8', 'Friday');

        foreach ($classSlotsInTimetable as $cs) {
            $covered = false;
            foreach ($fixedSlots as $fs) {
                if ($fs['start'] <= $cs['start'] && $fs['end'] >= $cs['end']) {
                    $covered = true;
                    break;
                }
            }
            $this->assertTrue($covered,
                "G7 Friday class window {$cs['start']}–{$cs['end']} is not covered by any fixed slot"
            );
        }
    }

    public function test_friday_g9_has_no_ila(): void
    {
        $slots = FixedSlotService::fixedSlotsForDay(9, 'G9G10', 'Friday');
        $types = array_column($slots, 'type');

        $this->assertNotContains('ILA', $types, 'G9 Friday must NOT have ILA');
    }

    public function test_wednesday_has_wellness_block(): void
    {
        foreach ([7, 9, 11] as $grade) {
            $gradeGroup = SC::getGradeGroup($grade);
            $slots      = FixedSlotService::fixedSlotsForDay($grade, $gradeGroup, 'Wednesday');
            $types      = array_column($slots, 'type');

            $this->assertContains('WELLNESS', $types,
                "Grade {$grade} Wednesday must have WELLNESS block"
            );
        }
    }

    public function test_wednesday_has_activity_lock(): void
    {
        foreach ([7, 9, 11] as $grade) {
            $gradeGroup = SC::getGradeGroup($grade);
            $slots      = FixedSlotService::fixedSlotsForDay($grade, $gradeGroup, 'Wednesday');
            $types      = array_column($slots, 'type');

            $this->assertContains('ACTIVITY', $types,
                "Grade {$grade} Wednesday must have ACTIVITY lock"
            );
        }
    }

    public function test_wednesday_g11g12_activity_starts_at_12_20(): void
    {
        $slots    = FixedSlotService::fixedSlotsForDay(11, 'G11G12', 'Wednesday');
        $activity = array_values(array_filter($slots, fn ($s) => $s['type'] === 'ACTIVITY'));

        $this->assertNotEmpty($activity);
        $this->assertSame('12:20', $activity[0]['start'],
            'G11/G12 Wednesday ACTIVITY must start at 12:20'
        );
    }

    public function test_wednesday_g7g8_activity_starts_at_15_00(): void
    {
        $slots    = FixedSlotService::fixedSlotsForDay(7, 'G7G8', 'Wednesday');
        $activity = array_values(array_filter($slots, fn ($s) => $s['type'] === 'ACTIVITY'));

        $this->assertNotEmpty($activity);
        $this->assertSame('15:00', $activity[0]['start'],
            'G7/G8 Wednesday ACTIVITY must start at 15:00'
        );
    }

    public function test_fixed_slots_contain_no_class_type(): void
    {
        foreach ([7, 9, 11] as $grade) {
            $gradeGroup = SC::getGradeGroup($grade);
            foreach (SC::DAYS as $day) {
                $slots = FixedSlotService::fixedSlotsForDay($grade, $gradeGroup, $day);
                foreach ($slots as $slot) {
                    $this->assertNotSame('CLASS', $slot['type'],
                        "fixedSlotsForDay returned a CLASS slot for G{$grade} {$day}"
                    );
                }
            }
        }
    }

    // ── overlapsConstant ──────────────────────────────────────────────────────

    public function test_overlaps_constant_detects_lunch_overlap(): void
    {
        // G7 Tuesday lunch: 10:20–11:20
        $this->assertTrue(
            FixedSlotService::overlapsConstant(7, 'Tuesday', '10:00', '11:00'),
            'A window straddling G7 Tuesday lunch should be blocked'
        );
    }

    public function test_overlaps_constant_allows_class_period(): void
    {
        // G7 Tuesday Period 6: 14:10–15:00 — should NOT overlap any fixed slot
        $this->assertFalse(
            FixedSlotService::overlapsConstant(7, 'Tuesday', '14:10', '15:00'),
            'A valid G7 Tuesday CLASS period should not overlap any fixed slot'
        );
    }

    public function test_overlaps_constant_detects_g7_friday_ila(): void
    {
        // Any CLASS window on G7 Friday should be blocked by ILA
        $this->assertTrue(
            FixedSlotService::overlapsConstant(7, 'Friday', '07:30', '08:20'),
            'G7 Friday first CLASS window must be blocked by ILA'
        );
    }

    public function test_overlaps_constant_detects_monday_dead_zone_g7(): void
    {
        // G7 Monday 08:50–09:40 is the DEAD zone
        $this->assertTrue(
            FixedSlotService::overlapsConstant(7, 'Monday', '08:50', '09:40'),
            'G7 Monday dead zone 08:50–09:40 should be blocked'
        );
    }

    // ── sealFixedSlots (DB) ───────────────────────────────────────────────────

    public function test_seal_fixed_slots_inserts_rows(): void
    {
        $sy = SchoolYear::factory()->create();

        // Create one section per grade
        foreach ([7, 9, 11] as $grade) {
            Section::factory()->create([
                'syid'      => $sy->id,
                'levelid'   => $grade,
                'is_active' => true,
            ]);
        }

        $count = $this->service->sealFixedSlots($sy->id);

        $this->assertGreaterThan(0, $count, 'sealFixedSlots should insert rows');
        $this->assertSame($count, (int) SectionFixedSlot::where('school_year_id', $sy->id)->count());
    }

    public function test_seal_fixed_slots_is_idempotent(): void
    {
        $sy = SchoolYear::factory()->create();
        Section::factory()->create(['syid' => $sy->id, 'levelid' => 7, 'is_active' => true]);

        $first  = $this->service->sealFixedSlots($sy->id);
        $second = $this->service->sealFixedSlots($sy->id);

        $this->assertSame($first, $second, 'Running sealFixedSlots twice should produce same count');
        $this->assertSame(
            $second,
            (int) SectionFixedSlot::where('school_year_id', $sy->id)->count(),
            'DB row count after second run must equal returned count'
        );
    }

    public function test_sealed_slots_have_no_class_type_in_db(): void
    {
        $sy = SchoolYear::factory()->create();
        Section::factory()->create(['syid' => $sy->id, 'levelid' => 8, 'is_active' => true]);
        $this->service->sealFixedSlots($sy->id);

        $classRows = SectionFixedSlot::where('school_year_id', $sy->id)
            ->where('slot_type', 'CLASS')
            ->count();

        $this->assertSame(0, $classRows, 'No row in section_fixed_slots should have slot_type=CLASS');
    }

    public function test_is_blocked_returns_true_for_lunch(): void
    {
        $sy      = SchoolYear::factory()->create();
        $section = Section::factory()->create(['syid' => $sy->id, 'levelid' => 7, 'is_active' => true]);
        $this->service->sealFixedSlots($sy->id);

        // G7 Tuesday lunch: 10:20–11:20
        $this->assertTrue(
            $this->service->isBlocked($section->id, 'Tuesday', '10:20', '11:20'),
            'isBlocked must return true for G7 Tuesday lunch window'
        );
    }

    public function test_is_blocked_returns_false_for_valid_class_window(): void
    {
        $sy      = SchoolYear::factory()->create();
        $section = Section::factory()->create(['syid' => $sy->id, 'levelid' => 7, 'is_active' => true]);
        $this->service->sealFixedSlots($sy->id);

        // G7 Tuesday Period 7: 15:00–15:50 (CLASS slot from timetable)
        $this->assertFalse(
            $this->service->isBlocked($section->id, 'Tuesday', '15:00', '15:50'),
            'isBlocked must return false for a valid G7 Tuesday class window'
        );
    }
}
