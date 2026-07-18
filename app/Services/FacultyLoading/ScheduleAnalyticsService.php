<?php

namespace App\Services\FacultyLoading;

use App\Models\FacultyLoading\ClassSchedule;
use App\Models\FacultyLoading\LoadAssignment;

/**
 * ScheduleAnalyticsService
 *
 * Read-only timetable-quality analytics over one term's occupying
 * class_schedules rows. Complements LoadBalancingService (which analyses WHO
 * carries how many load units) by analysing WHEN the resulting classes land:
 * compactness, fatigue and fairness on the faculty axis; daily balance,
 * cognitive-load placement and subject spacing on the student/section axis.
 *
 * Never writes anything. Every metric threshold is documented next to its
 * deduction so the 0–100 Balance Scores stay explainable in the UI.
 */
class ScheduleAnalyticsService
{
    private const DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

    /** Two class intervals closer than this are one continuous teaching streak. */
    private const STREAK_JOIN_GAP_MIN = SchedulingConstants::STREAK_JOIN_GAP_MIN;

    /** Streaks beyond 3 contiguous hours are flagged (fatigue rule of thumb). */
    private const MAX_STREAK_MIN = SchedulingConstants::MAX_STREAK_MIN;

    /** A single day with more than 5 teaching hours is flagged as heavy. */
    private const HEAVY_DAY_MIN = 300;

    /** Midday window in which one gap of up to 60 min is forgiven as lunch. */
    private const LUNCH_WINDOW = [690, 810]; // 11:30–13:30
    private const LUNCH_ALLOWANCE_MIN = 60;

    /**
     * Afternoon boundary and heavy-subject prefixes for the cognitive-load
     * placement metric — shared with DeterministicSchedulingService via
     * SchedulingConstants so both stay in sync on what counts as "heavy"/"PM".
     */
    private const PM_START_MIN = SchedulingConstants::PM_START_MIN;
    private const HEAVY_CODE_PREFIXES = SchedulingConstants::HEAVY_SUBJECT_PREFIXES;

    /** Heatmap hour buckets (24h clock, inclusive start of each 1-hour cell). */
    private const HEAT_HOURS = [7, 8, 9, 10, 11, 12, 13, 14, 15, 16];

    // ── Entry point ──────────────────────────────────────────────────────────

    public function analyzeTerm(int $termId): array
    {
        $rows = ClassSchedule::occupying()
            ->where('academic_term_id', $termId)
            ->whereIn('day_of_week', self::DAYS)
            ->with([
                'subject:id,code,name,load_units',
                'faculty:id,name',
                'section:id,sectionname,levelid',
            ])
            ->get([
                'id', 'user_id', 'subject_id', 'section_id', 'entry_type',
                'session_type', 'category', 'title', 'day_of_week',
                'start_time', 'end_time', 'status',
            ]);

        $classRows    = $rows->filter(fn ($r) => ($r->entry_type ?? 'class') === 'class');
        $nonTeachRows = $rows->filter(fn ($r) => ($r->entry_type ?? 'class') !== 'class');

        $faculty  = $this->facultyMetrics($classRows, $nonTeachRows, $termId);
        $sections = $this->sectionMetrics($classRows);

        $facultyScores = array_column($faculty, 'score');
        $sectionScores = array_column($sections, 'score');
        $facultyAvg    = $facultyScores === [] ? null : (int) round(array_sum($facultyScores) / count($facultyScores));
        $sectionAvg    = $sectionScores === [] ? null : (int) round(array_sum($sectionScores) / count($sectionScores));

        $weeklyHours = array_values(array_filter(array_column($faculty, 'weekly_hours'), fn ($h) => $h > 0));

        return [
            'campus' => [
                'score'         => ($facultyAvg !== null && $sectionAvg !== null)
                    ? (int) round(($facultyAvg + $sectionAvg) / 2)
                    : ($facultyAvg ?? $sectionAvg),
                'faculty_score' => $facultyAvg,
                'section_score' => $sectionAvg,
                'fairness' => [
                    'mean_hours' => $weeklyHours === [] ? null : round(array_sum($weeklyHours) / count($weeklyHours), 1),
                    'cv'         => $weeklyHours === [] ? null : round(self::coefficientOfVariation($weeklyHours), 3),
                    'jain'       => $weeklyHours === [] ? null : round(self::jainIndex($weeklyHours), 3),
                ],
                'totals' => [
                    'faculty_count'   => count($faculty),
                    'section_count'   => count($sections),
                    'class_sessions'  => $classRows->count(),
                    'scheduled_hours' => round($classRows->sum(fn ($r) => $this->rowMinutes($r)) / 60, 1),
                ],
                'utilization' => $this->utilizationHeatmap($classRows),
                'heat_hours'  => self::HEAT_HOURS,
            ],
            'faculty'  => $faculty,
            'sections' => $sections,
            'issues'   => $this->topIssues($faculty, $sections),
        ];
    }

    // ── Faculty axis ─────────────────────────────────────────────────────────

    /**
     * @param \Illuminate\Support\Collection $classRows
     * @param \Illuminate\Support\Collection $nonTeachRows
     * @return array<int,array<string,mixed>>
     */
    private function facultyMetrics($classRows, $nonTeachRows, int $termId): array
    {
        // Teaching units per faculty (context column, not a timetable metric).
        $unitsByFaculty = LoadAssignment::where('academic_term_id', $termId)
            ->where('assignment_type', 'teaching')
            ->whereNotNull('subject_id')
            ->with('subject:id,load_units')
            ->get(['id', 'user_id', 'subject_id'])
            ->groupBy('user_id')
            ->map(fn ($group) => round($group->sum(fn ($a) => (float) ($a->subject->load_units ?? 0)), 1));

        $perFaculty = [];
        foreach ($classRows->merge($nonTeachRows) as $row) {
            $name = $row->faculty?->name;
            // Vacant/TBA placeholders would skew fairness — real people only.
            if (! $row->user_id || $name === null || str_starts_with($name, 'TBA')) {
                continue;
            }

            $id = (int) $row->user_id;
            $perFaculty[$id]['name'] = $name;
            $isClass = ($row->entry_type ?? 'class') === 'class';
            $perFaculty[$id][$isClass ? 'class' : 'other'][$row->day_of_week][] = [
                self::toMin($row->start_time), self::toMin($row->end_time),
            ];
            if ($isClass && $row->subject_id) {
                $perFaculty[$id]['subjects'][(int) $row->subject_id] = true;
            }
        }

        $result = [];
        foreach ($perFaculty as $id => $data) {
            $daily        = [];
            $weeklyMin    = 0;
            $streakMax    = 0;
            $occupiedMin  = 0;
            $idleMin      = 0;
            $nonTeachMin  = 0;

            foreach (self::DAYS as $day) {
                $class = self::mergeIntervals($data['class'][$day] ?? []);
                $other = self::mergeIntervals($data['other'][$day] ?? []);

                $dayTeach     = array_sum(array_map(fn ($iv) => $iv[1] - $iv[0], $class));
                $daily[$day]  = $dayTeach;
                $weeklyMin   += $dayTeach;
                $nonTeachMin += array_sum(array_map(fn ($iv) => $iv[1] - $iv[0], $other));

                $streakMax = max($streakMax, self::longestRun($class, self::STREAK_JOIN_GAP_MIN));

                // Compactness: how much of the campus span (first→last busy
                // interval) is actually occupied, with one midday gap of up to
                // 60 min forgiven as lunch.
                $all = self::mergeIntervals(array_merge($class, $other));
                if ($all !== []) {
                    $span     = end($all)[1] - $all[0][0];
                    $occupied = array_sum(array_map(fn ($iv) => $iv[1] - $iv[0], $all));
                    $idle     = max(0, $span - $occupied - self::lunchForgiveness($all));
                    $occupiedMin += $occupied;
                    $idleMin     += $idle;
                }
            }

            $compactness  = ($occupiedMin + $idleMin) > 0 ? $occupiedMin / ($occupiedMin + $idleMin) : 1.0;
            $teachingDays = count(array_filter($daily));
            $activeDaily  = array_values(array_filter($daily));
            $dailyStdDev  = count($activeDaily) > 1 ? self::stdDev($activeDaily) / 60 : 0.0;
            $preps        = count($data['subjects'] ?? []);
            $heavyDays    = count(array_filter($daily, fn ($m) => $m > self::HEAVY_DAY_MIN));

            $findings = [];
            $score    = 100;

            if ($streakMax > self::MAX_STREAK_MIN) {
                $score -= 15;
                $findings[] = self::finding('long_streak', 3,
                    sprintf('Longest uninterrupted teaching streak is %s — beyond the 3-hour fatigue guideline.', self::fmtDuration($streakMax)));
            }
            if ($heavyDays > 0) {
                $score -= min(20, 10 * $heavyDays);
                $findings[] = self::finding('heavy_day', 2,
                    $heavyDays.' day(s) carry more than 5 teaching hours.');
            }
            if ($dailyStdDev > 1.5) {
                $score -= 10;
                $findings[] = self::finding('uneven_days', 2,
                    sprintf('Teaching hours swing ±%.1fh between days — the week is unevenly loaded.', $dailyStdDev));
            }
            if ($compactness < 0.5) {
                $score -= 20;
                $findings[] = self::finding('fragmented_days', 3,
                    sprintf('Only %d%% of time on campus is occupied — the schedule is heavily fragmented by idle gaps.', (int) round($compactness * 100)));
            } elseif ($compactness < 0.7) {
                $score -= 10;
                $findings[] = self::finding('idle_gaps', 2,
                    sprintf('%s of idle gaps per week between classes (beyond lunch).', self::fmtDuration($idleMin)));
            }
            if ($preps >= 6) {
                $score -= 10;
                $findings[] = self::finding('many_preps', 2, $preps.' distinct subject preparations.');
            } elseif ($preps >= 4) {
                $score -= 5;
                $findings[] = self::finding('many_preps', 1, $preps.' distinct subject preparations.');
            }
            if ($teachingDays > 0 && $teachingDays <= 3 && $weeklyMin >= 12 * 60) {
                $score -= 10;
                $findings[] = self::finding('compressed_week', 2,
                    sprintf('%.1f weekly hours compressed into %d day(s).', $weeklyMin / 60, $teachingDays));
            }

            $result[$id] = [
                'id'                => $id,
                'name'              => $data['name'],
                'weekly_hours'      => round($weeklyMin / 60, 1),
                'daily_minutes'     => $daily,
                'streak_max_min'    => $streakMax,
                'idle_min'          => $idleMin,
                'compactness'       => round($compactness, 2),
                'preps'             => $preps,
                'teaching_days'     => $teachingDays,
                'units'             => (float) ($unitsByFaculty[$id] ?? 0),
                'non_teaching_hours' => round($nonTeachMin / 60, 1),
                'score'             => $score,
                'findings'          => $findings,
            ];
        }

        // Fairness pass — needs the roster mean, so it runs after the loop.
        $hours = array_values(array_filter(array_column($result, 'weekly_hours'), fn ($h) => $h > 0));
        $mean  = $hours === [] ? 0 : array_sum($hours) / count($hours);
        if ($mean > 0 && count($hours) >= 5) {
            foreach ($result as &$f) {
                if ($f['weekly_hours'] <= 0) {
                    continue;
                }
                $deviation = ($f['weekly_hours'] - $mean) / $mean;
                if ($deviation > 0.25) {
                    $f['score'] -= 10;
                    $f['findings'][] = self::finding('load_above_mean', 2,
                        sprintf('Weekly teaching hours are %d%% above the roster mean (%.1fh vs %.1fh).', (int) round($deviation * 100), $f['weekly_hours'], $mean));
                } elseif ($deviation < -0.25) {
                    $f['score'] -= 5;
                    $f['findings'][] = self::finding('load_below_mean', 1,
                        sprintf('Weekly teaching hours are %d%% below the roster mean (%.1fh vs %.1fh).', (int) round(abs($deviation) * 100), $f['weekly_hours'], $mean));
                }
            }
            unset($f);
        }

        foreach ($result as &$f) {
            $f['score'] = max(0, min(100, $f['score']));
            $f['grade'] = self::gradeOf($f['score']);
        }
        unset($f);

        usort($result, fn ($a, $b) => [$a['score'], $b['name']] <=> [$b['score'], $a['name']]);

        return array_values($result);
    }

    // ── Student / section axis ───────────────────────────────────────────────

    /**
     * @param \Illuminate\Support\Collection $classRows
     * @return array<int,array<string,mixed>>
     */
    private function sectionMetrics($classRows): array
    {
        // Per-grade weekly period capacity: schedulable teaching slots that a
        // homeroom section can actually use (elective windows and ILP-only
        // slots excluded).
        $capacityByGrade = [];
        foreach (range(7, 12) as $grade) {
            $count = 0;
            foreach (self::DAYS as $day) {
                foreach (SchedulingConstants::getSchedulableTeachingSlots($grade, $day) as $slot) {
                    if (($slot['type'] ?? 'CLASS') === 'ILP_ONLY') {
                        continue;
                    }
                    if (str_contains($slot['label'] ?? '', 'Elective')) {
                        continue;
                    }
                    $count++;
                }
            }
            $capacityByGrade[$grade] = $count;
        }

        $perSection = [];
        foreach ($classRows as $row) {
            $section = $row->section;
            // Real student sections only — ELEC-* synthetics are cross-section
            // groupings whose student-side impact can't be read per section.
            if (! $section || $section->levelid < 7 || $section->levelid > 12
                || str_starts_with((string) $section->sectionname, 'ELEC-')) {
                continue;
            }

            $id  = (int) $section->id;
            $day = $row->day_of_week;
            $perSection[$id]['name']  = $section->sectionname;
            $perSection[$id]['grade'] = (int) $section->levelid;
            $perSection[$id]['periods'][$day] = ($perSection[$id]['periods'][$day] ?? 0) + 1;

            if ($row->subject_id) {
                $perSection[$id]['subject_days'][(int) $row->subject_id][$day] =
                    ($perSection[$id]['subject_days'][(int) $row->subject_id][$day] ?? 0) + 1;
            }

            $code = strtoupper((string) ($row->subject?->code ?? ''));
            foreach (self::HEAVY_CODE_PREFIXES as $prefix) {
                if (str_starts_with($code, $prefix)) {
                    $minutes = $this->rowMinutes($row);
                    $perSection[$id]['heavy_min']    = ($perSection[$id]['heavy_min'] ?? 0) + $minutes;
                    if (self::toMin($row->start_time) >= self::PM_START_MIN) {
                        $perSection[$id]['heavy_pm_min'] = ($perSection[$id]['heavy_pm_min'] ?? 0) + $minutes;
                    }
                    break;
                }
            }
        }

        $result = [];
        foreach ($perSection as $id => $data) {
            $periods = [];
            foreach (self::DAYS as $day) {
                $periods[$day] = $data['periods'][$day] ?? 0;
            }
            $used     = array_sum($periods);
            $stdDev   = self::stdDev(array_values($periods));
            $capacity = $capacityByGrade[$data['grade']] ?? 0;
            $free     = max(0, $capacity - $used);

            $heavyMin   = $data['heavy_min'] ?? 0;
            $heavyShare = $heavyMin > 0 ? ($data['heavy_pm_min'] ?? 0) / $heavyMin : null;

            $repeatExtra = 0;   // sessions beyond the first of a subject on one day
            $spacing     = 0;   // multi-session subjects sharing fewer days than sessions
            foreach ($data['subject_days'] ?? [] as $days) {
                foreach ($days as $count) {
                    $repeatExtra += max(0, $count - 1);
                }
                $sessions = array_sum($days);
                if ($sessions >= 2 && $sessions <= 5) {
                    $spacing += max(0, $sessions - count($days));
                }
            }

            $findings = [];
            $score    = 100;

            if ($stdDev > 2.5) {
                $score -= 20;
                $findings[] = self::finding('very_uneven_week', 3,
                    sprintf('Daily periods swing heavily (σ %.1f) — some days are crushed while others are near-empty.', $stdDev));
            } elseif ($stdDev > 1.5) {
                $score -= 10;
                $findings[] = self::finding('uneven_week', 2,
                    sprintf('Daily periods are unevenly spread across the week (σ %.1f).', $stdDev));
            }
            if ($heavyShare !== null && $heavyShare > 0.6) {
                $score -= 20;
                $findings[] = self::finding('pm_heavy', 3,
                    sprintf('%d%% of Math/Science minutes land after 1 PM, in the fatigue window.', (int) round($heavyShare * 100)));
            } elseif ($heavyShare !== null && $heavyShare > 0.4) {
                $score -= 10;
                $findings[] = self::finding('pm_heavy', 2,
                    sprintf('%d%% of Math/Science minutes land after 1 PM.', (int) round($heavyShare * 100)));
            }
            if ($repeatExtra > 0) {
                $score -= min(15, 5 * $repeatExtra);
                $findings[] = self::finding('subject_repeat', 2,
                    $repeatExtra.' extra same-subject session(s) doubled up on a single day.');
            }
            if ($spacing > 0) {
                $score -= min(15, 5 * $spacing);
                $findings[] = self::finding('poor_spacing', 1,
                    'Multi-session subjects share days '.$spacing.' time(s) instead of spreading across the week.');
            }
            if ($free > 4) {
                $score -= 10;
                $findings[] = self::finding('under_scheduled', 2,
                    $free.' of '.$capacity.' weekly periods are still unscheduled.');
            }

            $result[] = [
                'id'                 => $id,
                'name'               => $data['name'],
                'grade_level'        => $data['grade'],
                'periods_per_day'    => $periods,
                'periods_used'       => $used,
                'capacity'           => $capacity,
                'free_periods'       => $free,
                'std_dev'            => round($stdDev, 2),
                'heavy_pm_share'     => $heavyShare === null ? null : round($heavyShare, 2),
                'repeat_extra'       => $repeatExtra,
                'spacing_violations' => $spacing,
                'score'              => max(0, min(100, $score)),
                'findings'           => $findings,
            ];
        }

        foreach ($result as &$s) {
            $s['grade'] = self::gradeOf($s['score']);
        }
        unset($s);

        usort($result, fn ($a, $b) => [$a['score'], $a['grade_level'], $a['name']] <=> [$b['score'], $b['grade_level'], $b['name']]);

        return $result;
    }

    // ── Campus rollups ───────────────────────────────────────────────────────

    /**
     * Day × hour matrix of concurrent class sessions (campus-wide activity).
     *
     * @return array<string,array<int,int>> [day][hour] => concurrent sessions
     */
    private function utilizationHeatmap($classRows): array
    {
        $matrix = [];
        foreach (self::DAYS as $day) {
            $matrix[$day] = array_fill_keys(self::HEAT_HOURS, 0);
        }

        foreach ($classRows as $row) {
            $start = self::toMin($row->start_time);
            $end   = self::toMin($row->end_time);
            foreach (self::HEAT_HOURS as $hour) {
                if ($start < ($hour + 1) * 60 && $end > $hour * 60) {
                    $matrix[$row->day_of_week][$hour]++;
                }
            }
        }

        return $matrix;
    }

    /**
     * Worst findings across both axes, ready for the "needs attention" feed.
     *
     * @return array<int,array<string,mixed>>
     */
    private function topIssues(array $faculty, array $sections, int $limit = 12): array
    {
        $issues = [];
        foreach ($faculty as $f) {
            foreach ($f['findings'] as $finding) {
                if ($finding['severity'] < 2) {
                    continue;
                }
                $issues[] = [
                    'scope'    => 'faculty',
                    'id'       => $f['id'],
                    'name'     => $f['name'],
                    'score'    => $f['score'],
                    'code'     => $finding['code'],
                    'severity' => $finding['severity'],
                    'detail'   => $finding['detail'],
                ];
            }
        }
        foreach ($sections as $s) {
            foreach ($s['findings'] as $finding) {
                if ($finding['severity'] < 2) {
                    continue;
                }
                $issues[] = [
                    'scope'    => 'section',
                    'id'       => $s['id'],
                    'name'     => 'G'.$s['grade_level'].' '.$s['name'],
                    'score'    => $s['score'],
                    'code'     => $finding['code'],
                    'severity' => $finding['severity'],
                    'detail'   => $finding['detail'],
                ];
            }
        }

        usort($issues, fn ($a, $b) => [$b['severity'], $a['score']] <=> [$a['severity'], $b['score']]);

        return array_slice($issues, 0, $limit);
    }

    // ── Pure helpers (static so they can be smoke-tested without a DB) ──────

    /** @param array<int,array{0:int,1:int}> $intervals */
    public static function mergeIntervals(array $intervals): array
    {
        if ($intervals === []) {
            return [];
        }
        usort($intervals, fn ($a, $b) => $a[0] <=> $b[0]);
        $merged = [array_map('intval', $intervals[0])];
        foreach (array_slice($intervals, 1) as $iv) {
            $last = &$merged[count($merged) - 1];
            if ($iv[0] <= $last[1]) {
                $last[1] = max($last[1], (int) $iv[1]);
            } else {
                $merged[] = [(int) $iv[0], (int) $iv[1]];
            }
        }

        return $merged;
    }

    /**
     * Longest continuous run over merged intervals, treating gaps of up to
     * $joinGap minutes as part of the same run.
     *
     * @param array<int,array{0:int,1:int}> $merged sorted, non-overlapping
     */
    public static function longestRun(array $merged, int $joinGap): int
    {
        $best = 0;
        $runStart = null;
        $runEnd = null;
        foreach ($merged as [$start, $end]) {
            if ($runEnd !== null && $start - $runEnd <= $joinGap) {
                $runEnd = max($runEnd, $end);
            } else {
                $runStart = $start;
                $runEnd = $end;
            }
            $best = max($best, $runEnd - $runStart);
        }

        return $best;
    }

    /**
     * Minutes of midday gap (inside 11:30–13:30) to forgive as lunch, capped
     * at 60 — computed from the actual gaps between merged busy intervals.
     *
     * @param array<int,array{0:int,1:int}> $merged sorted, non-overlapping
     */
    public static function lunchForgiveness(array $merged): int
    {
        [$lunchStart, $lunchEnd] = self::LUNCH_WINDOW;
        $forgiven = 0;
        for ($i = 1; $i < count($merged); $i++) {
            $gapStart = $merged[$i - 1][1];
            $gapEnd   = $merged[$i][0];
            $overlap  = min($gapEnd, $lunchEnd) - max($gapStart, $lunchStart);
            if ($overlap > 0) {
                $forgiven += $overlap;
            }
        }

        return min(self::LUNCH_ALLOWANCE_MIN, $forgiven);
    }

    /** @param array<int,float|int> $values */
    public static function stdDev(array $values): float
    {
        $n = count($values);
        if ($n < 2) {
            return 0.0;
        }
        $mean = array_sum($values) / $n;
        $variance = array_sum(array_map(fn ($v) => ($v - $mean) ** 2, $values)) / $n;

        return sqrt($variance);
    }

    /** @param array<int,float|int> $values */
    public static function coefficientOfVariation(array $values): float
    {
        $mean = array_sum($values) / max(1, count($values));

        return $mean > 0 ? self::stdDev($values) / $mean : 0.0;
    }

    /**
     * Jain's fairness index: (Σx)² / (n·Σx²). 1.0 = perfectly even allocation;
     * 1/n = one person carries everything.
     *
     * @param array<int,float|int> $values
     */
    public static function jainIndex(array $values): float
    {
        $n = count($values);
        if ($n === 0) {
            return 1.0;
        }
        $sumSquares = array_sum(array_map(fn ($v) => $v * $v, $values));

        return $sumSquares > 0 ? (array_sum($values) ** 2) / ($n * $sumSquares) : 1.0;
    }

    public static function toMin(?string $time): int
    {
        if (! $time) {
            return 0;
        }

        return ((int) substr($time, 0, 2)) * 60 + (int) substr($time, 3, 2);
    }

    private function rowMinutes($row): int
    {
        return max(0, self::toMin($row->end_time) - self::toMin($row->start_time));
    }

    private static function fmtDuration(int $minutes): string
    {
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;
        if ($h === 0) {
            return $m.'m';
        }

        return $m === 0 ? $h.'h' : "{$h}h {$m}m";
    }

    private static function finding(string $code, int $severity, string $detail): array
    {
        return ['code' => $code, 'severity' => $severity, 'detail' => $detail];
    }

    private static function gradeOf(int $score): string
    {
        return match (true) {
            $score >= 90 => 'excellent',
            $score >= 75 => 'good',
            $score >= 60 => 'fair',
            default      => 'attention',
        };
    }
}
