<?php

namespace Tests\Feature\FacultyLoading;

use App\Services\FacultyLoading\ConflictDetectionService;
use Tests\TestCase;

/**
 * Unit tests for ConflictDetectionService.
 *
 * Tests the core time-overlap logic and validates that conflict detection
 * properly applies the interval rule:
 *   (startA < endB) AND (endA > startB)
 *
 * DB-dependent conflict query tests are covered in ConflictIntegrationTest.
 */
class ConflictDetectionTest extends TestCase
{
    private ConflictDetectionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ConflictDetectionService();
    }

    // ── Time Overlap Rule ────────────────────────────────────────────────────

    /** @test */
    public function it_detects_full_overlap(): void
    {
        // B entirely inside A
        $this->assertTrue($this->service->timesOverlap('08:00', '12:00', '09:00', '11:00'));
    }

    /** @test */
    public function it_detects_partial_overlap_start(): void
    {
        // B starts before A ends
        $this->assertTrue($this->service->timesOverlap('08:00', '10:00', '09:00', '11:00'));
    }

    /** @test */
    public function it_detects_partial_overlap_end(): void
    {
        // A starts before B ends
        $this->assertTrue($this->service->timesOverlap('09:00', '11:00', '08:00', '10:00'));
    }

    /** @test */
    public function it_detects_identical_slots_as_overlap(): void
    {
        $this->assertTrue($this->service->timesOverlap('08:00', '10:00', '08:00', '10:00'));
    }

    /** @test */
    public function it_detects_a_contained_within_b_as_overlap(): void
    {
        $this->assertTrue($this->service->timesOverlap('09:00', '10:00', '08:00', '11:00'));
    }

    /** @test */
    public function it_does_not_flag_non_overlapping_consecutive_slots(): void
    {
        // A ends exactly when B starts — touching boundary, NOT a conflict
        $this->assertFalse($this->service->timesOverlap('08:00', '09:00', '09:00', '10:00'));
        $this->assertFalse($this->service->timesOverlap('09:00', '10:00', '08:00', '09:00'));
    }

    /** @test */
    public function it_does_not_flag_clearly_separated_slots(): void
    {
        $this->assertFalse($this->service->timesOverlap('07:00', '08:00', '10:00', '12:00'));
        $this->assertFalse($this->service->timesOverlap('13:00', '15:00', '08:00', '10:00'));
    }

    /** @test */
    public function it_handles_one_minute_gap_between_slots(): void
    {
        // A: 08:00–10:00, B: 10:01–12:00 → no conflict
        $this->assertFalse($this->service->timesOverlap('08:00', '10:00', '10:01', '12:00'));
    }

    /** @test */
    public function it_handles_one_minute_overlap(): void
    {
        // A: 08:00–10:01, B: 10:00–12:00 → 1 minute overlap → conflict
        $this->assertTrue($this->service->timesOverlap('08:00', '10:01', '10:00', '12:00'));
    }

    /** @test */
    public function it_handles_seconds_in_time_strings(): void
    {
        // 08:00:00 – 09:00:00 vs 09:00:00 – 10:00:00 → touching, not a conflict
        $this->assertFalse($this->service->timesOverlap('08:00:00', '09:00:00', '09:00:00', '10:00:00'));
        // 08:00:00 – 09:00:01 vs 09:00:00 – 10:00:00 → 1 second overlap → conflict
        $this->assertTrue($this->service->timesOverlap('08:00:00', '09:00:01', '09:00:00', '10:00:00'));
    }

    // ── Typical PSHS Schedule Patterns ──────────────────────────────────────

    /** @test */
    public function standard_morning_and_afternoon_blocks_do_not_conflict(): void
    {
        // Common PSHS pattern: 07:30–12:00 AM block, 13:00–17:00 PM block
        $this->assertFalse($this->service->timesOverlap('07:30', '12:00', '13:00', '17:00'));
        $this->assertFalse($this->service->timesOverlap('13:00', '17:00', '07:30', '12:00'));
    }

    /** @test */
    public function consecutive_class_periods_do_not_conflict(): void
    {
        // Period 1: 07:30–08:30, Period 2: 08:30–09:30
        $this->assertFalse($this->service->timesOverlap('07:30', '08:30', '08:30', '09:30'));
    }

    /** @test */
    public function classes_with_shared_30_minute_window_are_flagged(): void
    {
        // Class A: 08:00–10:00, Class B: 09:30–11:30 → 30-min overlap
        $this->assertTrue($this->service->timesOverlap('08:00', '10:00', '09:30', '11:30'));
    }

    // ── Symmetry ─────────────────────────────────────────────────────────────

    /** @test */
    public function overlap_check_is_symmetric(): void
    {
        // If A overlaps B, then B must overlap A
        $pairsOverlap = [
            ['08:00', '10:00', '09:00', '11:00'],
            ['07:30', '09:00', '08:00', '09:30'],
            ['10:00', '12:00', '11:00', '13:00'],
        ];
        foreach ($pairsOverlap as [$sA, $eA, $sB, $eB]) {
            $this->assertTrue(
                $this->service->timesOverlap($sA, $eA, $sB, $eB),
                "Expected overlap for {$sA}–{$eA} vs {$sB}–{$eB}"
            );
            $this->assertTrue(
                $this->service->timesOverlap($sB, $eB, $sA, $eA),
                "Expected symmetric overlap for {$sB}–{$eB} vs {$sA}–{$eA}"
            );
        }

        $pairsNoOverlap = [
            ['08:00', '09:00', '09:00', '10:00'],
            ['07:00', '08:00', '10:00', '12:00'],
        ];
        foreach ($pairsNoOverlap as [$sA, $eA, $sB, $eB]) {
            $this->assertFalse(
                $this->service->timesOverlap($sA, $eA, $sB, $eB),
                "Expected no overlap for {$sA}–{$eA} vs {$sB}–{$eB}"
            );
            $this->assertFalse(
                $this->service->timesOverlap($sB, $eB, $sA, $eA),
                "Expected symmetric no-overlap for {$sB}–{$eB} vs {$sA}–{$eA}"
            );
        }
    }

    // ── Boundary Precision ───────────────────────────────────────────────────

    /** @test */
    public function it_handles_midnight_boundary(): void
    {
        $this->assertFalse($this->service->timesOverlap('00:00', '01:00', '01:00', '02:00'));
        $this->assertTrue($this->service->timesOverlap('00:00', '01:01', '01:00', '02:00'));
    }

    /** @test */
    public function it_handles_end_of_day_times(): void
    {
        $this->assertFalse($this->service->timesOverlap('17:00', '18:00', '18:00', '19:00'));
        $this->assertTrue($this->service->timesOverlap('17:00', '18:30', '18:00', '19:00'));
    }

    /** @test */
    public function zero_length_slot_does_not_conflict_with_adjacent_slot(): void
    {
        // Zero-length slot at 09:00 should not overlap 08:00–09:00 or 09:00–10:00
        $this->assertFalse($this->service->timesOverlap('09:00', '09:00', '08:00', '09:00'));
        $this->assertFalse($this->service->timesOverlap('09:00', '09:00', '09:00', '10:00'));
    }
}
