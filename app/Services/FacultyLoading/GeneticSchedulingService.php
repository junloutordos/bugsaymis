<?php

namespace App\Services\FacultyLoading;

use App\Models\FacultyLoading\Classroom;
use App\Models\FacultyLoading\LoadAssignment;

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
 *
 * ── Soft Constraints (lower penalty / bonus) ─────────────────────────────────
 *  • Faculty daily teaching hours ≤ 6 h (normal) / ≤ 8 h (exigency max)
 *  • Multi-session subjects spread across different days
 *  • Minimize idle gaps between a faculty's classes on the same day
 *  • Room type compatibility (lab subjects → lab rooms, lecture → lecture rooms)
 *
 * ── Fitness Score ────────────────────────────────────────────────────────────
 *  fitness = Σ penalties (negative) + Σ bonuses (positive)
 *  A score ≥ 0 means the chromosome has no hard conflicts.
 */
class GeneticSchedulingService
{
    // ── School day time slots (minutes from midnight) ─────────────────────

    /**
     * Valid class start times.  12:00–13:00 is reserved for lunch.
     */
    private const START_TIMES = [
        420,  // 07:00
        480,  // 08:00
        540,  // 09:00
        600,  // 10:00
        660,  // 11:00
        780,  // 13:00
        840,  // 14:00
        900,  // 15:00
        960,  // 16:00
    ];

    private const LUNCH_START    = 720;   // 12:00
    private const LUNCH_END      = 780;   // 13:00
    private const SCHOOL_DAY_END = 1080;  // 18:00

    private const DEFAULT_SESSION_MINUTES = 60;

    private const DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

    // ── Penalty / Bonus weights ───────────────────────────────────────────

    private const W_HARD_CONFLICT     = 1000;   // faculty / room / section clash
    private const W_OVER_MAX_HOURS    = 500;    // > 8 h/day (hard limit)
    private const W_OVER_NORMAL_HOURS = 100;    // > 6 h/day per extra hour
    private const W_SAME_SUBJECT_DAY  = 300;    // two sessions of same subject on same day
    private const W_ROOM_MISMATCH     = 100;    // lab subject in lecture room (or vice versa)
    private const W_ROOM_HALF_MISMATCH = 50;    // lecture in lab room
    private const W_GAP_PER_HOUR      = 10;     // idle gap between classes per hour
    private const W_ROOM_MATCH_BONUS  = 50;     // room type matches subject type
    private const W_SPREAD_BONUS      = 20;     // each extra day a subject is spread across

    // ── Lab room / subject type definitions (mirrors ScheduleValidationService) ─

    private const LAB_ROOM_TYPES = [
        'laboratory', 'science_lab', 'physics_lab', 'chemistry_lab',
        'biology_lab', 'mathematics_lab', 'ict_lab', 'language_lab',
    ];

    private const LAB_SUBJECT_TYPES = ['laboratory', 'lecture_lab'];

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Run the Genetic Algorithm and return the best schedule found.
     *
     * @param  int   $schoolYearId
     * @param  int   $termId
     * @param  array $params {
     *   population_size: int   (10–100,  default 30)
     *   mutation_rate:   float (0.01–0.30, default 0.05)
     *   max_generations: int   (20–500,  default 100)
     * }
     * @return array {
     *   fitness:             int,
     *   hard_conflicts:      int,
     *   schedules_generated: int,
     *   schedules:           array,   // ready to insert / preview
     *   warning?:            string,
     * }
     */
    public function generate(int $schoolYearId, int $termId, array $params = []): array
    {
        $populationSize = max(10, min(100, (int)   ($params['population_size'] ?? 30)));
        $mutationRate   = max(0.01, min(0.30, (float) ($params['mutation_rate']   ?? 0.05)));
        $maxGenerations = max(20, min(500, (int)   ($params['max_generations'] ?? 100)));
        $eliteCount     = max(1, (int) round($populationSize * 0.10));
        $tournamentSize = max(2, (int) round($populationSize * 0.20));

        // 1. Load all data into memory
        $context = $this->buildContext($schoolYearId, $termId);

        if (empty($context['requirements'])) {
            return [
                'fitness'             => 0,
                'hard_conflicts'      => 0,
                'schedules_generated' => 0,
                'schedules'           => [],
                'warning'             => 'No unscheduled teaching assignments with a subject assigned were found for this term.',
            ];
        }

        // 2. Generate initial random population
        $population = $this->initialPopulation($context, $populationSize);

        $bestChromosome = $population[0];
        $bestFitness    = PHP_INT_MIN;

        // 3. Evolve
        for ($gen = 0; $gen < $maxGenerations; $gen++) {

            // Score every chromosome
            $scored = [];
            foreach ($population as $chromosome) {
                $scored[] = [
                    'chromosome' => $chromosome,
                    'fitness'    => $this->fitness($chromosome, $context),
                ];
            }

            // Sort best first
            usort($scored, fn ($a, $b) => $b['fitness'] <=> $a['fitness']);

            // Track global best
            if ($scored[0]['fitness'] > $bestFitness) {
                $bestFitness    = $scored[0]['fitness'];
                $bestChromosome = $scored[0]['chromosome'];
            }

            // Early termination: zero hard conflicts remain
            if ($bestFitness >= 0) {
                break;
            }

            // Build next generation
            // Elitism: carry top N chromosomes unchanged
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

        $schedules     = $this->chromosomeToSchedules($bestChromosome, $context, $schoolYearId, $termId);
        $hardConflicts = $this->countHardConflicts($bestChromosome, $context);

        return [
            'fitness'             => $bestFitness,
            'hard_conflicts'      => $hardConflicts,
            'schedules_generated' => count($schedules),
            'schedules'           => $schedules,
        ];
    }

    // ── Context Builder ───────────────────────────────────────────────────

    /**
     * Load all data needed for the GA into a plain-array in-memory structure
     * so the hot loop (fitness evaluation) never touches the database.
     */
    private function buildContext(int $schoolYearId, int $termId): array
    {
        // Teaching assignments for this term that have a subject & section assigned
        $assignments = LoadAssignment::with(['subject', 'faculty'])
            ->where('academic_term_id', $termId)
            ->where('assignment_type', 'teaching')
            ->whereNotNull('subject_id')
            ->whereNotNull('section_id')
            ->get();

        // Available classrooms (fall back to ALL rooms if none marked available)
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
            ];
        }

        // Expand each teaching assignment into individual slot requirements
        $requirements = [];
        $reqId        = 0;

        foreach ($assignments as $assignment) {
            $subject           = $assignment->subject;
            $sessionsPerWeek   = max(1, (int) ($subject?->sessions_per_week   ?? 1));
            $minutesPerSession = max(30, (int) ($subject?->minutes_per_session ?? self::DEFAULT_SESSION_MINUTES));
            $isLab             = in_array($subject?->subject_type, self::LAB_SUBJECT_TYPES);

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
                    // Metadata for the output preview (not used in fitness)
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
        ];
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

    private function randomChromosome(array $context): array
    {
        $chromosome = [];
        foreach ($context['requirements'] as $req) {
            $chromosome[$req['req_id']] = $this->randomGene($req, $context);
        }
        return $chromosome;
    }

    private function randomGene(array $req, array $context): array
    {
        $day   = self::DAYS[array_rand(self::DAYS)];
        $start = $this->randomStartTime($req['minutes']);
        $end   = $start + $req['minutes'];
        $room  = $this->randomRoom($req, $context);

        return [
            'req_id'       => $req['req_id'],
            'day'          => $day,
            'start_min'    => $start,
            'end_min'      => $end,
            'classroom_id' => $room,
        ];
    }

    private function randomStartTime(int $durationMin): int
    {
        // Filter out start times where the session would cross end-of-day
        // or begin during the lunch break
        $valid = array_values(array_filter(self::START_TIMES, function ($s) use ($durationMin) {
            $end = $s + $durationMin;
            if ($end > self::SCHOOL_DAY_END) {
                return false;
            }
            if ($s >= self::LUNCH_START && $s < self::LUNCH_END) {
                return false;
            }
            return true;
        }));

        if (empty($valid)) {
            return 480; // fallback: 08:00
        }

        return $valid[array_rand($valid)];
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

    /**
     * Evaluate a chromosome and return its fitness score.
     * Higher is better.  Score ≥ 0 means no hard conflicts.
     */
    private function fitness(array $chromosome, array $context): int
    {
        $score = 0;

        // Build in-memory occupancy maps for fast pairwise checks:
        //   [entity_id][day][] = ['s' => start_min, 'e' => end_min]
        $facultySlots = [];
        $roomSlots    = [];
        $sectionSlots = [];

        foreach ($chromosome as $gene) {
            $req = $context['requirements'][$gene['req_id']];
            $uid = $req['user_id'];
            $sid = $req['section_id'];
            $rid = $gene['classroom_id'];
            $day = $gene['day'];
            $slot = ['s' => $gene['start_min'], 'e' => $gene['end_min']];

            $facultySlots[$uid][$day][] = $slot;
            $roomSlots[$rid][$day][]    = $slot;
            $sectionSlots[$sid][$day][] = $slot;
        }

        // ── Hard conflict penalties ───────────────────────────────────────
        $score -= $this->countSlotConflicts($facultySlots) * self::W_HARD_CONFLICT;
        $score -= $this->countSlotConflicts($roomSlots)    * self::W_HARD_CONFLICT;
        $score -= $this->countSlotConflicts($sectionSlots) * self::W_HARD_CONFLICT;

        // ── Per-faculty per-day soft checks ──────────────────────────────
        foreach ($facultySlots as $dayMap) {
            foreach ($dayMap as $slots) {
                if (empty($slots)) {
                    continue;
                }

                // Sort by start time so gap calculation is meaningful
                usort($slots, fn ($a, $b) => $a['s'] <=> $b['s']);

                $totalMin = (int) array_sum(array_map(fn ($sl) => $sl['e'] - $sl['s'], $slots));
                $totalHrs = $totalMin / 60.0;

                if ($totalHrs > 8.0) {
                    $score -= self::W_OVER_MAX_HOURS;
                    $score -= self::W_OVER_NORMAL_HOURS * (int) ceil($totalHrs - 8.0);
                } elseif ($totalHrs > 6.0) {
                    $score -= self::W_OVER_NORMAL_HOURS * (int) ceil($totalHrs - 6.0);
                }

                // Gap penalty: time between consecutive slots (in hours)
                for ($i = 1, $n = count($slots); $i < $n; $i++) {
                    $gapMin = $slots[$i]['s'] - $slots[$i - 1]['e'];
                    if ($gapMin > 0) {
                        $score -= (int) round(($gapMin / 60.0) * self::W_GAP_PER_HOUR);
                    }
                }
            }
        }

        // ── Same subject on same day penalty / spread bonus ───────────────
        // Group genes by load_assignment_id → count per day
        $subjDayCount = [];  // [load_assignment_id][day] = count
        foreach ($chromosome as $gene) {
            $laId = $context['requirements'][$gene['req_id']]['load_assignment_id'];
            $subjDayCount[$laId][$gene['day']] = ($subjDayCount[$laId][$gene['day']] ?? 0) + 1;
        }

        foreach ($subjDayCount as $dayCounts) {
            foreach ($dayCounts as $count) {
                if ($count > 1) {
                    // Two sessions of the same subject on the same day
                    $score -= ($count - 1) * self::W_SAME_SUBJECT_DAY;
                }
            }
            // Spread bonus: more unique days = better distribution
            $uniqueDays = count($dayCounts);
            if ($uniqueDays > 1) {
                $score += ($uniqueDays - 1) * self::W_SPREAD_BONUS;
            }
        }

        // ── Room type compatibility ────────────────────────────────────────
        foreach ($chromosome as $gene) {
            $req      = $context['requirements'][$gene['req_id']];
            $roomData = $context['classrooms'][$gene['classroom_id']] ?? null;

            if ($roomData === null) {
                continue;
            }

            $isLabRoom  = in_array($roomData['classroom_type'], self::LAB_ROOM_TYPES);
            $needsLab   = $req['needs_lab'];

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

    /**
     * Count all pairwise time overlaps within an [entity][day][] occupancy map.
     * Two slots overlap when: startA < endB AND endA > startB.
     */
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

    /**
     * Tournament selection: draw $k random candidates, return the fittest one's chromosome.
     */
    private function tournamentSelect(array $scored, int $k): array
    {
        $n          = count($scored);
        $best       = null;
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

    /**
     * Single-point crossover: genes before the split point come from parent A,
     * genes from the split point onward come from parent B.
     */
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

    /**
     * For each gene, with probability $rate, randomly reassign one attribute
     * (day, time, or classroom).
     */
    private function mutate(array $chromosome, float $rate, array $context): array
    {
        foreach ($chromosome as $reqId => &$gene) {
            if (lcg_value() >= $rate) {
                continue;
            }

            $req          = $context['requirements'][$reqId];
            $mutationType = random_int(0, 2);

            switch ($mutationType) {
                case 0: // New day
                    $gene['day'] = self::DAYS[array_rand(self::DAYS)];
                    break;

                case 1: // New start time (keep same room)
                    $start             = $this->randomStartTime($req['minutes']);
                    $gene['start_min'] = $start;
                    $gene['end_min']   = $start + $req['minutes'];
                    break;

                case 2: // New classroom
                    $gene['classroom_id'] = $this->randomRoom($req, $context);
                    break;
            }
        }

        return $chromosome;
    }

    // ── Result Serialization ──────────────────────────────────────────────

    /**
     * Convert the best chromosome into an array of class_schedule-compatible rows,
     * enriched with display metadata for the preview UI.
     */
    private function chromosomeToSchedules(array $chromosome, array $context, int $syId, int $termId): array
    {
        $schedules = [];

        foreach ($chromosome as $gene) {
            $req      = $context['requirements'][$gene['req_id']];
            $roomData = $context['classrooms'][$gene['classroom_id']] ?? null;

            $schedules[] = [
                // Fields for class_schedules table
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
                // Preview metadata (prefixed with _ — not stored in DB)
                '_subject_name'   => $req['_subject_name'],
                '_faculty_name'   => $req['_faculty_name'],
                '_section_id'     => $req['section_id'],
                '_classroom_name' => $roomData ? ($roomData['name'] ?? '—') : '—',
                '_classroom_code' => $roomData ? ($roomData['code'] ?? '—') : '—',
            ];
        }

        // Sort by day order then start time for a readable preview
        $dayOrder = array_flip(self::DAYS);
        usort($schedules, function ($a, $b) use ($dayOrder) {
            $dayA = $dayOrder[$a['day_of_week']] ?? 99;
            $dayB = $dayOrder[$b['day_of_week']] ?? 99;
            if ($dayA !== $dayB) {
                return $dayA <=> $dayB;
            }
            return strcmp($a['start_time'], $b['start_time']);
        });

        return $schedules;
    }

    /**
     * Count the total number of hard conflicts in a chromosome (for the summary).
     */
    private function countHardConflicts(array $chromosome, array $context): int
    {
        $facultySlots = [];
        $roomSlots    = [];
        $sectionSlots = [];

        foreach ($chromosome as $gene) {
            $req  = $context['requirements'][$gene['req_id']];
            $slot = ['s' => $gene['start_min'], 'e' => $gene['end_min']];

            $facultySlots[$req['user_id']][$gene['day']][]  = $slot;
            $roomSlots[$gene['classroom_id']][$gene['day']][] = $slot;
            $sectionSlots[$req['section_id']][$gene['day']][] = $slot;
        }

        return $this->countSlotConflicts($facultySlots)
             + $this->countSlotConflicts($roomSlots)
             + $this->countSlotConflicts($sectionSlots);
    }

    // ── Utilities ─────────────────────────────────────────────────────────

    private function minutesToTime(int $minutes): string
    {
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;
        return sprintf('%02d:%02d:00', $h, $m);
    }
}
