<?php

namespace App\Services\FacultyLoading;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\ClassSchedule;
use App\Models\FacultyLoading\FacultyLoad;
use App\Models\FacultyLoading\LoadAssignment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * LoadBalancingService
 *
 * Analyses faculty workloads for a given academic term and generates
 * actionable rebalancing suggestions.
 *
 * ── Analysis outputs ────────────────────────────────────────────────────────
 *  Per-faculty profile: unit breakdown, daily hours, preparation count, flags
 *  Term summary: average load, std-deviation, imbalance score
 *
 * ── Suggestion types ────────────────────────────────────────────────────────
 *  • transfer — move one teaching assignment from an overloaded to an
 *               underloaded faculty member
 *  • swap     — exchange two teaching assignments between two faculty members
 *               to bring both closer to the full-load threshold
 *
 * ── Apply ────────────────────────────────────────────────────────────────────
 *  Moves a load_assignment to a new faculty (updating user_id, faculty_load_id
 *  and mirroring the change on linked class_schedules), then re-syncs both
 *  faculty load totals via LoadComputationService.
 */
class LoadBalancingService
{
    // Imbalance thresholds (using constants from LoadComputationService)
    private const FULL_LOAD   = LoadComputationService::FULL_LOAD_THRESHOLD;  // 18
    private const MAX_DAILY   = LoadComputationService::MAX_TEACHING_HRS_DAY; // 6

    // Score cap so we don't waste time on marginal improvements
    private const MIN_IMPROVEMENT = 0.5; // units

    public function __construct(
        private readonly LoadComputationService $loadService,
        private readonly ConflictDetectionService $conflictService,
        private readonly ClassScheduleScopeLockService $scopeLocks,
    ) {}

    // ── Public API ────────────────────────────────────────────────────────

    /**
     * Build a full workload analysis for every faculty member in the term.
     *
     * @return array{
     *   term:        array,
     *   summary:     array,
     *   faculty:     array,   // per-faculty profiles
     *   imbalanced:  int,     // count of over/underloaded faculty
     * }
     */
    public function analyse(int $termId): array
    {
        $term = AcademicTerm::with('schoolYear')->findOrFail($termId);

        $loads = FacultyLoad::with([
                'faculty:id,name,position',
                'assignments.subject',
            ])
            ->forTerm($termId)
            ->get();

        // Daily teaching hours per faculty from class_schedules
        $dailyHours = $this->buildDailyHoursMap($termId);

        $faculty   = $loads->map(fn ($load) => $this->buildProfile($load, $dailyHours))->values()->all();
        $summary   = $this->buildSummary($faculty);

        return [
            'term'       => [
                'id'         => $term->id,
                'name'       => $term->name,
                'school_year'=> $term->schoolYear?->name ?? '—',
                'full_label' => ($term->name . ', S.Y. ' . ($term->schoolYear?->name ?? '—')),
            ],
            'summary'    => $summary,
            'faculty'    => $faculty,
            'imbalanced' => collect($faculty)->filter(fn ($f) => !empty($f['flags']))->count(),
        ];
    }

    /**
     * Generate a prioritised list of rebalancing suggestions for the term.
     *
     * @return array  List of suggestion objects (see buildTransferSuggestion / buildSwapSuggestion)
     */
    public function suggest(int $termId): array
    {
        $loads = FacultyLoad::with(['faculty:id,name,position', 'assignments.subject'])
            ->forTerm($termId)
            ->get()
            ->keyBy('user_id');

        $suggestions = [];

        $overloaded  = $loads->filter(fn ($l) => (float) $l->total_units > self::FULL_LOAD);
        $underloaded = $loads->filter(fn ($l) => (float) $l->total_units < self::FULL_LOAD);

        // ── Transfer: overloaded → underloaded ────────────────────────────
        foreach ($overloaded as $from) {
            foreach ($from->assignments->where('assignment_type', 'teaching') as $assignment) {
                foreach ($underloaded as $to) {
                    if ($from->user_id === $to->user_id) {
                        continue;
                    }

                    $suggestion = $this->evaluateTransfer($from, $to, $assignment);
                    if ($suggestion !== null) {
                        $suggestions[] = $suggestion;
                    }
                }
            }
        }

        // ── Swap: pair with complementary imbalances ──────────────────────
        $allLoads = $loads->values();
        for ($i = 0; $i < $allLoads->count(); $i++) {
            for ($j = $i + 1; $j < $allLoads->count(); $j++) {
                $loadA = $allLoads[$i];
                $loadB = $allLoads[$j];

                // Only bother if they have opposite imbalances
                if ($loadA->load_status === $loadB->load_status) {
                    continue;
                }

                $swaps = $this->evaluateSwaps($loadA, $loadB);
                foreach ($swaps as $swap) {
                    $suggestions[] = $swap;
                }
            }
        }

        // Sort by improvement score descending
        usort($suggestions, fn ($a, $b) => $b['improvement_score'] <=> $a['improvement_score']);

        // Deduplicate: keep best suggestion per assignment
        $seen        = [];
        $deduplicated = [];
        foreach ($suggestions as $s) {
            $key = $s['type'] === 'transfer'
                ? 'la_' . $s['load_assignment_id']
                : 'swap_' . min($s['assignment_a_id'], $s['assignment_b_id']) . '_' . max($s['assignment_a_id'], $s['assignment_b_id']);

            if (!isset($seen[$key])) {
                $seen[$key]     = true;
                $deduplicated[] = $s;
            }
        }

        return array_slice($deduplicated, 0, 20); // cap at 20 suggestions
    }

    /**
     * Apply a transfer: move one teaching assignment from one faculty to another.
     *
     * @param  int  $loadAssignmentId  The assignment to move
     * @param  int  $targetFacultyId   The receiving faculty's user_id
     * @return array{message: string, from_new_total: float, to_new_total: float}
     */
    public function applyTransfer(int $loadAssignmentId, int $targetFacultyId): array
    {
        $assignment = LoadAssignment::with('facultyLoad')->findOrFail($loadAssignmentId);

        if ($assignment->assignment_type !== 'teaching') {
            throw new \InvalidArgumentException('Only teaching assignments can be transferred.');
        }

        $sourceFacultyLoad = $assignment->facultyLoad;

        if ($sourceFacultyLoad?->is_locked) {
            throw new \RuntimeException('Source faculty load is locked and cannot be modified.');
        }

        // Find or create the target faculty's load record for this term
        $targetLoad = FacultyLoad::firstOrCreate(
            [
                'user_id'          => $targetFacultyId,
                'academic_term_id' => $assignment->academic_term_id,
            ],
            [
                'school_year_id'      => $assignment->school_year_id,
                'full_load_threshold' => self::FULL_LOAD,
                'load_status'         => 'underload',
                'teaching_units'      => 0,
                'research_units'      => 0,
                'admin_units'         => 0,
                'cocurricular_units'  => 0,
                'committee_units'     => 0,
                'total_units'         => 0,
                'is_locked'           => false,
                'overload_approved'   => false,
            ]
        );

        if ($targetLoad->is_locked) {
            throw new \RuntimeException('Target faculty load is locked and cannot receive assignments.');
        }

        $this->assertTargetFreeForAssignment($targetFacultyId, $assignment);

        DB::transaction(function () use ($assignment, $targetLoad, $targetFacultyId) {
            AcademicTerm::whereKey($assignment->academic_term_id)->lockForUpdate()->firstOrFail();
            $sectionIds = ClassSchedule::where('load_assignment_id', $assignment->id)->pluck('section_id');
            $this->scopeLocks->assertSectionsUnlocked((int) $assignment->academic_term_id, $sectionIds);
            // Move the assignment
            $assignment->update([
                'user_id'          => $targetFacultyId,
                'faculty_load_id'  => $targetLoad->id,
            ]);

            // Mirror the change on any linked class_schedule rows
            ClassSchedule::where('load_assignment_id', $assignment->id)
                ->update(['user_id' => $targetFacultyId]);
        });

        // Re-sync both faculty load totals
        $fromNewLoad = $sourceFacultyLoad
            ? $this->loadService->syncLoad($sourceFacultyLoad->fresh())
            : null;
        $toNewLoad   = $this->loadService->syncLoad($targetLoad->fresh());

        return [
            'message'        => "Assignment transferred to faculty #{$targetFacultyId} successfully.",
            'from_new_total' => $fromNewLoad ? (float) $fromNewLoad->total_units : null,
            'to_new_total'   => (float) $toNewLoad->total_units,
        ];
    }

    /**
     * Apply a swap: exchange two teaching assignments between two faculty.
     *
     * @param  int  $assignmentAId  Assignment currently held by Faculty A
     * @param  int  $assignmentBId  Assignment currently held by Faculty B
     */
    public function applySwap(int $assignmentAId, int $assignmentBId): array
    {
        $assignmentA = LoadAssignment::with('facultyLoad')->findOrFail($assignmentAId);
        $assignmentB = LoadAssignment::with('facultyLoad')->findOrFail($assignmentBId);

        foreach ([$assignmentA, $assignmentB] as $a) {
            if ($a->assignment_type !== 'teaching') {
                throw new \InvalidArgumentException('Only teaching assignments can be swapped.');
            }
            if ($a->facultyLoad?->is_locked) {
                throw new \RuntimeException("Faculty load for assignment #{$a->id} is locked.");
            }
        }

        $loadA = $assignmentA->facultyLoad;
        $loadB = $assignmentB->facultyLoad;

        $userA = $assignmentA->user_id;
        $userB = $assignmentB->user_id;

        // Each assignment's existing class_schedule slots must be free on the
        // OTHER faculty's calendar — a swap moves A's slots to B and vice versa.
        $this->assertTargetFreeForAssignment((int) $userB, $assignmentA);
        $this->assertTargetFreeForAssignment((int) $userA, $assignmentB);

        DB::transaction(function () use ($assignmentA, $assignmentB, $loadA, $loadB, $userA, $userB) {
            AcademicTerm::whereKey($assignmentA->academic_term_id)->lockForUpdate()->firstOrFail();
            $sectionIds = ClassSchedule::whereIn('load_assignment_id', [$assignmentA->id, $assignmentB->id])->pluck('section_id');
            $this->scopeLocks->assertSectionsUnlocked((int) $assignmentA->academic_term_id, $sectionIds);
            // Swap user_id and faculty_load_id
            $assignmentA->update(['user_id' => $userB, 'faculty_load_id' => $loadB->id]);
            $assignmentB->update(['user_id' => $userA, 'faculty_load_id' => $loadA->id]);

            // Mirror on class_schedules
            ClassSchedule::where('load_assignment_id', $assignmentA->id)->update(['user_id' => $userB]);
            ClassSchedule::where('load_assignment_id', $assignmentB->id)->update(['user_id' => $userA]);
        });

        $this->loadService->syncLoad($loadA->fresh());
        $this->loadService->syncLoad($loadB->fresh());

        return [
            'message' => "Assignments #{$assignmentAId} and #{$assignmentBId} swapped successfully.",
        ];
    }

    /**
     * Guard against a transfer/swap silently creating a real H1 teacher
     * double-booking: check every class_schedule slot linked to $assignment
     * against the RECEIVING faculty's existing calendar before it's moved.
     *
     * @throws \RuntimeException if the target faculty is already booked at
     *         any of the assignment's existing slots
     */
    private function assertTargetFreeForAssignment(int $targetFacultyId, LoadAssignment $assignment): void
    {
        $rows = ClassSchedule::where('load_assignment_id', $assignment->id)->get();

        foreach ($rows as $row) {
            $conflicts = $this->conflictService->detectFacultyConflict(
                $targetFacultyId,
                $row->day_of_week,
                $row->start_time,
                $row->end_time,
                $row->academic_term_id,
                excludeId: $row->id,
            );

            if ($conflicts->isNotEmpty()) {
                $conflict = $conflicts->first();
                throw new \RuntimeException(sprintf(
                    'Cannot move assignment #%d: the receiving faculty is already booked %s %s–%s.',
                    $assignment->id,
                    $conflict->day_of_week,
                    substr((string) $conflict->start_time, 0, 5),
                    substr((string) $conflict->end_time, 0, 5),
                ));
            }
        }
    }

    // ── Profile Builder ───────────────────────────────────────────────────

    private function buildProfile(FacultyLoad $load, array $dailyHours): array
    {
        $userId        = $load->user_id;
        $totalUnits    = (float) $load->total_units;
        $threshold     = (float) $load->full_load_threshold;
        $teachingUnits = (float) $load->teaching_units;

        $assignments   = $load->assignments->where('assignment_type', 'teaching');
        $subjectCount  = $assignments->pluck('subject_id')->unique()->count();

        $facultyDailyHours = $dailyHours[$userId] ?? [];
        $maxDailyHours     = empty($facultyDailyHours) ? 0.0 : max($facultyDailyHours);

        $flags = $this->detectFlags($load, $maxDailyHours);

        return [
            'faculty_load_id'    => $load->id,
            'user_id'            => $userId,
            'faculty_name'       => $load->faculty?->name       ?? '—',
            'position'           => $load->faculty?->position   ?? '—',
            'total_units'        => $totalUnits,
            'teaching_units'     => $teachingUnits,
            'research_units'     => (float) $load->research_units,
            'admin_units'        => (float) $load->admin_units,
            'cocurricular_units' => (float) $load->cocurricular_units,
            'committee_units'    => (float) $load->committee_units,
            'full_load_threshold'=> $threshold,
            'load_status'        => $load->load_status,
            'overload_units'     => max(0.0, round($totalUnits - $threshold, 2)),
            'underload_units'    => max(0.0, round($threshold - $totalUnits, 2)),
            'subjects_count'     => $subjectCount,
            'daily_hours'        => $facultyDailyHours,
            'max_daily_hours'    => $maxDailyHours,
            'is_locked'          => (bool) $load->is_locked,
            'overload_approved'  => (bool) $load->overload_approved,
            'flags'              => $flags,
            'teaching_assignments' => $assignments->map(fn ($a) => [
                'id'           => $a->id,
                'subject_id'   => $a->subject_id,
                'subject_name' => $a->subject?->name ?? '—',
                'section_id'   => $a->section_id,
                'load_units'   => (float) $a->load_units,
                'grade_level'  => $a->subject?->grade_level ?? null,
                'subject_type' => $a->subject?->subject_type ?? null,
            ])->values()->all(),
        ];
    }

    private function detectFlags(FacultyLoad $load, float $maxDailyHours): array
    {
        $flags         = [];
        $totalUnits    = (float) $load->total_units;
        $threshold     = (float) $load->full_load_threshold;
        $overloadUnits = max(0.0, $totalUnits - $threshold);
        $underloadUnits= max(0.0, $threshold - $totalUnits);

        if ($overloadUnits > 0 && !$load->overload_approved) {
            $flags[] = [
                'type'     => 'overload',
                'severity' => $overloadUnits >= 3 ? 'high' : 'medium',
                'message'  => "Overloaded by {$overloadUnits} units — approval pending.",
            ];
        }

        if ($underloadUnits >= 3) {
            $flags[] = [
                'type'     => 'underload',
                'severity' => $underloadUnits >= 6 ? 'high' : 'medium',
                'message'  => "Underloaded by {$underloadUnits} units.",
            ];
        }

        if ($maxDailyHours > self::MAX_DAILY) {
            $flags[] = [
                'type'     => 'daily_hours',
                'severity' => $maxDailyHours > 8.0 ? 'high' : 'medium',
                'message'  => "Teaching {$maxDailyHours}h in a single day (normal max: " . self::MAX_DAILY . "h).",
            ];
        }

        if ($load->is_locked) {
            $flags[] = [
                'type'     => 'locked',
                'severity' => 'info',
                'message'  => 'Load is locked — no changes can be made.',
            ];
        }

        return $flags;
    }

    // ── Summary Builder ───────────────────────────────────────────────────

    private function buildSummary(array $faculty): array
    {
        if (empty($faculty)) {
            return [
                'faculty_count'   => 0,
                'average_load'    => 0.0,
                'std_deviation'   => 0.0,
                'imbalance_score' => 0.0,
                'overloaded'      => 0,
                'underloaded'     => 0,
                'full_load'       => 0,
            ];
        }

        $totals = array_column($faculty, 'total_units');
        $count  = count($totals);
        $avg    = array_sum($totals) / $count;
        $variance = array_sum(array_map(fn ($u) => ($u - $avg) ** 2, $totals)) / $count;
        $std    = sqrt($variance);

        // Imbalance score: mean absolute deviation from threshold
        $imbalanceScore = array_sum(
            array_map(fn ($u) => abs($u - self::FULL_LOAD), $totals)
        ) / $count;

        return [
            'faculty_count'   => $count,
            'average_load'    => round($avg, 2),
            'std_deviation'   => round($std, 2),
            'imbalance_score' => round($imbalanceScore, 2),
            'overloaded'      => collect($faculty)->where('load_status', 'overload')->count(),
            'underloaded'     => collect($faculty)->where('load_status', 'underload')->count(),
            'full_load'       => collect($faculty)->where('load_status', 'full_load')->count(),
            'max_load'        => $count ? max($totals) : 0,
            'min_load'        => $count ? min($totals) : 0,
        ];
    }

    // ── Suggestion Evaluators ─────────────────────────────────────────────

    private function evaluateTransfer(FacultyLoad $from, FacultyLoad $to, LoadAssignment $assignment): ?array
    {
        $units        = (float) $assignment->load_units;
        $fromTotal    = (float) $from->total_units;
        $toTotal      = (float) $to->total_units;
        $threshold    = self::FULL_LOAD;

        // After transfer
        $fromNew = $fromTotal - $units;
        $toNew   = $toTotal   + $units;

        // Don't suggest if it over-burdens the receiver
        if ($toNew > $threshold + 3) {
            return null;
        }

        // Calculate how much the imbalance improves
        $beforeImbalance = abs($fromTotal - $threshold) + abs($toTotal - $threshold);
        $afterImbalance  = abs($fromNew   - $threshold) + abs($toNew   - $threshold);
        $improvement     = $beforeImbalance - $afterImbalance;

        if ($improvement < self::MIN_IMPROVEMENT) {
            return null;
        }

        return $this->buildTransferSuggestion($from, $to, $assignment, $fromNew, $toNew, $improvement);
    }

    private function evaluateSwaps(FacultyLoad $loadA, FacultyLoad $loadB): array
    {
        $results  = [];
        $threshold = self::FULL_LOAD;
        $totalA   = (float) $loadA->total_units;
        $totalB   = (float) $loadB->total_units;

        $assignmentsA = $loadA->assignments->where('assignment_type', 'teaching');
        $assignmentsB = $loadB->assignments->where('assignment_type', 'teaching');

        foreach ($assignmentsA as $asgA) {
            foreach ($assignmentsB as $asgB) {
                $unitsA = (float) $asgA->load_units;
                $unitsB = (float) $asgB->load_units;

                // No point swapping equal-unit assignments
                if (abs($unitsA - $unitsB) < 0.5) {
                    continue;
                }

                // After swap: A gives unitsA, receives unitsB; B vice-versa
                $newA = $totalA - $unitsA + $unitsB;
                $newB = $totalB - $unitsB + $unitsA;

                // Skip if either goes wildly out of range
                if ($newA > $threshold + 3 || $newB > $threshold + 3) {
                    continue;
                }
                if ($newA < 0 || $newB < 0) {
                    continue;
                }

                $before      = abs($totalA - $threshold) + abs($totalB - $threshold);
                $after       = abs($newA   - $threshold) + abs($newB   - $threshold);
                $improvement = $before - $after;

                if ($improvement < self::MIN_IMPROVEMENT) {
                    continue;
                }

                $results[] = $this->buildSwapSuggestion(
                    $loadA, $loadB, $asgA, $asgB, $newA, $newB, $improvement
                );
            }
        }

        return $results;
    }

    // ── Suggestion Builders ───────────────────────────────────────────────

    private function buildTransferSuggestion(
        FacultyLoad $from,
        FacultyLoad $to,
        LoadAssignment $assignment,
        float $fromNew,
        float $toNew,
        float $improvement
    ): array {
        $threshold = self::FULL_LOAD;
        return [
            'type'                     => 'transfer',
            'explanation'              => sprintf(
                'Transfer "%s" (%s units) from %s → %s to reduce overload.',
                $assignment->subject?->name ?? 'Subject',
                $assignment->load_units,
                $from->faculty?->name ?? "Faculty #{$from->user_id}",
                $to->faculty?->name   ?? "Faculty #{$to->user_id}"
            ),
            'load_assignment_id'       => $assignment->id,
            'subject_name'             => $assignment->subject?->name   ?? '—',
            'subject_id'               => $assignment->subject_id,
            'section_id'               => $assignment->section_id,
            'load_units'               => (float) $assignment->load_units,
            'from_user_id'             => $from->user_id,
            'from_faculty_name'        => $from->faculty?->name ?? '—',
            'from_current_total'       => (float) $from->total_units,
            'from_new_total'           => round($fromNew, 2),
            'from_new_status'          => $this->loadService->getLoadStatus($fromNew, $threshold),
            'to_user_id'               => $to->user_id,
            'to_faculty_name'          => $to->faculty?->name   ?? '—',
            'to_current_total'         => (float) $to->total_units,
            'to_new_total'             => round($toNew, 2),
            'to_new_status'            => $this->loadService->getLoadStatus($toNew, $threshold),
            'improvement_score'        => round($improvement, 2),
        ];
    }

    private function buildSwapSuggestion(
        FacultyLoad $loadA,
        FacultyLoad $loadB,
        LoadAssignment $asgA,
        LoadAssignment $asgB,
        float $newA,
        float $newB,
        float $improvement
    ): array {
        $threshold = self::FULL_LOAD;
        return [
            'type'              => 'swap',
            'explanation'       => sprintf(
                'Swap "%s" (%s u) with "%s" (%s u) between %s and %s.',
                $asgA->subject?->name ?? 'Subject A',
                $asgA->load_units,
                $asgB->subject?->name ?? 'Subject B',
                $asgB->load_units,
                $loadA->faculty?->name ?? "Faculty #{$loadA->user_id}",
                $loadB->faculty?->name ?? "Faculty #{$loadB->user_id}"
            ),
            'assignment_a_id'         => $asgA->id,
            'assignment_a_subject'    => $asgA->subject?->name ?? '—',
            'assignment_a_units'      => (float) $asgA->load_units,
            'assignment_b_id'         => $asgB->id,
            'assignment_b_subject'    => $asgB->subject?->name ?? '—',
            'assignment_b_units'      => (float) $asgB->load_units,
            'faculty_a_user_id'       => $loadA->user_id,
            'faculty_a_name'          => $loadA->faculty?->name ?? '—',
            'faculty_a_current_total' => (float) $loadA->total_units,
            'faculty_a_new_total'     => round($newA, 2),
            'faculty_a_new_status'    => $this->loadService->getLoadStatus($newA, $threshold),
            'faculty_b_user_id'       => $loadB->user_id,
            'faculty_b_name'          => $loadB->faculty?->name ?? '—',
            'faculty_b_current_total' => (float) $loadB->total_units,
            'faculty_b_new_total'     => round($newB, 2),
            'faculty_b_new_status'    => $this->loadService->getLoadStatus($newB, $threshold),
            'improvement_score'       => round($improvement, 2),
        ];
    }

    // ── Daily Hours Helper ────────────────────────────────────────────────

    /**
     * Build a map of [user_id => [day => float hours]] from class_schedules.
     */
    private function buildDailyHoursMap(int $termId): array
    {
        $schedules = ClassSchedule::classes()
            ->where('academic_term_id', $termId)
            ->where('status', 'active')
            ->get(['user_id', 'day_of_week', 'start_time', 'end_time']);

        $map = [];
        foreach ($schedules as $s) {
            $hours = $this->minutesBetween($s->start_time, $s->end_time) / 60.0;
            $map[$s->user_id][$s->day_of_week] = ($map[$s->user_id][$s->day_of_week] ?? 0.0) + $hours;
        }

        // Round to 2 decimal places
        foreach ($map as $uid => $days) {
            foreach ($days as $day => $h) {
                $map[$uid][$day] = round($h, 2);
            }
        }

        return $map;
    }

    private function minutesBetween(string $start, string $end): int
    {
        $toMin = fn ($t) => (int) explode(':', $t)[0] * 60 + (int) (explode(':', $t)[1] ?? 0);
        return max(0, $toMin($end) - $toMin($start));
    }
}
