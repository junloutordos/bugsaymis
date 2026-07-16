<?php

namespace App\Services\FacultyLoading;

use App\Models\FacultyLoading\Classroom;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\SchoolDayConfig;
use App\Models\FacultyLoading\Section;

/**
 * GeneticSchedulingService
 *
 * Implements a Genetic Algorithm (GA) to automatically generate an optimized
 * weekly class schedule for a given academic term.
 *
 * ── Terminology ─────────────────────────────────────────────────────────────
 *
 *  Slot Requirement  — one meeting session needed per week (derived from a
 *                      teaching LoadAssignment + the subject's sessions_per_week)
 *  Gene              — assignment of one slot requirement to a day / start time
 *                      / classroom
 *  Chromosome        — a complete candidate schedule (array of all genes)
 *  Population        — a set of chromosomes that evolves over generations
 *
 * ── Hard Constraints (high penalty) ─────────────────────────────────────────
 *  • No faculty conflict  — same faculty, same day, overlapping time
 *  • No room conflict     — same room, same day, overlapping time
 *  • No section conflict  — same section, same day, overlapping time
 *  • Day boundary         — slot outside allowed class hours for that day
 *  • Blocked period       — slot overlaps flag ceremony / lunch / club time
 *
 * ── Soft Constraints (lower penalty / bonus) ─────────────────────────────────
 *  • Even distribution    — section slots spread evenly Mon–Fri
 *  • Faculty daily hours  ≤ 6 h (normal) / ≤ 8 h (max)
 *  • Multi-session subjects spread across different days
 *  • Minimize idle gaps between a faculty's classes on the same day
 *  • Room type compatibility (lab subjects → lab rooms)
 *
 * ── Fitness Score ────────────────────────────────────────────────────────────
 *  fitness = Σ penalties (negative) + Σ bonuses (positive)
 *  A score ≥ 0 means the chromosome has no hard conflicts.
 */
class GeneticSchedulingService
{
    private const DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

    private const DEFAULT_SESSION_MINUTES = 60;

    // ── Penalty / Bonus weights ───────────────────────────────────────────

    private const W_HARD_CONFLICT      = 1000;  // faculty / room / section clash
    private const W_DAY_BOUNDARY       = 1000;  // slot outside allowed class hours
    private const W_OVER_MAX_HOURS     = 500;   // > 8 h/day (hard limit)
    private const W_OVER_NORMAL_HOURS  = 100;   // > 6 h/day per extra hour
    private const W_SAME_SUBJECT_DAY   = 300;   // two sessions of same subject same day
    private const W_ROOM_MISMATCH      = 100;   // lab subject in lecture room
    private const W_ROOM_HALF_MISMATCH = 50;    // lecture in lab room
    private const W_GAP_PER_HOUR       = 10;    // idle gap between classes per hour
    private const W_ROOM_MATCH_BONUS   = 50;    // room type matches subject type
    private const W_SPREAD_BONUS       = 20;    // each extra day a subject is spread across
    private const W_DAY_IMBALANCE      = 40;    // per extra slot beyond average in a section/day

    // ── Lab room / subject type definitions ──────────────────────────────

    private const LAB_ROOM_TYPES = [
        'laboratory', 'science_lab', 'physics_lab', 'chemistry_lab',
        'biology_lab', 'mathematics_lab', 'ict_lab', 'language_lab',
    ];

    private const LAB_SUBJECT_TYPES = ['laboratory', 'lecture_lab'];

    // ── Repair passes ─────────────────────────────────────────────────────
    private const MAX_REPAIR_PASSES = 10;

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Run the Genetic Algorithm and return the best schedule found.
     */
    public function generate(int $schoolYearId, int $termId, array $params = []): array
    {
        $populationSize = max(10, min(100, (int)   ($params['population_size'] ?? 30)));
        $mutationRate   = max(0.01, min(0.30, (float) ($params['mutation_rate']   ?? 0.05)));
        $maxGenerations = max(20, min(500, (int)   ($params['max_generations'] ?? 100)));
        $eliteCount     = max(1, (int) round($populationSize * 0.10));
        $tournamentSize = max(2, (int) round($populationSize * 0.20));

        $context = $this->buildContext($schoolYearId, $termId);

        if (empty($context['requirements'])) {
            return [
                'fitness'              => 0,
                'hard_conflicts'       => 0,
                'schedules_generated'  => 0,
                'schedules'            => [],
                'conflict_suggestions' => [],
                'warning'              => 'No unscheduled teaching assignments with a subject assigned were found for this term.',
            ];
        }

        $population = $this->initialPopulation($context, $populationSize);

        $bestChromosome = $population[0];
        $bestFitness    = PHP_INT_MIN;

        for ($gen = 0; $gen < $maxGenerations; $gen++) {
            $scored = [];
            foreach ($population as $chromosome) {
                $scored[] = [
                    'chromosome' => $chromosome,
                    'fitness'    => $this->fitness($chromosome, $context),
                ];
            }

            usort($scored, fn ($a, $b) => $b['fitness'] <=> $a['fitness']);

            if ($scored[0]['fitness'] > $bestFitness) {
                $bestFitness    = $scored[0]['fitness'];
                $bestChromosome = $scored[0]['chromosome'];
            }

            if ($bestFitness >= 0) {
                break;
            }

            $next = array_map(fn ($s) => $s['chromosome'], array_slice($scored, 0, $eliteCount));

            while (count($next) < $populationSize) {
                $parentA = $this->tournamentSelect($scored, $tournamentSize);
                $parentB = $this->tournamentSelect($scored, $tournamentSize);
                $child   = $this->crossover($parentA, $parentB);
                $child   = $this->mutate($child, $mutationRate, $context);
                $next[]  = $child;
            }

            $population = $next;
        }

        // Post-GA repair: fix day boundary violations first, then time conflicts
        $bestChromosome = $this->repairDayBoundaryViolations($bestChromosome, $context);
        if ($this->countHardConflicts($bestChromosome, $context) > 0) {
            $bestChromosome = $this->repairConflicts($bestChromosome, $context);
        }
        $bestFitness = $this->fitness($bestChromosome, $context);

        $schedules     = $this->chromosomeToSchedules($bestChromosome, $context, $schoolYearId, $termId);
        $hardConflicts = $this->countHardConflicts($bestChromosome, $context);

        // Collect any remaining conflict suggestions
        $conflictSuggestions = [];
        if ($hardConflicts > 0) {
            $conflictDetails = $this->detectConflictDetails($bestChromosome, $context);

            $conflictingReqIds = [];
            foreach ($conflictDetails as $cd) {
                $conflictingReqIds[$cd['req_id_a']] = true;
                $conflictingReqIds[$cd['req_id_b']] = true;
            }

            $alternativesByReq = [];
            foreach (array_keys($conflictingReqIds) as $rid) {
                $alternativesByReq[$rid] = $this->suggestAlternatives($rid, $bestChromosome, $context);
            }

            foreach ($conflictDetails as $cd) {
                $conflictSuggestions[] = array_merge($cd, [
                    'alternatives_a' => $alternativesByReq[$cd['req_id_a']] ?? [],
                    'alternatives_b' => $alternativesByReq[$cd['req_id_b']] ?? [],
                ]);
            }
        }

        return [
            'fitness'              => $bestFitness,
            'hard_conflicts'       => $hardConflicts,
            'schedules_generated'  => count($schedules),
            'schedules'            => $schedules,
            'conflict_suggestions' => $conflictSuggestions,
        ];
    }

    // ── Context Builder ───────────────────────────────────────────────────

    private function buildContext(int $schoolYearId, int $termId): array
    {
        // Load school day configs (per-day class hours + blocked periods)
        $dayConfigRecords = SchoolDayConfig::where('is_active', true)->get()->keyBy('day_of_week');

        $dayConfigs = [];
        foreach (self::DAYS as $day) {
            $cfg = $dayConfigRecords->get($day);
            if ($cfg) {
                $blocked = array_map(fn ($b) => [
                    's' => $this->timeToMinutes($b['start']),
                    'e' => $this->timeToMinutes($b['end']),
                ], $cfg->blocked_periods ?? []);

                $dayConfigs[$day] = [
                    'start'   => $this->timeToMinutes($cfg->classes_start),
                    'end'     => $this->timeToMinutes($cfg->classes_end),
                    'blocked' => $blocked,
                ];
            } else {
                // Fallback: 8:00 AM – 4:30 PM, lunch 12–1
                $dayConfigs[$day] = [
                    'start'   => 480,
                    'end'     => 990,
                    'blocked' => [['s' => 720, 'e' => 780]],
                ];
            }
        }

        $assignments = LoadAssignment::with(['subject', 'faculty'])
            ->where('academic_term_id', $termId)
            ->where('assignment_type', 'teaching')
            ->whereNotNull('subject_id')
            ->whereNotNull('section_id')
            ->get();

        $classrooms = Classroom::available()->get();
        if ($classrooms->isEmpty()) {
            $classrooms = Classroom::all();
        }
        $classroomsById = $classrooms->keyBy('id')->toArray();

        $labRoomIds     = $classrooms->filter(fn ($r) => in_array($r->classroom_type, self::LAB_ROOM_TYPES))->pluck('id')->values()->toArray();
        $lectureRoomIds = $classrooms->filter(fn ($r) => !in_array($r->classroom_type, self::LAB_ROOM_TYPES))->pluck('id')->values()->toArray();
        $allRoomIds     = $classrooms->pluck('id')->values()->toArray();

        if (empty($allRoomIds)) {
            return [
                'requirements'   => [],
                'classrooms'     => [],
                'labRoomIds'     => [],
                'lectureRoomIds' => [],
                'allRoomIds'     => [],
                'dayConfigs'     => $dayConfigs,
                'sectionDayConfigs' => [],
            ];
        }

        // Section home-room map
        $sectionIds        = $assignments->pluck('section_id')->filter()->unique()->values()->toArray();
        $sections          = Section::whereIn('id', $sectionIds)->get();
        $sectionClassrooms = $sections->whereNotNull('classroom_id')->pluck('classroom_id', 'id')->toArray();

        // Per-section break windows (recess/lunch), merged into each
        // section's day config alongside the global school_day_configs blocks.
        $sectionDayConfigs = [];
        foreach ($sections as $section) {
            $ownBreaks = [];
            foreach ([
                [$section->recess_start, $section->recess_end],
                [$section->lunch_start, $section->lunch_end],
            ] as [$breakStart, $breakEnd]) {
                if ($breakStart && $breakEnd) {
                    $ownBreaks[] = ['s' => $this->timeToMinutes($breakStart), 'e' => $this->timeToMinutes($breakEnd)];
                }
            }

            if (empty($ownBreaks)) {
                continue;
            }

            $sectionDayConfigs[$section->id] = [];
            foreach (self::DAYS as $day) {
                $base = $dayConfigs[$day];
                $sectionDayConfigs[$section->id][$day] = [
                    'start'   => $base['start'],
                    'end'     => $base['end'],
                    'blocked' => array_merge($base['blocked'], $ownBreaks),
                ];
            }
        }

        $requirements = [];
        $reqId        = 0;

        foreach ($assignments as $assignment) {
            $subject = $assignment->subject;

            // load_units = total class hours per week; session duration comes from
            // the subject's own minutes_per_session (e.g. a 2-hour lab block),
            // not a hardcoded 1 hour.
            $sessionsPerWeek   = max(1, (int) round((float) ($subject?->load_units ?? 1)));
            $minutesPerSession = max(1, (int) ($subject?->minutes_per_session ?? 60));
            $isLab             = in_array($subject?->subject_type, self::LAB_SUBJECT_TYPES);

            $fixedRoomId = null;
            $sectionRoom = $sectionClassrooms[$assignment->section_id] ?? null;
            if ($sectionRoom && isset($classroomsById[$sectionRoom])) {
                $fixedRoomId = (int) $sectionRoom;
            }

            for ($i = 0; $i < $sessionsPerWeek; $i++) {
                $requirements[$reqId] = [
                    'req_id'             => $reqId,
                    'load_assignment_id' => $assignment->id,
                    'user_id'            => $assignment->user_id,
                    'subject_id'         => $assignment->subject_id,
                    'section_id'         => $assignment->section_id,
                    'session_index'      => $i,
                    'minutes'            => $minutesPerSession,
                    'needs_lab'          => $isLab,
                    'fixed_room_id'      => $fixedRoomId,
                    '_subject_name'      => $subject?->name      ?? '—',
                    '_faculty_name'      => $assignment->faculty?->name ?? '—',
                ];
                $reqId++;
            }
        }

        return [
            'requirements'   => $requirements,
            'classrooms'     => $classroomsById,
            'labRoomIds'     => $labRoomIds,
            'lectureRoomIds' => $lectureRoomIds,
            'allRoomIds'     => $allRoomIds,
            'dayConfigs'     => $dayConfigs,
            'sectionDayConfigs' => $sectionDayConfigs,
        ];
    }

    /**
     * Resolve the effective day config for a section, merging in its own
     * recess/lunch windows (set in the Sections module) on
     * top of the global school_day_configs blocks. Falls back to the global
     * config when the section has no break times configured.
     */
    private function dayConfigFor(array $context, int $sectionId, string $day): array
    {
        return $context['sectionDayConfigs'][$sectionId][$day] ?? $context['dayConfigs'][$day];
    }

    // ── Population Initialization ─────────────────────────────────────────

    private function initialPopulation(array $context, int $size): array
    {
        $population = [];
        for ($i = 0; $i < $size; $i++) {
            $population[] = $this->randomChromosome($context);
        }
        return $population;
    }

    /**
     * Build a chromosome with even day distribution per section:
     * requirements for each section are shuffled then assigned to days
     * in round-robin order so slots are spread Mon–Fri.
     */
    private function randomChromosome(array $context): array
    {
        $chromosome = [];
        $days       = self::DAYS;

        // Group requirements by section
        $bySection = [];
        foreach ($context['requirements'] as $req) {
            $bySection[$req['section_id']][] = $req['req_id'];
        }

        foreach ($bySection as $sectionReqIds) {
            shuffle($sectionReqIds);

            foreach ($sectionReqIds as $i => $reqId) {
                // Round-robin day to spread evenly; try next day if this one has no valid slots
                $req  = $context['requirements'][$reqId];
                $day  = null;

                for ($offset = 0; $offset < count($days); $offset++) {
                    $candidate = $days[($i + $offset) % count($days)];
                    $cfg       = $this->dayConfigFor($context, $req['section_id'], $candidate);
                    if (!empty($this->validStartTimesForDay($cfg['start'], $cfg['end'], $cfg['blocked'], $req['minutes']))) {
                        $day = $candidate;
                        break;
                    }
                }

                // Ultimate fallback: first day
                if ($day === null) {
                    $day = $days[$i % count($days)];
                }

                $chromosome[$reqId] = $this->randomGene($req, $context, $day);
            }
        }

        return $chromosome;
    }

    private function randomGene(array $req, array $context, ?string $preferredDay = null): array
    {
        $day = $preferredDay ?? self::DAYS[array_rand(self::DAYS)];

        $cfg         = $this->dayConfigFor($context, $req['section_id'], $day);
        $validStarts = $this->validStartTimesForDay($cfg['start'], $cfg['end'], $cfg['blocked'], $req['minutes']);

        $start = empty($validStarts) ? $cfg['start'] : $validStarts[array_rand($validStarts)];
        $end   = $start + $req['minutes'];
        $room  = $req['fixed_room_id'] ?? $this->randomRoom($req, $context);

        return [
            'req_id'       => $req['req_id'],
            'day'          => $day,
            'start_min'    => $start,
            'end_min'      => $end,
            'classroom_id' => $room,
        ];
    }

    /**
     * Return all valid 30-minute-interval start times for a given day config and session duration.
     * A start time is valid when the session fits within class hours and does not overlap
     * any blocked period (lunch, flag ceremony, etc.).
     */
    private function validStartTimesForDay(int $dayStart, int $dayEnd, array $blocked, int $durationMin): array
    {
        $valid = [];

        for ($t = $dayStart; $t + $durationMin <= $dayEnd; $t += 30) {
            $slotEnd    = $t + $durationMin;
            $isBlocked  = false;

            foreach ($blocked as $b) {
                if ($t < $b['e'] && $slotEnd > $b['s']) {
                    $isBlocked = true;
                    break;
                }
            }

            if (!$isBlocked) {
                $valid[] = $t;
            }
        }

        return $valid;
    }

    private function randomRoom(array $req, array $context): int
    {
        if ($req['needs_lab'] && !empty($context['labRoomIds'])) {
            return $context['labRoomIds'][array_rand($context['labRoomIds'])];
        }
        if (!$req['needs_lab'] && !empty($context['lectureRoomIds'])) {
            return $context['lectureRoomIds'][array_rand($context['lectureRoomIds'])];
        }
        return $context['allRoomIds'][array_rand($context['allRoomIds'])];
    }

    // ── Fitness Evaluation ────────────────────────────────────────────────

    private function fitness(array $chromosome, array $context): int
    {
        $score = 0;

        $facultySlots = [];
        $roomSlots    = [];
        $sectionSlots = [];

        foreach ($chromosome as $gene) {
            $req  = $context['requirements'][$gene['req_id']];
            $slot = ['s' => $gene['start_min'], 'e' => $gene['end_min']];

            $facultySlots[$req['user_id']][$gene['day']][]         = $slot;
            $roomSlots[$gene['classroom_id']][$gene['day']][]      = $slot;
            $sectionSlots[$req['section_id']][$gene['day']][]      = $slot;
        }

        // ── Hard conflict penalties ───────────────────────────────────────
        $score -= $this->countSlotConflicts($facultySlots) * self::W_HARD_CONFLICT;
        $score -= $this->countSlotConflicts($roomSlots)    * self::W_HARD_CONFLICT;
        $score -= $this->countSlotConflicts($sectionSlots) * self::W_HARD_CONFLICT;

        // ── Day boundary violations (hard) ────────────────────────────────
        foreach ($chromosome as $gene) {
            $req = $context['requirements'][$gene['req_id']];
            $cfg = $this->dayConfigFor($context, $req['section_id'], $gene['day']);

            if ($gene['start_min'] < $cfg['start'] || $gene['end_min'] > $cfg['end']) {
                $score -= self::W_DAY_BOUNDARY;
                continue;
            }

            foreach ($cfg['blocked'] as $b) {
                if ($gene['start_min'] < $b['e'] && $gene['end_min'] > $b['s']) {
                    $score -= self::W_DAY_BOUNDARY;
                    break;
                }
            }
        }

        // ── Per-faculty per-day soft checks ──────────────────────────────
        foreach ($facultySlots as $dayMap) {
            foreach ($dayMap as $slots) {
                if (empty($slots)) {
                    continue;
                }
                usort($slots, fn ($a, $b) => $a['s'] <=> $b['s']);

                $totalMin = (int) array_sum(array_map(fn ($sl) => $sl['e'] - $sl['s'], $slots));
                $totalHrs = $totalMin / 60.0;

                if ($totalHrs > 8.0) {
                    $score -= self::W_OVER_MAX_HOURS;
                    $score -= self::W_OVER_NORMAL_HOURS * (int) ceil($totalHrs - 8.0);
                } elseif ($totalHrs > 6.0) {
                    $score -= self::W_OVER_NORMAL_HOURS * (int) ceil($totalHrs - 6.0);
                }

                for ($i = 1, $n = count($slots); $i < $n; $i++) {
                    $gapMin = $slots[$i]['s'] - $slots[$i - 1]['e'];
                    if ($gapMin > 0) {
                        $score -= (int) round(($gapMin / 60.0) * self::W_GAP_PER_HOUR);
                    }
                }
            }
        }

        // ── Same subject on same day penalty / spread bonus ───────────────
        $subjDayCount = [];
        foreach ($chromosome as $gene) {
            $laId = $context['requirements'][$gene['req_id']]['load_assignment_id'];
            $subjDayCount[$laId][$gene['day']] = ($subjDayCount[$laId][$gene['day']] ?? 0) + 1;
        }

        foreach ($subjDayCount as $dayCounts) {
            foreach ($dayCounts as $count) {
                if ($count > 1) {
                    $score -= ($count - 1) * self::W_SAME_SUBJECT_DAY;
                }
            }
            $uniqueDays = count($dayCounts);
            if ($uniqueDays > 1) {
                $score += ($uniqueDays - 1) * self::W_SPREAD_BONUS;
            }
        }

        // ── Per-section even-day-distribution bonus/penalty ───────────────
        $sectionDayCounts = [];
        foreach ($chromosome as $gene) {
            $req = $context['requirements'][$gene['req_id']];
            $sectionDayCounts[$req['section_id']][$gene['day']] = ($sectionDayCounts[$req['section_id']][$gene['day']] ?? 0) + 1;
        }

        foreach ($sectionDayCounts as $dayCounts) {
            if (count($dayCounts) <= 1) {
                continue;
            }
            $avg = array_sum($dayCounts) / count($dayCounts);
            foreach ($dayCounts as $count) {
                $excess = $count - (int) ceil($avg);
                if ($excess > 0) {
                    $score -= $excess * self::W_DAY_IMBALANCE;
                }
            }
        }

        // ── Room type compatibility ────────────────────────────────────────
        foreach ($chromosome as $gene) {
            $req      = $context['requirements'][$gene['req_id']];
            $roomData = $context['classrooms'][$gene['classroom_id']] ?? null;

            if ($roomData === null) {
                continue;
            }

            $isLabRoom = in_array($roomData['classroom_type'], self::LAB_ROOM_TYPES);
            $needsLab  = $req['needs_lab'];

            if ($needsLab && !$isLabRoom) {
                $score -= self::W_ROOM_MISMATCH;
            } elseif (!$needsLab && $isLabRoom) {
                $score -= self::W_ROOM_HALF_MISMATCH;
            } else {
                $score += self::W_ROOM_MATCH_BONUS;
            }
        }

        return $score;
    }

    private function countSlotConflicts(array $entityDayMap): int
    {
        $count = 0;
        foreach ($entityDayMap as $dayMap) {
            foreach ($dayMap as $slots) {
                $n = count($slots);
                for ($i = 0; $i < $n - 1; $i++) {
                    for ($j = $i + 1; $j < $n; $j++) {
                        if ($slots[$i]['s'] < $slots[$j]['e'] && $slots[$i]['e'] > $slots[$j]['s']) {
                            $count++;
                        }
                    }
                }
            }
        }
        return $count;
    }

    // ── Selection ─────────────────────────────────────────────────────────

    private function tournamentSelect(array $scored, int $k): array
    {
        $n           = count($scored);
        $best        = null;
        $bestFitness = PHP_INT_MIN;

        for ($i = 0; $i < $k; $i++) {
            $candidate = $scored[random_int(0, $n - 1)];
            if ($candidate['fitness'] > $bestFitness) {
                $bestFitness = $candidate['fitness'];
                $best        = $candidate['chromosome'];
            }
        }

        return $best;
    }

    // ── Crossover ─────────────────────────────────────────────────────────

    private function crossover(array $parentA, array $parentB): array
    {
        $keys = array_keys($parentA);
        $n    = count($keys);

        if ($n <= 1) {
            return $parentA;
        }

        $splitPoint = random_int(1, $n - 1);
        $child      = [];

        foreach ($keys as $i => $key) {
            $child[$key] = ($i < $splitPoint) ? $parentA[$key] : $parentB[$key];
        }

        return $child;
    }

    // ── Mutation ──────────────────────────────────────────────────────────

    private function mutate(array $chromosome, float $rate, array $context): array
    {
        foreach ($chromosome as $reqId => &$gene) {
            if (lcg_value() >= $rate) {
                continue;
            }

            $req          = $context['requirements'][$reqId];
            $mutationType = random_int(0, 2);

            switch ($mutationType) {
                case 0: // New day — only pick days that have valid slots for this session
                    $candidates = [];
                    foreach (self::DAYS as $d) {
                        $cfg = $this->dayConfigFor($context, $req['section_id'], $d);
                        if (!empty($this->validStartTimesForDay($cfg['start'], $cfg['end'], $cfg['blocked'], $req['minutes']))) {
                            $candidates[] = $d;
                        }
                    }
                    if (!empty($candidates)) {
                        $newDay      = $candidates[array_rand($candidates)];
                        $cfg         = $this->dayConfigFor($context, $req['section_id'], $newDay);
                        $validStarts = $this->validStartTimesForDay($cfg['start'], $cfg['end'], $cfg['blocked'], $req['minutes']);
                        $start       = $validStarts[array_rand($validStarts)];

                        $gene['day']       = $newDay;
                        $gene['start_min'] = $start;
                        $gene['end_min']   = $start + $req['minutes'];
                    }
                    break;

                case 1: // New start time within current day
                    $cfg         = $this->dayConfigFor($context, $req['section_id'], $gene['day']);
                    $validStarts = $this->validStartTimesForDay($cfg['start'], $cfg['end'], $cfg['blocked'], $req['minutes']);
                    if (!empty($validStarts)) {
                        $start             = $validStarts[array_rand($validStarts)];
                        $gene['start_min'] = $start;
                        $gene['end_min']   = $start + $req['minutes'];
                    }
                    break;

                case 2: // New classroom (skip if room is fixed for section)
                    if ($req['fixed_room_id'] === null) {
                        $gene['classroom_id'] = $this->randomRoom($req, $context);
                    }
                    break;
            }
        }

        return $chromosome;
    }

    // ── Result Serialization ──────────────────────────────────────────────

    private function chromosomeToSchedules(array $chromosome, array $context, int $syId, int $termId): array
    {
        $schedules = [];

        foreach ($chromosome as $gene) {
            $req      = $context['requirements'][$gene['req_id']];
            $roomData = $context['classrooms'][$gene['classroom_id']] ?? null;

            $schedules[] = [
                'load_assignment_id' => $req['load_assignment_id'],
                'user_id'            => $req['user_id'],
                'subject_id'         => $req['subject_id'],
                'section_id'         => $req['section_id'],
                'classroom_id'       => $gene['classroom_id'],
                'school_year_id'     => $syId,
                'academic_term_id'   => $termId,
                'day_of_week'        => $gene['day'],
                'start_time'         => $this->minutesToTime($gene['start_min']),
                'end_time'           => $this->minutesToTime($gene['end_min']),
                'status'             => 'tentative',
                'remarks'            => 'AI-generated schedule',
                // Preview metadata (prefixed _ — not stored in DB)
                '_req_id'         => $gene['req_id'],
                '_subject_name'   => $req['_subject_name'],
                '_faculty_name'   => $req['_faculty_name'],
                '_section_id'     => $req['section_id'],
                '_classroom_name' => $roomData ? ($roomData['name'] ?? '—') : '—',
                '_classroom_code' => $roomData ? ($roomData['code'] ?? '—') : '—',
            ];
        }

        $dayOrder = array_flip(self::DAYS);
        usort($schedules, function ($a, $b) use ($dayOrder) {
            $dayA = $dayOrder[$a['day_of_week']] ?? 99;
            $dayB = $dayOrder[$b['day_of_week']] ?? 99;
            return $dayA !== $dayB ? $dayA <=> $dayB : strcmp($a['start_time'], $b['start_time']);
        });

        return $schedules;
    }

    // ── Hard Conflict Count ───────────────────────────────────────────────

    private function countHardConflicts(array $chromosome, array $context): int
    {
        $facultySlots = [];
        $roomSlots    = [];
        $sectionSlots = [];

        foreach ($chromosome as $gene) {
            $req  = $context['requirements'][$gene['req_id']];
            $slot = ['s' => $gene['start_min'], 'e' => $gene['end_min']];

            $facultySlots[$req['user_id']][$gene['day']][]       = $slot;
            $roomSlots[$gene['classroom_id']][$gene['day']][]    = $slot;
            $sectionSlots[$req['section_id']][$gene['day']][]    = $slot;
        }

        $count = $this->countSlotConflicts($facultySlots)
               + $this->countSlotConflicts($roomSlots)
               + $this->countSlotConflicts($sectionSlots);

        // Also count day boundary violations as hard conflicts
        foreach ($chromosome as $gene) {
            $req = $context['requirements'][$gene['req_id']];
            $cfg = $this->dayConfigFor($context, $req['section_id'], $gene['day']);

            if ($gene['start_min'] < $cfg['start'] || $gene['end_min'] > $cfg['end']) {
                $count++;
                continue;
            }

            foreach ($cfg['blocked'] as $b) {
                if ($gene['start_min'] < $b['e'] && $gene['end_min'] > $b['s']) {
                    $count++;
                    break;
                }
            }
        }

        return $count;
    }

    // ── Post-GA Repair ────────────────────────────────────────────────────

    /**
     * Fix genes whose time falls outside their day's allowed class hours or
     * inside a blocked period (flag ceremony, lunch, club time).
     * This runs before the overlap-conflict repair.
     */
    private function repairDayBoundaryViolations(array $chromosome, array $context): array
    {
        foreach ($chromosome as $reqId => &$gene) {
            $req = $context['requirements'][$reqId];
            $cfg = $this->dayConfigFor($context, $req['section_id'], $gene['day']);

            $violation = ($gene['start_min'] < $cfg['start'] || $gene['end_min'] > $cfg['end']);

            if (!$violation) {
                foreach ($cfg['blocked'] as $b) {
                    if ($gene['start_min'] < $b['e'] && $gene['end_min'] > $b['s']) {
                        $violation = true;
                        break;
                    }
                }
            }

            if (!$violation) {
                continue;
            }

            // Try to find a valid slot on the same day first
            $validStarts = $this->validStartTimesForDay($cfg['start'], $cfg['end'], $cfg['blocked'], $req['minutes']);

            if (!empty($validStarts)) {
                $start             = $validStarts[array_rand($validStarts)];
                $gene['start_min'] = $start;
                $gene['end_min']   = $start + $req['minutes'];
            } else {
                // Move to a different day that has valid slots
                foreach (self::DAYS as $otherDay) {
                    if ($otherDay === $gene['day']) {
                        continue;
                    }
                    $otherCfg    = $this->dayConfigFor($context, $req['section_id'], $otherDay);
                    $otherStarts = $this->validStartTimesForDay($otherCfg['start'], $otherCfg['end'], $otherCfg['blocked'], $req['minutes']);
                    if (!empty($otherStarts)) {
                        $start             = $otherStarts[array_rand($otherStarts)];
                        $gene['day']       = $otherDay;
                        $gene['start_min'] = $start;
                        $gene['end_min']   = $start + $req['minutes'];
                        break;
                    }
                }
            }
        }

        return $chromosome;
    }

    /**
     * Greedily resolve pairwise time-overlap hard conflicts by relocating
     * each conflicting gene to its best conflict-free slot.
     */
    private function repairConflicts(array $chromosome, array $context): array
    {
        for ($pass = 0; $pass < self::MAX_REPAIR_PASSES; $pass++) {
            $conflicts = $this->detectConflictDetails($chromosome, $context);

            if (empty($conflicts)) {
                break;
            }

            $improved  = false;
            $attempted = [];

            foreach ($conflicts as $conflict) {
                foreach ([$conflict['req_id_a'], $conflict['req_id_b']] as $reqId) {
                    if (isset($attempted[$reqId])) {
                        continue;
                    }
                    $attempted[$reqId] = true;

                    $alternatives = $this->suggestAlternatives($reqId, $chromosome, $context);

                    if (empty($alternatives)) {
                        continue;
                    }

                    $alt = $alternatives[0];

                    $chromosome[$reqId] = [
                        'req_id'       => $reqId,
                        'day'          => $alt['day'],
                        'start_min'    => $this->timeToMinutes($alt['start_time']),
                        'end_min'      => $this->timeToMinutes($alt['end_time']),
                        'classroom_id' => $alt['classroom_id'],
                    ];

                    $improved = true;
                    break 2;
                }
            }

            if (!$improved) {
                break;
            }
        }

        return $chromosome;
    }

    // ── Conflict Detection & Alternatives ────────────────────────────────

    private function detectConflictDetails(array $chromosome, array $context): array
    {
        $maps = ['faculty' => [], 'room' => [], 'section' => []];

        foreach ($chromosome as $gene) {
            $req = $context['requirements'][$gene['req_id']];
            $day = $gene['day'];

            $maps['faculty'][$req['user_id']][$day][]        = ['gene' => $gene, 'req' => $req];
            $maps['room'][$gene['classroom_id']][$day][]     = ['gene' => $gene, 'req' => $req];
            $maps['section'][$req['section_id']][$day][]     = ['gene' => $gene, 'req' => $req];
        }

        $conflicts = [];
        $seen      = [];

        foreach ($maps as $conflictType => $entityMap) {
            foreach ($entityMap as $entityId => $dayMap) {
                foreach ($dayMap as $day => $entries) {
                    $n = count($entries);
                    for ($i = 0; $i < $n - 1; $i++) {
                        for ($j = $i + 1; $j < $n; $j++) {
                            $a  = $entries[$i];
                            $b  = $entries[$j];
                            $aG = $a['gene'];
                            $bG = $b['gene'];

                            if ($aG['start_min'] >= $bG['end_min'] || $aG['end_min'] <= $bG['start_min']) {
                                continue;
                            }

                            $pairKey = $conflictType
                                . ':' . min($aG['req_id'], $bG['req_id'])
                                . ':' . max($aG['req_id'], $bG['req_id']);

                            if (isset($seen[$pairKey])) {
                                continue;
                            }
                            $seen[$pairKey] = true;

                            $entityLabel = match ($conflictType) {
                                'faculty' => $a['req']['_faculty_name'],
                                'room'    => ($context['classrooms'][$entityId] ?? [])['name'] ?? "Room #{$entityId}",
                                'section' => "Section #{$entityId}",
                            };

                            $conflicts[] = [
                                'type'         => $conflictType,
                                'entity_id'    => $entityId,
                                'entity_label' => $entityLabel,
                                'day'          => $day,
                                'req_id_a'     => $aG['req_id'],
                                'req_id_b'     => $bG['req_id'],
                                'subject_a'    => $a['req']['_subject_name'],
                                'subject_b'    => $b['req']['_subject_name'],
                                'faculty_a'    => $a['req']['_faculty_name'],
                                'faculty_b'    => $b['req']['_faculty_name'],
                                'time_a'       => $this->minutesToTimeRange($aG['start_min'], $aG['end_min']),
                                'time_b'       => $this->minutesToTimeRange($bG['start_min'], $bG['end_min']),
                            ];
                        }
                    }
                }
            }
        }

        return $conflicts;
    }

    private function suggestAlternatives(int $reqId, array $chromosome, array $context): array
    {
        $req = $context['requirements'][$reqId];

        $facultySlots = [];
        $roomSlots    = [];
        $sectionSlots = [];

        foreach ($chromosome as $geneReqId => $gene) {
            if ($geneReqId === $reqId) {
                continue;
            }
            $r    = $context['requirements'][$geneReqId];
            $day  = $gene['day'];
            $slot = ['s' => $gene['start_min'], 'e' => $gene['end_min']];

            $facultySlots[$r['user_id']][$day][]       = $slot;
            $roomSlots[$gene['classroom_id']][$day][]  = $slot;
            $sectionSlots[$r['section_id']][$day][]    = $slot;
        }

        $fixedRoomId = $req['fixed_room_id'] ?? null;
        $roomPool    = $fixedRoomId !== null
            ? [$fixedRoomId]
            : ($req['needs_lab'] && !empty($context['labRoomIds'])
                ? $context['labRoomIds']
                : (!$req['needs_lab'] && !empty($context['lectureRoomIds'])
                    ? $context['lectureRoomIds']
                    : $context['allRoomIds']));

        $alternatives = [];

        foreach (self::DAYS as $day) {
            $cfg         = $this->dayConfigFor($context, $req['section_id'], $day);
            $validStarts = $this->validStartTimesForDay($cfg['start'], $cfg['end'], $cfg['blocked'], $req['minutes']);

            foreach ($validStarts as $start) {
                $end  = $start + $req['minutes'];
                $slot = ['s' => $start, 'e' => $end];

                if (!$this->isSlotFree($facultySlots[$req['user_id']][$day] ?? [], $slot)) {
                    continue;
                }
                if (!$this->isSlotFree($sectionSlots[$req['section_id']][$day] ?? [], $slot)) {
                    continue;
                }

                foreach ($roomPool as $roomId) {
                    if (!$this->isSlotFree($roomSlots[$roomId][$day] ?? [], $slot)) {
                        continue;
                    }

                    $roomData       = $context['classrooms'][$roomId] ?? null;
                    $alternatives[] = [
                        'day'            => $day,
                        'start_time'     => $this->minutesToTime($start),
                        'end_time'       => $this->minutesToTime($end),
                        'classroom_id'   => $roomId,
                        'classroom_name' => $roomData['name'] ?? '—',
                        'classroom_code' => $roomData['code'] ?? '—',
                    ];
                    break;
                }

                if (count($alternatives) >= 3) {
                    break 2;
                }
            }
        }

        return $alternatives;
    }

    private function isSlotFree(array $existingSlots, array $newSlot): bool
    {
        foreach ($existingSlots as $existing) {
            if ($newSlot['s'] < $existing['e'] && $newSlot['e'] > $existing['s']) {
                return false;
            }
        }
        return true;
    }

    private function minutesToTimeRange(int $start, int $end): string
    {
        $fmt = fn (int $m) => sprintf('%d:%02d %s',
            ($h = intdiv($m, 60)) % 12 ?: 12,
            $m % 60,
            $h >= 12 ? 'PM' : 'AM'
        );
        return $fmt($start) . '–' . $fmt($end);
    }

    // ── Utilities ─────────────────────────────────────────────────────────

    private function minutesToTime(int $minutes): string
    {
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;
        return sprintf('%02d:%02d:00', $h, $m);
    }

    private function timeToMinutes(string $time): int
    {
        $parts = explode(':', $time);
        return (int) $parts[0] * 60 + (int) ($parts[1] ?? 0);
    }
}
