<?php

namespace App\Services\FacultyLoading;

use App\Models\FacultyLoading\Classroom;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\Section;

/**
 * DeterministicSchedulingService
 *
 * Constraint-based weekly class-schedule generator. Replaces the genetic
 * algorithm (which only soft-penalised conflicts and emitted best-effort,
 * conflicted results). This service places every teaching session onto the
 * canonical per-grade period grid (see {@see SchedulingConstants}) such that:
 *
 *   • a section is never double-booked (one class per period per section)
 *   • a faculty member is never double-booked (no two classes at overlapping
 *     clock times on the same day, even across different grades)
 *   • placements only ever land on real CLASS periods — flag ceremony, homeroom
 *     / advising, recess, lunch and consultation windows are never used
 *   • Wednesday afternoon (post activity-cutoff) and Friday ILA days for the
 *     lower grades are excluded automatically
 *   • each section uses its own fixed room (sections.classroom_id), so room
 *     clashes are structurally impossible for homeroom sections
 *
 * Because placement is constraint-first, the output is conflict-free by
 * construction. When the weekly load exceeds the available periods (e.g. an
 * over-subscribed grade), the surplus sessions are reported as "unplaceable"
 * rather than being force-fit into a conflicting slot.
 */
class DeterministicSchedulingService
{
    private const DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

    /** Maximum displacement depth for the relocation (augmenting-path) search. */
    private const RELOCATION_DEPTH = 4;

    /**
     * Number of randomized restart attempts when sessions remain unplaced.
     * The loop exits as soon as a fully-placed (zero-unplaced) schedule is found,
     * so this cost is only paid for genuinely over-subscribed grades.
     */
    private const MAX_RESTARTS = 150;

    /** @var array<int,array<int,array<string,mixed>>> available slots per grade */
    private array $gridByGrade = [];
    /** @var array<int,array<string,array<int,array{0:int,1:int,2:int}>>> */
    private array $sectionBusy = [];
    /** @var array<int,array<string,array<int,array{0:int,1:int,2:int}>>> */
    private array $facultyBusy = [];
    /** @var array<int,array<string,int>> */
    private array $sectionDayCount = [];
    /** @var array<int,array<int,array<string,int>>> */
    private array $sectionSubjDays = [];
    /** @var array<int,?array{s:array<string,mixed>,slot:array<string,mixed>}> */
    private array $placements = [];

    /**
     * Generate a conflict-free schedule for the given term.
     *
     * @return array{
     *   fitness:int, hard_conflicts:int, schedules_generated:int,
     *   schedules:array<int,array<string,mixed>>, conflict_suggestions:array,
     *   unplaceable:array<int,array<string,mixed>>,
     *   section_report:array<int,array<string,mixed>>, warning:?string
     * }
     */
    public function generate(int $schoolYearId, int $termId, array $params = []): array
    {
        $requirements = $this->buildRequirements($schoolYearId, $termId);

        if (empty($requirements)) {
            return [
                'fitness'              => 0,
                'hard_conflicts'       => 0,
                'schedules_generated'  => 0,
                'schedules'            => [],
                'conflict_suggestions' => [],
                'unplaceable'          => [],
                'section_report'       => [],
                'warning'              => 'No teaching assignments with a subject and section were found for this term.',
            ];
        }

        // Pre-compute the available period grid per grade level (shared by all
        // sections of that grade).
        $this->gridByGrade = [];
        foreach (array_unique(array_column($requirements, 'grade')) as $grade) {
            $this->gridByGrade[$grade] = $this->buildSlotGrid((int) $grade);
        }

        // Flatten requirements into individual session instances.
        $baseSessions = [];
        foreach ($requirements as $req) {
            for ($i = 0; $i < $req['sessions_needed']; $i++) {
                $baseSessions[] = $req;
            }
        }

        // Attempt 0 — deterministic most-constrained-first ordering: place the
        // busiest faculty's sessions earliest so tight calendars are satisfied
        // before the grid fills up.
        $ordered = $baseSessions;
        usort($ordered, function ($a, $b) {
            return [$b['faculty_total'], $b['sessions_needed'], $a['section_id']]
                <=> [$a['faculty_total'], $a['sessions_needed'], $b['section_id']];
        });
        $best = $this->runPlacement($ordered, $schoolYearId, $termId);

        // Randomized restarts — when a near-fully-packed week leaves feasible
        // sessions unplaced, a different tie-break order often resolves them.
        // PHP's usort is stable, so shuffling first then sorting by the
        // constraint key keeps most-constrained-first while varying ties.
        for ($attempt = 1; $attempt <= self::MAX_RESTARTS && ! empty($best['unplaceable']); $attempt++) {
            mt_srand($attempt * 7919);
            $shuffled = $baseSessions;
            shuffle($shuffled);
            usort($shuffled, fn ($a, $b) =>
                [$b['faculty_total'], $b['sessions_needed']] <=> [$a['faculty_total'], $a['sessions_needed']]);

            $candidate = $this->runPlacement($shuffled, $schoolYearId, $termId);
            if (count($candidate['unplaceable']) < count($best['unplaceable'])) {
                $best = $candidate;
            }
        }

        $placed       = $best['placed'];
        $unplaceable  = $best['unplaceable'];

        return [
            'fitness'              => -count($unplaceable),
            'hard_conflicts'       => 0,
            'schedules_generated'  => count($placed),
            'schedules'            => $placed,
            'conflict_suggestions' => [],
            'unplaceable'          => $unplaceable,
            'section_report'       => $this->buildSectionReport($requirements, $placed),
            'warning'              => empty($unplaceable)
                ? null
                : count($unplaceable) . ' session(s) could not be placed (grade likely over-subscribed). See unplaceable report.',
        ];
    }

    /**
     * Run one full placement pass (greedy + relocation) over a session ordering,
     * resetting all scheduling state first. Returns the placed schedule rows and
     * the sessions that could not be placed.
     *
     * @param array<int,array<string,mixed>> $sessions
     * @return array{placed:array<int,array<string,mixed>>,unplaceable:array<int,array<string,mixed>>}
     */
    private function runPlacement(array $sessions, int $schoolYearId, int $termId): array
    {
        $this->sectionBusy     = [];
        $this->facultyBusy     = [];
        $this->sectionDayCount = [];
        $this->sectionSubjDays = [];
        $this->placements      = [];

        // Pass 1 — greedy placement.
        $deferred = [];
        foreach ($sessions as $s) {
            $slot = $this->findBestSlot($s);
            if ($slot === null) {
                $deferred[] = $s;
                continue;
            }
            $this->commit($s, $slot);
        }

        // Pass 2 — recursive relocation (bounded augmenting-path search).
        $unplaceable = [];
        foreach ($deferred as $s) {
            if (! $this->attemptPlace($s, self::RELOCATION_DEPTH)) {
                $unplaceable[] = $this->describeSession($s);
            }
        }

        $placed = [];
        foreach ($this->placements as $p) {
            if ($p !== null) {
                $placed[] = $this->toScheduleRow($p['s'], $p['slot'], $schoolYearId, $termId);
            }
        }

        return ['placed' => $placed, 'unplaceable' => $unplaceable];
    }

    // ── Requirements ────────────────────────────────────────────────────────

    /**
     * Build the list of teaching requirements grouped from load_assignments.
     * Synthetic elective sections (ELEC-*) are excluded — they use cross-section
     * elective windows that are handled separately.
     *
     * @return array<int,array<string,mixed>>
     */
    private function buildRequirements(int $schoolYearId, int $termId): array
    {
        $assignments = LoadAssignment::with(['subject', 'faculty:id,name'])
            ->where('academic_term_id', $termId)
            ->where('assignment_type', 'teaching')
            ->whereNotNull('subject_id')
            ->whereNotNull('section_id')
            ->get();

        // Sections for this school year, keyed by id (avoids a per-row query and
        // a model relationship dependency).
        $sections = Section::where('school_year_id', $schoolYearId)->get()->keyBy('id');

        // Classroom names, keyed by id (for display in the preview).
        $classroomNames = Classroom::pluck('name', 'id');

        // Faculty total teaching sessions (for most-constrained ordering).
        $facultyTotal = [];

        // Grades whose bell schedule reserves elective windows (and can therefore
        // schedule cross-section elective groups).
        $electiveGrades = $this->gradesWithElectiveWindows();

        $requirements = [];
        foreach ($assignments as $la) {
            $section = $sections->get($la->section_id);
            if (! $section) {
                continue;
            }
            $grade = (int) $section->levelid;
            if ($grade < 7 || $grade > 12) {
                continue;
            }

            $isElectiveSection = str_starts_with((string) $section->sectionname, 'ELEC-');
            // Elective (cross-section) groups are only schedulable for grades that
            // have elective windows; for other grades they remain out of scope.
            if ($isElectiveSection && ! in_array($grade, $electiveGrades, true)) {
                continue;
            }

            $sessions = max(1, (int) round((float) ($la->subject->load_units ?? 1)));

            // TBA / vacant placeholder faculty must never create false conflicts —
            // give each such session its own unique sentinel "faculty".
            $facultyName = $la->faculty?->name ?? 'TBA';
            $isPlaceholder = str_starts_with($facultyName, 'TBA');
            $facultyId = $isPlaceholder ? -((int) $la->id) : (int) $la->user_id;

            if (! $isPlaceholder) {
                $facultyTotal[$facultyId] = ($facultyTotal[$facultyId] ?? 0) + $sessions;
            }

            $requirements[] = [
                'load_assignment_id' => (int) $la->id,
                'section_id'         => (int) $section->id,
                'section_name'       => $section->sectionname,
                'grade'              => $grade,
                'classroom_id'       => $section->classroom_id ? (int) $section->classroom_id : null,
                'classroom_name'     => $section->classroom_id ? ($classroomNames[$section->classroom_id] ?? null) : null,
                'subject_id'         => (int) $la->subject_id,
                'subject_code'       => $la->subject->code,
                'subject_name'       => $la->subject->name,
                'subject_type'       => $la->subject->subject_type,
                'faculty_id'         => $facultyId,
                'faculty_name'       => $facultyName,
                'sessions_needed'    => $sessions,
                'is_elective'        => $isElectiveSection || $la->subject->subject_type === 'elective',
            ];
        }

        // Attach faculty total to each requirement for ordering.
        foreach ($requirements as &$req) {
            $req['faculty_total'] = $facultyTotal[$req['faculty_id']] ?? 0;
        }
        unset($req);

        return $requirements;
    }

    /**
     * Grades whose bell schedule contains elective-labeled periods. Only these
     * grades can schedule cross-section elective groups.
     *
     * @return array<int,int>
     */
    private function gradesWithElectiveWindows(): array
    {
        $grades = [];
        foreach (range(7, 12) as $grade) {
            foreach ($this->buildSlotGrid($grade) as $slot) {
                if ($slot['is_elective']) {
                    $grades[] = $grade;
                    break;
                }
            }
        }
        return $grades;
    }

    // ── Slot grid ─────────────────────────────────────────────────────────

    /**
     * Build the available CLASS period slots for a grade across the week,
     * applying the Wednesday activity cutoff, the Wednesday wellness block, and
     * Friday ILA (no in-person) for the lower grades.
     *
     * @return array<int,array{day:string,start:string,end:string,start_min:int,end_min:int}>
     */
    private function buildSlotGrid(int $grade): array
    {
        $group     = SchedulingConstants::getGradeGroup($grade);
        $wedCut    = SchedulingConstants::WEDNESDAY_ACTIVITY_START[$group] ?? '23:59';
        $fullWed   = in_array($grade, SchedulingConstants::WEDNESDAY_FULL_GRADES, true);
        $reclaimMon = in_array($grade, SchedulingConstants::MONDAY_RECLAIM_GAP_GRADES, true);

        $slots = [];
        foreach (self::DAYS as $day) {
            // Friday ILA: lower grades have no in-person classes on Friday.
            if ($day === 'Friday' && in_array($grade, SchedulingConstants::FRIDAY_ILA_GRADES, true)) {
                continue;
            }

            $timetable = ($day === 'Monday')
                ? SchedulingConstants::getMondayTimetable($grade)
                : SchedulingConstants::getTueFriTimetable($grade);

            foreach ($timetable as $row) {
                // Usable teaching rows: CLASS periods, plus the Monday DEAD gap for
                // grades that reclaim it.
                $isClass = ($row['type'] ?? '') === 'CLASS';
                $isReclaimedGap = $reclaimMon && $day === 'Monday' && ($row['type'] ?? '') === 'DEAD';
                if (! $isClass && ! $isReclaimedGap) {
                    continue;
                }

                // Wednesday restrictions do not apply to "full Wednesday" grades.
                if ($day === 'Wednesday' && ! $fullWed) {
                    if ($row['start'] >= $wedCut) {
                        continue;
                    }
                    if (SchedulingConstants::timesOverlap(
                        $row['start'], $row['end'],
                        SchedulingConstants::WEDNESDAY_WELLNESS['start'],
                        SchedulingConstants::WEDNESDAY_WELLNESS['end'],
                    )) {
                        continue;
                    }
                }

                $slots[] = [
                    'day'         => $day,
                    'start'       => $row['start'],
                    'end'         => $row['end'],
                    'start_min'   => SchedulingConstants::toMinutes($row['start']),
                    'end_min'     => SchedulingConstants::toMinutes($row['end']),
                    // Periods the bell schedule reserves for electives — core
                    // (homeroom) subjects avoid these; electives only use these.
                    'is_elective' => str_contains($row['label'] ?? '', 'Elective'),
                ];
            }
        }

        return $slots;
    }

    // ── Placement ─────────────────────────────────────────────────────────

    /**
     * Find the best free slot for a session, or null if none is available.
     * "Best" minimises a soft penalty that spreads a section's classes across
     * days and avoids placing the same subject twice on one day.
     *
     * @param array<string,mixed> $s
     * @param array<int,array<string,mixed>> $reserved time windows reserved by an
     *        in-progress relocation chain that this placement must not overlap
     */
    private function findBestSlot(array $s, array $reserved = []): ?array
    {
        $best      = null;
        $bestScore = PHP_INT_MAX;

        foreach ($this->gridByGrade[$s['grade']] ?? [] as $slot) {
            $day = $slot['day'];

            // Electives only use elective windows; core subjects only use the rest.
            if (($slot['is_elective'] ?? false) !== ($s['is_elective'] ?? false)) {
                continue;
            }
            if ($this->overlapsReserved($slot, $reserved)) {
                continue;
            }

            // HARD: section free, faculty free at this time.
            if ($this->sectionBusyAt($s['section_id'], $slot)) {
                continue;
            }
            if ($this->facultyBusyAt($s['faculty_id'], $slot)) {
                continue;
            }

            // SOFT scoring (lower is better).
            $score = 0;
            // Strongly avoid the same subject twice on the same day for a section.
            $score += ($this->sectionSubjDays[$s['section_id']][$s['subject_id']][$day] ?? 0) * 10000;
            // Spread a section's classes evenly across the week.
            $score += ($this->sectionDayCount[$s['section_id']][$day] ?? 0) * 100;
            // Slight preference for earlier days/times for determinism + compactness.
            $score += array_search($day, self::DAYS, true) * 2;
            $score += intdiv($slot['start_min'], 30);

            if ($score < $bestScore) {
                $bestScore = $score;
                $best      = $slot;
            }
        }

        return $best;
    }

    /**
     * Place a session, displacing blocking classes of the same faculty and
     * recursively re-placing them, up to a bounded depth. This is a bounded
     * augmenting-path search that recovers feasible-but-tight placements that
     * pure greedy misses. Leaves the scheduling state unchanged on failure.
     *
     * @param array<string,mixed> $s
     * @param array<int,array<string,mixed>> $reserved time windows reserved by
     *        the relocation chain so far (this placement must avoid them)
     */
    private function attemptPlace(array $s, int $depth, array $reserved = []): bool
    {
        // Direct placement onto a slot that is free for both section and faculty.
        $slot = $this->findBestSlot($s, $reserved);
        if ($slot !== null) {
            $this->commit($s, $slot);
            return true;
        }

        if ($depth <= 0) {
            return false;
        }

        foreach ($this->gridByGrade[$s['grade']] ?? [] as $slot) {
            // Electives only use elective windows; core subjects only use the rest.
            if (($slot['is_elective'] ?? false) !== ($s['is_elective'] ?? false)) {
                continue;
            }
            if ($this->overlapsReserved($slot, $reserved)) {
                continue;
            }
            // Section must be free here; only the faculty is (singly) blocked.
            if ($this->sectionBusyAt($s['section_id'], $slot)) {
                continue;
            }
            $blockers = $this->facultyBlockersAt($s['faculty_id'], $slot);
            if (count($blockers) !== 1) {
                continue;
            }

            $blockIdx = $blockers[0];
            $blocked  = $this->placements[$blockIdx];

            // Free the blocker and try to re-place it elsewhere — it (and any
            // class it displaces in turn) must avoid every slot this chain has
            // reserved, including the one we are reserving now for $s.
            $this->uncommit($blockIdx);
            if ($this->attemptPlace($blocked['s'], $depth - 1, array_merge($reserved, [$slot]))) {
                $this->commit($s, $slot);
                return true;
            }

            // Restore the blocker at its original slot/index and keep searching.
            $this->commit($blocked['s'], $blocked['slot'], $blockIdx);
        }

        return false;
    }

    /**
     * True if a slot overlaps any reserved time window (same day).
     *
     * @param array<string,mixed> $slot
     * @param array<int,array<string,mixed>> $reserved
     */
    private function overlapsReserved(array $slot, array $reserved): bool
    {
        foreach ($reserved as $r) {
            if ($slot['day'] === $r['day']
                && $slot['start_min'] < $r['end_min']
                && $slot['end_min'] > $r['start_min']) {
                return true;
            }
        }
        return false;
    }

    /**
     * Commit a placement into the scheduling state. Returns the placement index.
     *
     * @param array<string,mixed> $s
     * @param array<string,mixed> $slot
     */
    private function commit(array $s, array $slot, ?int $reuseIdx = null): int
    {
        $idx = $reuseIdx ?? count($this->placements);
        $this->placements[$idx] = ['s' => $s, 'slot' => $slot];

        $day = $slot['day'];
        $this->sectionBusy[$s['section_id']][$day][] = [$slot['start_min'], $slot['end_min'], $idx];
        $this->facultyBusy[$s['faculty_id']][$day][] = [$slot['start_min'], $slot['end_min'], $idx];
        $this->sectionDayCount[$s['section_id']][$day] = ($this->sectionDayCount[$s['section_id']][$day] ?? 0) + 1;
        $this->sectionSubjDays[$s['section_id']][$s['subject_id']][$day] =
            ($this->sectionSubjDays[$s['section_id']][$s['subject_id']][$day] ?? 0) + 1;

        return $idx;
    }

    /** Remove a committed placement from the scheduling state. */
    private function uncommit(int $idx): void
    {
        $p    = $this->placements[$idx];
        $s    = $p['s'];
        $day  = $p['slot']['day'];

        $this->sectionBusy[$s['section_id']][$day] = $this->dropByIdx($this->sectionBusy[$s['section_id']][$day], $idx);
        $this->facultyBusy[$s['faculty_id']][$day] = $this->dropByIdx($this->facultyBusy[$s['faculty_id']][$day], $idx);
        $this->sectionDayCount[$s['section_id']][$day]--;
        $this->sectionSubjDays[$s['section_id']][$s['subject_id']][$day]--;

        $this->placements[$idx] = null;
    }

    /** @param array<int,array{0:int,1:int,2:int}> $intervals */
    private function dropByIdx(array $intervals, int $idx): array
    {
        return array_values(array_filter($intervals, fn ($iv) => $iv[2] !== $idx));
    }

    /** @param array<string,mixed> $slot */
    private function sectionBusyAt(int $sectionId, array $slot): bool
    {
        foreach ($this->sectionBusy[$sectionId][$slot['day']] ?? [] as [$start, $end]) {
            if ($slot['start_min'] < $end && $slot['end_min'] > $start) {
                return true;
            }
        }
        return false;
    }

    /** @param array<string,mixed> $slot */
    private function facultyBusyAt(int $facultyId, array $slot): bool
    {
        foreach ($this->facultyBusy[$facultyId][$slot['day']] ?? [] as [$start, $end]) {
            if ($slot['start_min'] < $end && $slot['end_min'] > $start) {
                return true;
            }
        }
        return false;
    }

    /**
     * Placement indices of a faculty's classes overlapping the given slot.
     *
     * @param array<string,mixed> $slot
     * @return array<int,int>
     */
    private function facultyBlockersAt(int $facultyId, array $slot): array
    {
        $hits = [];
        foreach ($this->facultyBusy[$facultyId][$slot['day']] ?? [] as [$start, $end, $idx]) {
            if ($slot['start_min'] < $end && $slot['end_min'] > $start) {
                $hits[] = $idx;
            }
        }
        return $hits;
    }

    // ── Output shaping ──────────────────────────────────────────────────────

    /**
     * @param array<string,mixed> $s
     * @param array<string,mixed> $slot
     * @return array<string,mixed>
     */
    private function toScheduleRow(array $s, array $slot, int $schoolYearId, int $termId): array
    {
        return [
            'load_assignment_id' => $s['load_assignment_id'],
            'user_id'            => $s['faculty_id'],
            'subject_id'         => $s['subject_id'],
            'section_id'         => $s['section_id'],
            'classroom_id'       => $s['classroom_id'],
            'school_year_id'     => $schoolYearId,
            'academic_term_id'   => $termId,
            'day_of_week'        => $slot['day'],
            'start_time'         => $slot['start'] . ':00',
            'end_time'           => $slot['end'] . ':00',
            'status'             => 'tentative',
            'remarks'            => 'AI-generated schedule',
            // Display-only metadata (underscore-prefixed; ignored by the
            // controller's explicit column mapping on apply()).
            '_section_name'      => $s['section_name'],
            '_subject_code'      => $s['subject_code'],
            '_subject_name'      => $s['subject_name'],
            '_faculty_name'      => $s['faculty_name'],
            '_classroom_name'    => $s['classroom_name'],
        ];
    }

    /** @param array<string,mixed> $s */
    private function describeSession(array $s): array
    {
        return [
            'section_id'   => $s['section_id'],
            'section_name' => $s['section_name'],
            'grade'        => $s['grade'],
            'subject_code' => $s['subject_code'],
            'subject_name' => $s['subject_name'],
            'faculty_name' => $s['faculty_name'],
        ];
    }

    /**
     * Per-section summary of needed vs placed sessions (for the UI report).
     *
     * @param array<int,array<string,mixed>> $requirements
     * @param array<int,array<string,mixed>> $placed
     * @return array<int,array<string,mixed>>
     */
    private function buildSectionReport(array $requirements, array $placed): array
    {
        $needed = [];
        $names  = [];
        foreach ($requirements as $req) {
            $needed[$req['section_id']] = ($needed[$req['section_id']] ?? 0) + $req['sessions_needed'];
            $names[$req['section_id']]  = ['name' => $req['section_name'], 'grade' => $req['grade']];
        }

        $placedCount = [];
        foreach ($placed as $row) {
            $placedCount[$row['section_id']] = ($placedCount[$row['section_id']] ?? 0) + 1;
        }

        $report = [];
        foreach ($needed as $sectionId => $need) {
            $report[] = [
                'section_id'   => $sectionId,
                'section_name' => $names[$sectionId]['name'],
                'grade'        => $names[$sectionId]['grade'],
                'needed'       => $need,
                'placed'       => $placedCount[$sectionId] ?? 0,
                'unplaced'     => $need - ($placedCount[$sectionId] ?? 0),
            ];
        }

        usort($report, fn ($a, $b) => [$a['grade'], $a['section_name']] <=> [$b['grade'], $b['section_name']]);

        return $report;
    }
}
