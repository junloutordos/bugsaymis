<?php

namespace Tests\Feature\FacultyLoading;

use App\Services\FacultyLoading\ConflictResolverService;
use App\Services\FacultyLoading\ConflictDetectionService;
use App\Services\FacultyLoading\FullConstraintValidator;
use App\Services\FacultyLoading\SchedulingConstants;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * ConflictResolverServiceTest
 *
 * Covers:
 *   – getResolutionStrategy: empty, all-H14, single H1, H9, mixed H14+H1
 *   – findNextValidSlot: constant-only scan
 *   – resolveSlotBump: moves past failing slot, skips exclusions
 *   – resolveRoomSwap: skips same room, tries alternatives, null on none
 *   – resolve: orchestration — H14→room_swap then slot_bump; H1→slot_bump; empty→null
 *   – describeViolations: formatting
 */
class ConflictResolverServiceTest extends TestCase
{
    use \Illuminate\Foundation\Testing\DatabaseTransactions;

    private const DUMMY_ID = 88888;

    private ConflictResolverService $resolver;
    private FullConstraintValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $conflicts      = app(ConflictDetectionService::class);
        $this->validator = new FullConstraintValidator($conflicts);
        $this->resolver  = new ConflictResolverService($this->validator);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /** Wrap callable in FK-check disabled block */
    private function withoutFKChecks(callable $fn): mixed
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            return $fn();
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    /** Insert a class_schedule row; returns inserted ID */
    private function insertSchedule(array $attrs): int
    {
        // Map proposal's 'faculty_id' key → actual DB column 'user_id'
        if (isset($attrs['faculty_id']) && ! isset($attrs['user_id'])) {
            $attrs['user_id'] = $attrs['faculty_id'];
            unset($attrs['faculty_id']);
        }

        $defaults = [
            'academic_term_id' => self::DUMMY_ID,
            'school_year_id'   => self::DUMMY_ID,
            'user_id'          => self::DUMMY_ID,
            'section_id'       => self::DUMMY_ID,
            'classroom_id'     => self::DUMMY_ID,
            'subject_id'       => self::DUMMY_ID,
            'day_of_week'      => 'Monday',
            'start_time'       => '07:30',
            'end_time'         => '08:20',
            'status'           => 'active',
            'created_at'       => now(),
            'updated_at'       => now(),
        ];

        return $this->withoutFKChecks(fn () =>
            DB::table('class_schedules')->insertGetId(array_merge($defaults, $attrs))
        );
    }

    /** Insert a teacher_official_times row */
    private function insertOfficialTime(string $day, string $start, string $end, int $userId = self::DUMMY_ID): void
    {
        $this->withoutFKChecks(fn () =>
            DB::table('teacher_official_times')->insert([
                'user_id'     => $userId,
                'day_of_week' => $day,
                'start_time'  => $start,
                'end_time'    => $end,
                'created_at'  => now(),
                'updated_at'  => now(),
            ])
        );
    }

    /** Return a minimal valid G8 proposal (no DB IDs — passes constant checks) */
    private function baseProposal(array $overrides = []): array
    {
        return array_merge([
            'grade'            => 8,
            'day_of_week'      => 'Tuesday',
            'start_time'       => '07:30',
            'end_time'         => '08:20',
            'academic_term_id' => null,
            'faculty_id'       => null,
            'section_id'       => null,
            'classroom_id'     => null,
        ], $overrides);
    }

    /** Build a simple ordered candidate slot list for a grade/day */
    private function candidateSlotsForDay(int $grade, string $day): array
    {
        return array_map(
            fn ($s) => array_merge($s, ['day' => $day]),
            SchedulingConstants::getClassSlots($grade, $day)
        );
    }

    /** Get all candidate slots across the full week for a grade */
    private function allWeekSlots(int $grade): array
    {
        $days  = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
        $slots = [];
        foreach ($days as $day) {
            foreach (SchedulingConstants::getClassSlots($grade, $day) as $s) {
                $slots[] = array_merge($s, ['day' => $day]);
            }
        }
        return $slots;
    }

    // =========================================================================
    // getResolutionStrategy
    // =========================================================================

    /** @test */
    public function test_strategy_is_unresolvable_when_violations_empty(): void
    {
        $strategy = $this->resolver->getResolutionStrategy([]);
        $this->assertSame(ConflictResolverService::STRATEGY_UNRESOLVABLE, $strategy);
    }

    /** @test */
    public function test_strategy_is_room_swap_when_all_h14(): void
    {
        $violations = [
            ['code' => 'H14', 'reason' => 'H14: Room conflict…'],
            ['code' => 'H14', 'reason' => 'H14: Room conflict…'],
        ];
        $strategy = $this->resolver->getResolutionStrategy($violations);
        $this->assertSame(ConflictResolverService::STRATEGY_ROOM_SWAP, $strategy);
    }

    /** @test */
    public function test_strategy_is_room_swap_for_single_h14(): void
    {
        $violations = [['code' => 'H14', 'reason' => 'H14: Room conflict…']];
        $this->assertSame(
            ConflictResolverService::STRATEGY_ROOM_SWAP,
            $this->resolver->getResolutionStrategy($violations)
        );
    }

    /** @test */
    public function test_strategy_is_slot_bump_for_h1(): void
    {
        $violations = [['code' => 'H1', 'reason' => 'H1: Teacher conflict…']];
        $this->assertSame(
            ConflictResolverService::STRATEGY_SLOT_BUMP,
            $this->resolver->getResolutionStrategy($violations)
        );
    }

    /** @test */
    public function test_strategy_is_slot_bump_for_h9(): void
    {
        $violations = [['code' => 'H9', 'reason' => 'H9: Monday dead zone…']];
        $this->assertSame(
            ConflictResolverService::STRATEGY_SLOT_BUMP,
            $this->resolver->getResolutionStrategy($violations)
        );
    }

    /** @test */
    public function test_strategy_is_slot_bump_when_mixed_h14_and_h1(): void
    {
        $violations = [
            ['code' => 'H14', 'reason' => 'H14: Room conflict…'],
            ['code' => 'H1',  'reason' => 'H1: Teacher conflict…'],
        ];
        $this->assertSame(
            ConflictResolverService::STRATEGY_SLOT_BUMP,
            $this->resolver->getResolutionStrategy($violations)
        );
    }

    /** @test */
    public function test_strategy_is_slot_bump_for_h2(): void
    {
        $violations = [['code' => 'H2', 'reason' => 'H2: Section conflict…']];
        $this->assertSame(
            ConflictResolverService::STRATEGY_SLOT_BUMP,
            $this->resolver->getResolutionStrategy($violations)
        );
    }

    /** @test */
    public function test_strategy_is_slot_bump_for_h3(): void
    {
        $violations = [['code' => 'H3', 'reason' => 'H3: Outside official time…']];
        $this->assertSame(
            ConflictResolverService::STRATEGY_SLOT_BUMP,
            $this->resolver->getResolutionStrategy($violations)
        );
    }

    // =========================================================================
    // findNextValidSlot (constant-only, no DB)
    // =========================================================================

    /** @test */
    public function test_find_next_valid_slot_returns_first_valid(): void
    {
        $slots  = $this->allWeekSlots(8);
        $result = $this->resolver->findNextValidSlot(8, $slots);

        $this->assertNotNull($result);
        $this->assertArrayHasKey('day',   $result);
        $this->assertArrayHasKey('start', $result);
        $this->assertArrayHasKey('end',   $result);
    }

    /** @test */
    public function test_find_next_valid_slot_skips_excluded_keys(): void
    {
        $slots  = $this->allWeekSlots(8);
        $first  = $this->resolver->findNextValidSlot(8, $slots);
        $this->assertNotNull($first);

        // Exclude the first result, expect a different one
        $excluded = [$first['day'] . '|' . $first['start']];
        $second   = $this->resolver->findNextValidSlot(8, $slots, $excluded);

        $this->assertNotNull($second);
        $this->assertNotSame($first['start'], $second['start'] . $second['day']);
        $this->assertNotContains($second['day'] . '|' . $second['start'], $excluded);
    }

    /** @test */
    public function test_find_next_valid_slot_skips_monday_dead_zone_for_g7(): void
    {
        // G7 Monday dead zone: 08:50–09:40 — this slot should never be returned for G7
        $slots = $this->allWeekSlots(7);

        // Collect ALL valid slots to confirm dead zone never appears
        $found   = [];
        $claimed = [];
        while (true) {
            $slot = $this->resolver->findNextValidSlot(7, $slots, $claimed);
            if ($slot === null) {
                break;
            }
            $found[]   = $slot;
            $claimed[] = $slot['day'] . '|' . $slot['start'];
        }

        $deadZoneTimes = ['08:50', '09:40'];
        foreach ($found as $f) {
            if ($f['day'] === 'Monday') {
                $this->assertNotContains($f['start'], $deadZoneTimes,
                    "findNextValidSlot returned Monday dead zone slot for G7: {$f['start']}");
            }
        }
    }

    /** @test */
    public function test_find_next_valid_slot_returns_null_when_all_excluded(): void
    {
        $slots    = $this->candidateSlotsForDay(8, 'Tuesday');
        $excluded = array_map(fn ($s) => $s['day'] . '|' . $s['start'], $slots);

        $result = $this->resolver->findNextValidSlot(8, $slots, $excluded);
        $this->assertNull($result);
    }

    /** @test */
    public function test_find_next_valid_slot_returns_null_for_empty_slots(): void
    {
        $result = $this->resolver->findNextValidSlot(8, []);
        $this->assertNull($result);
    }

    // =========================================================================
    // resolveSlotBump (uses validator, requires DB-clean environment)
    // =========================================================================

    /** @test */
    public function test_resolve_slot_bump_returns_first_valid_slot(): void
    {
        // No DB IDs in proposal → only constant checks run
        $slots    = $this->allWeekSlots(8);
        $proposal = $this->baseProposal([
            'day_of_week' => 'Tuesday',
            'start_time'  => '07:30',
            'end_time'    => '08:20',
        ]);

        // Exclude the first slot so bump should find the second
        $exclude = ['Tuesday|07:30'];
        $result  = $this->resolver->resolveSlotBump($proposal, $slots, $exclude);

        $this->assertNotNull($result);
        $this->assertNotSame('07:30', $result['start_time']);
        $this->assertArrayHasKey('day_of_week', $result);
        $this->assertArrayHasKey('start_time',  $result);
        $this->assertArrayHasKey('end_time',    $result);
    }

    /** @test */
    public function test_resolve_slot_bump_excludes_the_failing_slot_automatically(): void
    {
        $slots    = $this->allWeekSlots(8);
        $proposal = $this->baseProposal([
            'day_of_week' => 'Tuesday',
            'start_time'  => '07:30',
            'end_time'    => '08:20',
        ]);

        $result = $this->resolver->resolveSlotBump($proposal, $slots);

        // The failing slot (Tuesday 07:30) must not be returned
        $this->assertNotNull($result);
        $failingKey   = 'Tuesday|07:30';
        $resolvedKey  = $result['day_of_week'] . '|' . $result['start_time'];
        $this->assertNotSame($failingKey, $resolvedKey);
    }

    /** @test */
    public function test_resolve_slot_bump_skips_external_excluded_keys(): void
    {
        $slots    = $this->allWeekSlots(8);
        $proposal = $this->baseProposal([
            'day_of_week' => 'Tuesday',
            'start_time'  => '07:30',
            'end_time'    => '08:20',
        ]);

        // Collect first 3 valid slots (excluding the proposal's slot), add to exclusions
        $firstValid = null;
        $excluded   = ['Tuesday|07:30'];
        foreach ($slots as $s) {
            $key = $s['day'] . '|' . $s['start'];
            if (! in_array($key, $excluded, true)) {
                $check = $this->validator->check(array_merge($proposal, [
                    'day_of_week' => $s['day'],
                    'start_time'  => $s['start'],
                    'end_time'    => $s['end'],
                ]));
                if ($check['passes']) {
                    $firstValid = $key;
                    $excluded[] = $key;
                    break;
                }
            }
        }

        $this->assertNotNull($firstValid, 'Could not find a valid slot to exclude');

        $result = $this->resolver->resolveSlotBump($proposal, $slots, $excluded);
        if ($result !== null) {
            $resolvedKey = $result['day_of_week'] . '|' . $result['start_time'];
            $this->assertNotContains($resolvedKey, $excluded);
        }
        // Either null (ran out) or skipped excluded — both valid
    }

    /** @test */
    public function test_resolve_slot_bump_returns_null_when_all_slots_exhausted(): void
    {
        // Provide only 1 candidate slot — same as the failing proposal
        $proposal = $this->baseProposal([
            'day_of_week' => 'Tuesday',
            'start_time'  => '07:30',
            'end_time'    => '08:20',
        ]);

        $onlySlot = [['day' => 'Tuesday', 'start' => '07:30', 'end' => '08:20', 'label' => 'P1']];

        $result = $this->resolver->resolveSlotBump($proposal, $onlySlot);
        $this->assertNull($result);
    }

    /** @test */
    public function test_resolve_slot_bump_fails_when_db_conflict_on_every_slot(): void
    {
        // Insert a teacher schedule that covers ALL Tuesday slots
        $termId    = self::DUMMY_ID;
        $facultyId = self::DUMMY_ID + 1;

        $tuesdaySlots = $this->candidateSlotsForDay(8, 'Tuesday');
        foreach ($tuesdaySlots as $s) {
            $this->insertSchedule([
                'academic_term_id' => $termId,
                'faculty_id'       => $facultyId,
                'day_of_week'      => 'Tuesday',
                'start_time'       => $s['start'],
                'end_time'         => $s['end'],
            ]);
        }

        $proposal = $this->baseProposal([
            'grade'            => 8,
            'academic_term_id' => $termId,
            'faculty_id'       => $facultyId,
            'day_of_week'      => 'Tuesday',
            'start_time'       => '07:30',
            'end_time'         => '08:20',
        ]);

        // Only provide Tuesday slots — all conflicted → null
        $result = $this->resolver->resolveSlotBump($proposal, $tuesdaySlots);
        $this->assertNull($result);
    }

    /** @test */
    public function test_resolve_slot_bump_resolves_when_second_slot_is_free(): void
    {
        $termId    = self::DUMMY_ID;
        $facultyId = self::DUMMY_ID + 2;

        // Block only the first Tuesday slot for this teacher
        $this->insertSchedule([
            'academic_term_id' => $termId,
            'faculty_id'       => $facultyId,
            'day_of_week'      => 'Tuesday',
            'start_time'       => '07:30',
            'end_time'         => '08:20',
        ]);

        $proposal = $this->baseProposal([
            'grade'            => 8,
            'academic_term_id' => $termId,
            'faculty_id'       => $facultyId,
            'day_of_week'      => 'Tuesday',
            'start_time'       => '07:30',
            'end_time'         => '08:20',
        ]);

        $slots  = $this->candidateSlotsForDay(8, 'Tuesday');
        $result = $this->resolver->resolveSlotBump($proposal, $slots);

        $this->assertNotNull($result);
        $this->assertSame('Tuesday', $result['day_of_week']);
        $this->assertNotSame('07:30', $result['start_time']); // not the blocked slot
    }

    // =========================================================================
    // resolveRoomSwap
    // =========================================================================

    /** @test */
    public function test_resolve_room_swap_skips_current_room(): void
    {
        $termId      = self::DUMMY_ID;
        $classroomId = self::DUMMY_ID;

        $proposal = $this->baseProposal([
            'academic_term_id' => $termId,
            'classroom_id'     => $classroomId,
        ]);

        // Only alternative is the same room — should return null
        $result = $this->resolver->resolveRoomSwap($proposal, [$classroomId]);
        $this->assertNull($result);
    }

    /** @test */
    public function test_resolve_room_swap_returns_null_when_no_alternatives(): void
    {
        $proposal = $this->baseProposal(['classroom_id' => self::DUMMY_ID]);
        $result   = $this->resolver->resolveRoomSwap($proposal, []);
        $this->assertNull($result);
    }

    /** @test */
    public function test_resolve_room_swap_returns_proposal_with_free_room(): void
    {
        $termId       = self::DUMMY_ID;
        $blockedRoom  = self::DUMMY_ID + 10;
        $freeRoom     = self::DUMMY_ID + 11;

        // Book the blocked room on Tuesday 07:30–08:20
        $this->insertSchedule([
            'academic_term_id' => $termId,
            'classroom_id'     => $blockedRoom,
            'day_of_week'      => 'Tuesday',
            'start_time'       => '07:30',
            'end_time'         => '08:20',
        ]);

        // Failing proposal uses the blocked room
        $proposal = $this->baseProposal([
            'grade'            => 8,
            'academic_term_id' => $termId,
            'classroom_id'     => $blockedRoom,
            'day_of_week'      => 'Tuesday',
            'start_time'       => '07:30',
            'end_time'         => '08:20',
        ]);

        // freeRoom has no bookings → should succeed
        $result = $this->resolver->resolveRoomSwap($proposal, [$blockedRoom, $freeRoom]);

        $this->assertNotNull($result);
        $this->assertSame($freeRoom, $result['classroom_id']);
        // Timeslot preserved
        $this->assertSame('Tuesday', $result['day_of_week']);
        $this->assertSame('07:30',   $result['start_time']);
        $this->assertSame('08:20',   $result['end_time']);
    }

    /** @test */
    public function test_resolve_room_swap_returns_null_when_all_rooms_blocked(): void
    {
        $termId   = self::DUMMY_ID;
        $room1    = self::DUMMY_ID + 20;
        $room2    = self::DUMMY_ID + 21;

        // Book both rooms on Tuesday 07:30–08:20
        foreach ([$room1, $room2] as $roomId) {
            $this->insertSchedule([
                'academic_term_id' => $termId,
                'classroom_id'     => $roomId,
                'day_of_week'      => 'Tuesday',
                'start_time'       => '07:30',
                'end_time'         => '08:20',
            ]);
        }

        $proposal = $this->baseProposal([
            'grade'            => 8,
            'academic_term_id' => $termId,
            'classroom_id'     => $room1,
        ]);

        $result = $this->resolver->resolveRoomSwap($proposal, [$room1, $room2]);
        $this->assertNull($result);
    }

    // =========================================================================
    // resolve (orchestration)
    // =========================================================================

    /** @test */
    public function test_resolve_returns_null_when_violations_empty(): void
    {
        $proposal = $this->baseProposal();
        $result   = $this->resolver->resolve($proposal, [], $this->allWeekSlots(8));
        $this->assertNull($result);
    }

    /** @test */
    public function test_resolve_uses_slot_bump_for_h1_violation(): void
    {
        $termId    = self::DUMMY_ID;
        $facultyId = self::DUMMY_ID + 3;

        // Block Tuesday 07:30–08:20 for teacher
        $this->insertSchedule([
            'academic_term_id' => $termId,
            'faculty_id'       => $facultyId,
            'day_of_week'      => 'Tuesday',
            'start_time'       => '07:30',
            'end_time'         => '08:20',
        ]);

        $proposal = $this->baseProposal([
            'grade'            => 8,
            'academic_term_id' => $termId,
            'faculty_id'       => $facultyId,
            'day_of_week'      => 'Tuesday',
            'start_time'       => '07:30',
            'end_time'         => '08:20',
        ]);

        $violations = [['code' => 'H1', 'reason' => 'H1: Teacher conflict…']];
        $slots      = $this->allWeekSlots(8);

        $result = $this->resolver->resolve($proposal, $violations, $slots);

        $this->assertNotNull($result);
        $resolvedKey = $result['day_of_week'] . '|' . $result['start_time'];
        $this->assertNotSame('Tuesday|07:30', $resolvedKey);
    }

    /** @test */
    public function test_resolve_tries_room_swap_first_for_h14_violation(): void
    {
        $termId      = self::DUMMY_ID;
        $blockedRoom = self::DUMMY_ID + 30;
        $freeRoom    = self::DUMMY_ID + 31;

        $this->insertSchedule([
            'academic_term_id' => $termId,
            'classroom_id'     => $blockedRoom,
            'day_of_week'      => 'Tuesday',
            'start_time'       => '07:30',
            'end_time'         => '08:20',
        ]);

        $proposal = $this->baseProposal([
            'grade'            => 8,
            'academic_term_id' => $termId,
            'classroom_id'     => $blockedRoom,
            'day_of_week'      => 'Tuesday',
            'start_time'       => '07:30',
            'end_time'         => '08:20',
        ]);

        $violations = [['code' => 'H14', 'reason' => 'H14: Room conflict…']];
        $slots      = $this->allWeekSlots(8);

        $result = $this->resolver->resolve(
            proposal:         $proposal,
            violations:       $violations,
            candidateSlots:   $slots,
            alternativeRooms: [$blockedRoom, $freeRoom]
        );

        $this->assertNotNull($result);
        // Room swap should keep the same timeslot but swap the room
        $this->assertSame('Tuesday', $result['day_of_week']);
        $this->assertSame('07:30',   $result['start_time']);
        $this->assertSame($freeRoom, $result['classroom_id']);
    }

    /** @test */
    public function test_resolve_falls_back_to_slot_bump_when_room_swap_fails(): void
    {
        $termId   = self::DUMMY_ID;
        $room1    = self::DUMMY_ID + 40;
        $room2    = self::DUMMY_ID + 41;

        // Block both rooms at Tuesday 07:30–08:20
        foreach ([$room1, $room2] as $r) {
            $this->insertSchedule([
                'academic_term_id' => $termId,
                'classroom_id'     => $r,
                'day_of_week'      => 'Tuesday',
                'start_time'       => '07:30',
                'end_time'         => '08:20',
            ]);
        }

        $proposal = $this->baseProposal([
            'grade'            => 8,
            'academic_term_id' => $termId,
            'classroom_id'     => $room1,
            'day_of_week'      => 'Tuesday',
            'start_time'       => '07:30',
            'end_time'         => '08:20',
        ]);

        $violations = [['code' => 'H14', 'reason' => 'H14: Room conflict…']];
        $slots      = $this->allWeekSlots(8);

        $result = $this->resolver->resolve(
            proposal:         $proposal,
            violations:       $violations,
            candidateSlots:   $slots,
            alternativeRooms: [$room1, $room2]
        );

        // Room swap fails → slot bump should find a different timeslot
        // (room conflict only if same room; slot_bump keeps room1 but at a
        //  different time where it isn't booked)
        $this->assertNotNull($result);
        $resolvedKey = $result['day_of_week'] . '|' . $result['start_time'];
        $this->assertNotSame('Tuesday|07:30', $resolvedKey);
    }

    /** @test */
    public function test_resolve_returns_null_when_completely_unresolvable(): void
    {
        $termId    = self::DUMMY_ID;
        $facultyId = self::DUMMY_ID + 5;

        // Block ALL slots in the week for this teacher
        foreach ($this->allWeekSlots(8) as $s) {
            $this->insertSchedule([
                'academic_term_id' => $termId,
                'faculty_id'       => $facultyId,
                'section_id'       => self::DUMMY_ID + 99,
                'day_of_week'      => $s['day'],
                'start_time'       => $s['start'],
                'end_time'         => $s['end'],
            ]);
        }

        $proposal = $this->baseProposal([
            'grade'            => 8,
            'academic_term_id' => $termId,
            'faculty_id'       => $facultyId,
            'day_of_week'      => 'Tuesday',
            'start_time'       => '07:30',
            'end_time'         => '08:20',
        ]);

        $violations = [['code' => 'H1', 'reason' => 'H1: Teacher conflict…']];
        $slots      = $this->allWeekSlots(8);

        $result = $this->resolver->resolve($proposal, $violations, $slots);
        $this->assertNull($result);
    }

    /** @test */
    public function test_resolve_room_swap_not_attempted_without_alternative_rooms(): void
    {
        $termId      = self::DUMMY_ID;
        $blockedRoom = self::DUMMY_ID + 50;

        $this->insertSchedule([
            'academic_term_id' => $termId,
            'classroom_id'     => $blockedRoom,
            'day_of_week'      => 'Tuesday',
            'start_time'       => '07:30',
            'end_time'         => '08:20',
        ]);

        $proposal = $this->baseProposal([
            'grade'            => 8,
            'academic_term_id' => $termId,
            'classroom_id'     => $blockedRoom,
            'day_of_week'      => 'Tuesday',
            'start_time'       => '07:30',
            'end_time'         => '08:20',
        ]);

        $violations = [['code' => 'H14', 'reason' => 'H14: Room conflict…']];
        $slots      = $this->allWeekSlots(8);

        // No alternativeRooms → falls straight to slot_bump
        $result = $this->resolver->resolve(
            proposal:       $proposal,
            violations:     $violations,
            candidateSlots: $slots
            // alternativeRooms defaults to []
        );

        $this->assertNotNull($result);
        // Slot bump should have moved to a different timeslot
        $resolvedKey = $result['day_of_week'] . '|' . $result['start_time'];
        $this->assertNotSame('Tuesday|07:30', $resolvedKey);
    }

    // =========================================================================
    // describeViolations
    // =========================================================================

    /** @test */
    public function test_describe_violations_returns_no_violations_when_empty(): void
    {
        $output = $this->resolver->describeViolations([]);
        $this->assertSame('No violations.', $output);
    }

    /** @test */
    public function test_describe_violations_formats_single_violation(): void
    {
        $violations = [['code' => 'H1', 'reason' => 'H1: Teacher conflict on Monday']];
        $output     = $this->resolver->describeViolations($violations);

        $this->assertStringContainsString('[H1]', $output);
        $this->assertStringContainsString('Teacher conflict', $output);
    }

    /** @test */
    public function test_describe_violations_joins_multiple_with_pipe(): void
    {
        $violations = [
            ['code' => 'H1',  'reason' => 'H1: Teacher conflict'],
            ['code' => 'H14', 'reason' => 'H14: Room conflict'],
        ];
        $output = $this->resolver->describeViolations($violations);

        $this->assertStringContainsString('[H1]',  $output);
        $this->assertStringContainsString('[H14]', $output);
        $this->assertStringContainsString(' | ',   $output);
    }

    /** @test */
    public function test_describe_violations_includes_all_violations(): void
    {
        $violations = [
            ['code' => 'H2', 'reason' => 'H2: Section conflict'],
            ['code' => 'H3', 'reason' => 'H3: Outside official time'],
            ['code' => 'H9', 'reason' => 'H9: Monday dead zone'],
        ];
        $output = $this->resolver->describeViolations($violations);

        $this->assertStringContainsString('[H2]', $output);
        $this->assertStringContainsString('[H3]', $output);
        $this->assertStringContainsString('[H9]', $output);
    }
}
