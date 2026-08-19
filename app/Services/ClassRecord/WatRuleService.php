<?php

namespace App\Services\ClassRecord;

use App\Models\ClassRecord\ClassRecord;
use App\Models\ClassRecord\ClassRecordAssessment;
use App\Models\ClassRecord\ClassRecordIlaDate;
use App\Models\ClassRecord\ClassRecordScore;
use App\Models\ClassRecord\ClassRecordStudent;
use App\Models\ClassRecord\ClassRecordTeacher;
use App\Models\ClassRecord\GradingCategory;
use App\Models\ClassRecord\QuarterExamWindow;
use App\Models\ClassRecord\WatReview;
use App\Models\FacultyLoading\ClassSchedule;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\Section;
use App\Models\User;
use App\Services\FacultyLoading\SchedulingConstants;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Weekly Assessment Tracker (WAT) rules — single source of truth for the
 * campus assessment-plotting policy:
 *
 *  - Plotting deadline: no later than 12:00 NN Friday preceding the week of
 *    implementation (leaves the Friday afternoon for coordinator/CID Chief review)
 *  - Daily:  max 3 graded assessments per section, of which max 2 major
 *  - Weekly: max 15 graded / 6 major per section (Mon–Sun window)
 *  - Major  = Long Test, or any assessment worth >= 10% of the quarterly grade
 */
class WatRuleService
{
    public const DAILY_GRADED_MAX = 3;

    public const DAILY_MAJOR_MAX = 2;

    public const WEEKLY_GRADED_MAX = 15;

    public const WEEKLY_MAJOR_MAX = 6;

    public const MAJOR_WEIGHT_SHARE = 0.10;

    public const OCCURRENCE_DATE_SQL = 'CASE
        WHEN crad.id IS NULL OR crad.is_primary = 1
        THEN class_record_assessments.activity_date
        ELSE crad.activity_date
    END';

    public const OCCURRENCE_PLOTTED_AT_SQL = 'CASE
        WHEN crad.id IS NULL OR crad.is_primary = 1
        THEN class_record_assessments.plotted_at
        ELSE crad.plotted_at
    END';

    /**
     * One row per assessment occurrence. The legacy primary date stays
     * authoritative so writes from blue instances remain visible during a
     * rolling deployment; child rows contribute the additional dates.
     */
    public static function assessmentOccurrencesQuery(int $schoolYearId)
    {
        return ClassRecordAssessment::schoolYearScopeQuery($schoolYearId)
            ->leftJoin(
                'class_record_assessment_dates as crad',
                'crad.class_record_assessment_id',
                '=',
                'class_record_assessments.id'
            )
            ->whereRaw(self::OCCURRENCE_DATE_SQL.' IS NOT NULL');
    }

    // ── Type derivation from the grading-option category ─────────────────────

    /**
     * WAT type is derived from the row's grading category — never chosen by
     * the teacher (it would duplicate what the grading option already says).
     * Unknown codes return null; displays fall back to the category name.
     * A campus-added category with code ILA maps automatically.
     */
    public static function deriveType(?string $categoryCode, int $assessmentNumber): ?string
    {
        return match (strtoupper((string) $categoryCode)) {
            'FA', 'QZ' => 'formative',
            'AA', 'P1', 'P2' => 'alternative',
            'ILA' => 'ila',
            'QE', 'SE', 'PE' => match ($assessmentNumber) {
                1 => 'long_test_1',
                2 => 'long_test_2',
                default => null,
            },
            default => null,
        };
    }

    // ── Major-assessment derivation ───────────────────────────────────────────

    public static function isMajor(?string $type, GradingCategory $category): bool
    {
        // Compliance-mode grading options (e.g. Values Education) score a
        // checkbox, not a number — there is no "major exam" concept to
        // apply the weight-share floor against, regardless of the
        // category's own weight/max_assessments or code.
        if (($category->gradingOption?->grading_mode ?? 'numeric') === 'compliance') {
            return false;
        }

        // A long_test_1/long_test_2 type (QE/SE/PE category, assessment #1
        // or #2) still has to clear the same 10%-per-assessment floor as
        // every other category — it is NOT automatically major regardless
        // of weight. A quarterly/summative/periodical exam category split
        // into enough pieces (or given a low enough weight) that each single
        // assessment is worth less than 10% of the quarter grade is not a
        // major assessment, the same as any other category.
        //
        // round() guards the exact-10% boundary against float division
        // (0.30 / 3 === 0.0999…) — QE at 10% each must count as major
        $perAssessmentShare = round((float) $category->weight / max(1, (int) $category->max_assessments), 6);

        return $perAssessmentShare >= self::MAJOR_WEIGHT_SHARE;
    }

    // ── Friday-before plotting deadline ───────────────────────────────────────

    public static function plottingDeadline(string $activityDate): Carbon
    {
        // Monday of the implementation week minus 3 days = preceding Friday,
        // cutoff at 12:00 NN (not end of day) so coordinators/CID Chief have
        // the Friday afternoon to review the week's plotted assessments.
        return Carbon::parse($activityDate)->startOfWeek(Carbon::MONDAY)->subDays(3)->setTime(12, 0, 0);
    }

    public static function violatesPlottingDeadline(string $activityDate): bool
    {
        return now()->greaterThan(self::plottingDeadline($activityDate));
    }

    // ── Scheduled-week window (deletion-approval gate) ────────────────────────

    /**
     * True when today falls within the Monday–Sunday week containing
     * $activityDate. A plotted assessment is only "announced to students" in
     * a way that matters once its week has arrived — before that, nothing's
     * been experienced yet, and after that week has fully passed, forcing an
     * ACIDAA approval for a stale/dead entry adds friction without protecting
     * anyone (the separate has-scores check still blocks removing anything
     * actually graded, in or out of this window).
     */
    public static function isWithinScheduledWeek(string $activityDate): bool
    {
        $start = Carbon::parse($activityDate)->startOfWeek(Carbon::MONDAY);
        $end = Carbon::parse($activityDate)->endOfWeek(Carbon::SUNDAY);

        return now()->between($start, $end);
    }

    // ── Quarter final exam window (daily/weekly caps + schedule-day exempt) ──

    /**
     * True when $date falls inside the configured Quarter Final Exam window
     * for this school year + quarter. During that window, Long Test/Quarterly
     * Exam entries are exempt from the daily/weekly caps and the schedule-day
     * rule — every subject legitimately sits its final exam in the same few
     * campus-wide days, which isn't the cramming those rules guard against.
     * The plotting-deadline rule (Friday before) still applies unchanged.
     */
    public static function isWithinExamWindow(int $schoolYearId, int $quarter, string $date): bool
    {
        return QuarterExamWindow::where('school_year_id', $schoolYearId)
            ->where('quarter', $quarter)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->exists();
    }

    /** Only Long Test/Quarterly Exam entries dated inside the window get the pass. */
    public static function isExamExempt(?string $assessmentType, int $schoolYearId, int $quarter, string $date): bool
    {
        return in_array($assessmentType, ['long_test_1', 'long_test_2'], true)
            && self::isWithinExamWindow($schoolYearId, $quarter, $date);
    }

    // ── Grade-pooled graded/major counts (exclude IDs being replaced) ────────

    /**
     * The sections sharing $sectionId's WAT budget: itself, plus any SCI- /
     * ELEC- prefixed synthetic sections at the same grade level (Faculty
     * Loading gives each Science Core/Elective class its own synthetic
     * Section row, carrying the same levelid as the grade it cuts across).
     * Those classes pull students from multiple homerooms at once, so their
     * assessments land on the same students' daily/weekly load and must
     * pool with it — not track a private budget on their own synthetic
     * section, which no one reviews independently.
     *
     * Deliberately NOT every section at that grade — two real homerooms
     * (e.g. Grade 7 "Opal" and Grade 7 "Sapphire") never share a budget with
     * each other, only with the grade's synthetic cross-section classes.
     *
     * Not scoped by school year here — sections.school_year_id isn't
     * reliably populated across all data (legacy sections only carry the
     * older syid column). The actual year boundary is already enforced by
     * schoolYearScopeQuery()'s cr.school_year_id filter on the assessments
     * side, so including a same-levelid synthetic section id from another
     * year here is harmless: no assessment of a different year will ever
     * match it.
     */
    public static function poolSectionIds(int $sectionId, int $grade): array
    {
        $syntheticIds = Section::where('levelid', $grade)
            ->where(function ($q) {
                $q->where('sectionname', 'like', 'SCI-%')
                    ->orWhere('sectionname', 'like', 'ELEC-%');
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id);

        return $syntheticIds->push($sectionId)->unique()->values()->all();
    }

    public static function gradeCountsOnDate(
        int $sectionId,
        int $grade,
        int $schoolYearId,
        string $date,
        array $excludeIds = [],
        array $candidateOccurrences = []
    ): array {
        $sectionIds = self::poolSectionIds($sectionId, $grade);

        return self::counts(
            self::assessmentOccurrencesQuery($schoolYearId)
                ->whereIn('cr.section_id', $sectionIds)
                ->whereRaw(self::OCCURRENCE_DATE_SQL.' = ?', [$date]),
            $schoolYearId,
            $excludeIds,
            $candidateOccurrences
        );
    }

    public static function gradeCountsInWeek(
        int $sectionId,
        int $grade,
        int $schoolYearId,
        string $weekStart,
        array $excludeIds = [],
        array $candidateOccurrences = []
    ): array {
        $sectionIds = self::poolSectionIds($sectionId, $grade);
        $monday = Carbon::parse($weekStart)->startOfWeek(Carbon::MONDAY);

        return self::counts(
            self::assessmentOccurrencesQuery($schoolYearId)
                ->whereIn('cr.section_id', $sectionIds)
                ->whereRaw(
                    self::OCCURRENCE_DATE_SQL.' BETWEEN ? AND ?',
                    [$monday->toDateString(), $monday->copy()->addDays(6)->toDateString()]
                ),
            $schoolYearId,
            $excludeIds,
            $candidateOccurrences
        );
    }

    /**
     * Count WAT load, consolidating simultaneous Elective or Science Core
     * assessments into one block. Assessments have no time column of their
     * own, so a block is resolved from the subject's sole matching schedule
     * slot for its section + weekday. Missing or ambiguous schedules safely
     * fall back to individual counting.
     *
     * Rows remain separate in tracker displays; only cap arithmetic is
     * consolidated. A block is major when any assessment inside it is major.
     */
    private static function counts(
        $query,
        int $schoolYearId,
        array $excludeIds,
        array $candidateOccurrences = []
    ): array {
        $rows = $query
            ->when($excludeIds, fn ($q) => $q->whereNotIn('class_record_assessments.id', $excludeIds))
            // Long Test/Quarterly Exam entries inside a configured exam window never
            // count toward the cap — every subject legitimately sits its final exam
            // in the same campus-wide days, section-wide.
            ->where(function ($q) {
                $q->whereNotIn('class_record_assessments.assessment_type', ['long_test_1', 'long_test_2'])
                    ->orWhereNotExists(function ($sub) {
                        $sub->select(DB::raw(1))
                            ->from('quarter_exam_windows as qew')
                            ->whereColumn('qew.school_year_id', 'cr.school_year_id')
                            ->whereColumn('qew.quarter', 'crq.quarter')
                            ->whereRaw(self::OCCURRENCE_DATE_SQL.' >= qew.start_date')
                            ->whereRaw(self::OCCURRENCE_DATE_SQL.' <= qew.end_date');
                    });
            })
            ->leftJoin('subjects as wat_subjects', 'wat_subjects.id', '=', 'cr.subject_id')
            ->select([
                'class_record_assessments.id as assessment_id',
                'class_record_assessments.is_graded',
                'class_record_assessments.is_major',
                'cr.section_id',
                'cr.subject_id',
                'wat_subjects.subject_type',
            ])
            ->selectRaw(self::OCCURRENCE_DATE_SQL.' as activity_date')
            ->get()
            ->map(fn ($row) => [
                'assessment_key' => 'db:'.$row->assessment_id,
                'activity_date' => $row->activity_date instanceof Carbon
                    ? $row->activity_date->toDateString()
                    : (string) $row->activity_date,
                'section_id' => (int) $row->section_id,
                'subject_id' => (int) $row->subject_id,
                'subject_type' => $row->subject_type,
                'is_graded' => (bool) $row->is_graded,
                'is_major' => (bool) $row->is_major,
            ]);

        $rows = $rows->concat(collect($candidateOccurrences)->map(fn ($row) => [
            'assessment_key' => 'candidate:'.(string) $row['assessment_key'],
            'activity_date' => Carbon::parse($row['activity_date'])->toDateString(),
            'section_id' => (int) $row['section_id'],
            'subject_id' => (int) $row['subject_id'],
            'subject_type' => $row['subject_type'] ?? null,
            'is_graded' => (bool) ($row['is_graded'] ?? true),
            'is_major' => (bool) ($row['is_major'] ?? false),
        ]));

        return self::consolidatedCounts($rows, $schoolYearId);
    }

    /**
     * @param Collection<int, array{
     *   assessment_key:string, activity_date:string, section_id:int,
     *   subject_id:int, subject_type:?string, is_graded:bool, is_major:bool
     * }> $rows
     */
    private static function consolidatedCounts(Collection $rows, int $schoolYearId): array
    {
        $gradedRows = $rows->where('is_graded', true)->values();
        if ($gradedRows->isEmpty()) {
            return ['graded' => 0, 'major' => 0];
        }

        $specialRows = $gradedRows->filter(
            fn ($row) => in_array($row['subject_type'], ['elective', 'science_core'], true)
        );
        $unresolvedSpecialRows = $specialRows->filter(
            fn ($row) => ! array_key_exists('resolved_slot', $row)
        );

        $scheduleByPairDate = self::scheduleResolutionForRows($unresolvedSpecialRows, $schoolYearId)
            ->map(fn ($resolution) => $resolution['wat_block']);

        // Each occurrence connects to its assessment token (preserving the
        // existing "multi-date assessment counts once per week" rule) and,
        // when resolvable, its shared block token. Connected components are
        // the effective WAT units.
        $parent = [];
        $find = function (string $token) use (&$parent, &$find): string {
            $parent[$token] ??= $token;
            if ($parent[$token] !== $token) {
                $parent[$token] = $find($parent[$token]);
            }

            return $parent[$token];
        };
        $union = function (string $left, string $right) use (&$parent, $find): void {
            $leftRoot = $find($left);
            $rightRoot = $find($right);
            if ($leftRoot !== $rightRoot) {
                $parent[$rightRoot] = $leftRoot;
            }
        };

        $rowTokens = $gradedRows->map(function ($row) use ($scheduleByPairDate, $union) {
            $assessmentToken = 'assessment|'.$row['assessment_key'];
            $tokens = [$assessmentToken];

            if (in_array($row['subject_type'], ['elective', 'science_core'], true)) {
                $slot = array_key_exists('resolved_slot', $row)
                    ? $row['resolved_slot']
                    : $scheduleByPairDate->get(self::scheduleSlotKey($row));
                if ($slot) {
                    $blockToken = 'block|'.$row['subject_type'].'|'.$row['activity_date'].'|'.$slot;
                    $union($assessmentToken, $blockToken);
                    $tokens[] = $blockToken;
                }
            }

            return ['tokens' => $tokens, 'is_major' => $row['is_major']];
        });

        $gradedRoots = $rowTokens
            ->map(fn ($row) => $find($row['tokens'][0]))
            ->unique();
        $majorRoots = $rowTokens
            ->where('is_major', true)
            ->map(fn ($row) => $find($row['tokens'][0]))
            ->unique();

        return ['graded' => $gradedRoots->count(), 'major' => $majorRoots->count()];
    }

    private static function scheduleSlotKey(array $row): string
    {
        return $row['section_id'].'|'.$row['subject_id'].'|'.$row['activity_date'];
    }

    /**
     * Resolve the display slot and WAT counting block for each
     * section/subject/date. Academic term dates matter because the same subject
     * may move to another time in the next semester.
     *
     * Electives use the grade's canonical elective window as their WAT block
     * whenever any of their schedule rows overlaps it. This covers production
     * schedules where one elective is stored as a single 100-minute row while
     * another is stored as two consecutive 50-minute rows. The display slot
     * remains conservative: zero or multiple distinct rows render no single
     * time rather than claiming an inaccurate class duration.
     *
     * Outside a configured elective window, the existing exact-slot fallback
     * remains in force. Science Core classes also retain exact-slot grouping.
     *
     * @return Collection<string, array{display_slot:?string, wat_block:?string}>
     */
    private static function scheduleResolutionForRows(Collection $rows, int $schoolYearId): Collection
    {
        $rows = $rows
            ->filter(fn ($row) => $row['section_id'] && $row['subject_id'] && $row['activity_date'])
            ->unique(fn ($row) => self::scheduleSlotKey($row))
            ->values();
        if ($rows->isEmpty()) {
            return collect();
        }

        $pairs = $rows
            ->map(fn ($row) => [
                'section_id' => $row['section_id'],
                'subject_id' => $row['subject_id'],
            ])
            ->unique(fn ($pair) => $pair['section_id'].'|'.$pair['subject_id'])
            ->values();

        $query = ClassSchedule::query()
            ->occupying()
            ->where('class_schedules.school_year_id', $schoolYearId)
            ->join('academic_terms as wat_terms', 'wat_terms.id', '=', 'class_schedules.academic_term_id')
            ->join('sections as wat_schedule_sections', 'wat_schedule_sections.id', '=', 'class_schedules.section_id')
            ->where(function ($outer) use ($pairs) {
                foreach ($pairs as $pair) {
                    $outer->orWhere(function ($inner) use ($pair) {
                        $inner->where('class_schedules.section_id', $pair['section_id'])
                            ->where('class_schedules.subject_id', $pair['subject_id']);
                    });
                }
            });

        $schedules = $query->get([
            'class_schedules.section_id',
            'class_schedules.subject_id',
            'class_schedules.day_of_week',
            'class_schedules.start_time',
            'class_schedules.end_time',
            'wat_schedule_sections.levelid as grade_level',
            'wat_terms.start_date as term_start_date',
            'wat_terms.end_date as term_end_date',
        ]);

        $electiveWindows = [];

        return $rows->mapWithKeys(function ($row) use ($schedules, &$electiveWindows) {
            $day = Carbon::parse($row['activity_date'])->format('l');
            $matchingSchedules = $schedules
                ->filter(fn ($schedule) => (int) $schedule->section_id === (int) $row['section_id']
                    && (int) $schedule->subject_id === (int) $row['subject_id']
                    && $schedule->day_of_week === $day);
            $inTermSchedules = $matchingSchedules
                ->filter(fn ($schedule) => $row['activity_date'] >= Carbon::parse($schedule->term_start_date)->toDateString()
                    && $row['activity_date'] <= Carbon::parse($schedule->term_end_date)->toDateString());
            // Legacy/test records can carry dates just outside their term. In
            // that case a single unambiguous school-year slot is still useful;
            // differing semester slots remain ambiguous and do not consolidate.
            $selectedSchedules = $inTermSchedules->isNotEmpty() ? $inTermSchedules : $matchingSchedules;
            $slots = $selectedSchedules
                ->map(fn ($schedule) => substr((string) $schedule->start_time, 0, 5)
                    .'|'.substr((string) $schedule->end_time, 0, 5))
                ->unique()
                ->values();
            $displaySlot = $slots->count() === 1 ? $slots->first() : null;
            $watBlock = $displaySlot;

            if (($row['subject_type'] ?? null) === 'elective') {
                $grades = $selectedSchedules->pluck('grade_level')
                    ->filter(fn ($grade) => $grade !== null)
                    ->map(fn ($grade) => (int) $grade)
                    ->unique()
                    ->values();

                if ($grades->count() === 1) {
                    $grade = $grades->first();
                    $windowKey = $grade.'|'.$day;
                    $windows = $electiveWindows[$windowKey]
                        ??= SchedulingConstants::getElectiveWindows($grade, $day);
                    $matchingWindows = collect($windows)->filter(
                        fn ($window) => $selectedSchedules->contains(
                            fn ($schedule) => SchedulingConstants::timesOverlap(
                                substr((string) $schedule->start_time, 0, 5),
                                substr((string) $schedule->end_time, 0, 5),
                                $window['start'],
                                $window['end']
                            )
                        )
                    )->values();

                    if ($matchingWindows->count() === 1) {
                        $window = $matchingWindows->first();
                        $watBlock = $window['start'].'|'.$window['end'];
                    }
                }
            }

            // Science Core has no fixed campus-wide window catalog the way
            // Electives do (its real placement is dynamic, driven by
            // room/faculty availability — see SchedulingConstants' "VESTIGIAL"
            // Science Core notes), so a class whose period happens to be
            // stored as consecutive rows (e.g. two 50-min rows instead of one
            // merged 100-min row) still needs to resolve to the same block as
            // a sibling Science Core class covering the identical span with a
            // single row. Falls back to merging THIS row's own back-to-back
            // schedule rows into one continuous span whenever nothing above
            // resolved a block — safe for every subject type since only
            // elective/science_core rows are ever unioned into a shared block
            // (see consolidatedCounts()).
            if ($watBlock === null) {
                $watBlock = self::mergedContiguousSpan($selectedSchedules);
            }

            return [self::scheduleSlotKey($row) => [
                'display_slot' => $displaySlot,
                'wat_block' => $watBlock,
            ]];
        });
    }

    /**
     * Collapse a set of schedule rows into one "start|end" span when they
     * chain together with no gap (one row's end equals the next row's
     * start) — e.g. 07:30-08:20 + 08:20-09:10 becomes "07:30|09:10". Returns
     * null when the rows don't form a single continuous span (a genuine gap,
     * or a real double-booking), matching the existing conservative fallback
     * used elsewhere in this file.
     */
    private static function mergedContiguousSpan(Collection $schedules): ?string
    {
        if ($schedules->isEmpty()) {
            return null;
        }

        $intervals = $schedules
            ->map(fn ($schedule) => [
                substr((string) $schedule->start_time, 0, 5),
                substr((string) $schedule->end_time, 0, 5),
            ])
            ->unique(fn ($interval) => implode('|', $interval))
            ->sortBy(fn ($interval) => $interval[0])
            ->values();

        [$start, $end] = $intervals->first();

        foreach ($intervals->slice(1) as [$nextStart, $nextEnd]) {
            if ($nextStart !== $end) {
                return null;
            }
            $end = $nextEnd;
        }

        return $start.'|'.$end;
    }

    // ── Schedule-day check (warn-only) ────────────────────────────────────────

    /**
     * True when the subject meets the section on the weekday of $date per the
     * class schedule. Returns null (unknown) when the record has no subject
     * or section link to check against.
     */
    public static function meetsOnDate(?int $subjectId, ?int $sectionId, int $schoolYearId, string $date): ?bool
    {
        if (! $subjectId || ! $sectionId) {
            return null;
        }

        $hasAny = ClassSchedule::where('section_id', $sectionId)
            ->where('subject_id', $subjectId)
            ->where('school_year_id', $schoolYearId)
            ->exists();

        if (! $hasAny) {
            return null; // not scheduled at all yet — nothing to validate against
        }

        return ClassSchedule::where('section_id', $sectionId)
            ->where('subject_id', $subjectId)
            ->where('school_year_id', $schoolYearId)
            ->where('day_of_week', Carbon::parse($date)->format('l'))
            ->exists();
    }

    // ── Weekly tracker data (Homeroom / ACIDAA views + print) ────────────────

    /**
     * Full WAT dataset for one homeroom's grade-week: per-day assessment rows
     * (including any Science Core/Elective sessions pooled into this grade's
     * budget — see poolSectionIds()), with compliance %, per-day and weekly
     * graded/major tallies, limit flags, and the ACIDAA review record if one
     * exists.
     */
    public static function weekData(int $sectionId, int $schoolYearId, string $weekStart): array
    {
        $monday = Carbon::parse($weekStart)->startOfWeek(Carbon::MONDAY);
        $friday = $monday->copy()->addDays(4);

        $grade = Section::where('id', $sectionId)->value('levelid');
        $poolSectionIds = $grade !== null ? self::poolSectionIds($sectionId, (int) $grade) : [$sectionId];

        $rows = self::assessmentOccurrencesQuery($schoolYearId)
            ->whereIn('cr.section_id', $poolSectionIds)
            ->join('grading_categories as gc', 'class_record_assessments.grading_category_id', '=', 'gc.id')
            ->leftJoin('subjects as wat_subjects', 'wat_subjects.id', '=', 'cr.subject_id')
            ->leftJoin('sections as sec', 'sec.id', '=', 'cr.section_id')
            ->whereRaw(
                self::OCCURRENCE_DATE_SQL.' BETWEEN ? AND ?',
                [$monday->toDateString(), $monday->copy()->addDays(6)->toDateString()]
            )
            ->orderByRaw(self::OCCURRENCE_DATE_SQL)
            ->orderBy('cr.subject_name')
            ->select([
                'class_record_assessments.id',
                'class_record_assessments.class_record_quarter_id',
                'class_record_assessments.title',
                'class_record_assessments.assessment_type',
                'class_record_assessments.is_graded',
                'class_record_assessments.is_major',
                'class_record_assessments.max_score',
                'crad.id as assessment_date_id',
                'cr.id as class_record_id',
                'cr.subject_id',
                'cr.section_id',
                'cr.subject_name',
                'cr.category_label',
                'cr.teacher_id',
                'wat_subjects.subject_type',
                'gc.name as category_name',
                'gc.code as category_code',
                'sec.sectionname as row_section_name',
            ])
            ->selectRaw(self::OCCURRENCE_DATE_SQL.' as activity_date')
            ->selectRaw(self::OCCURRENCE_PLOTTED_AT_SQL.' as plotted_at')
            ->get();

        // Non-graded ILA dates (the default) are still surfaced in the WAT
        // grid — informational only, never counted toward the graded/major
        // caps below — so coordinators can see the ILA session happened even
        // when nobody elected to grade it. Graded ILA dates need no special
        // handling here: they're already a normal row in $rows above via
        // their linked class_record_assessments entry.
        $ilaDates = ClassRecordIlaDate::where('is_graded', false)
            ->whereBetween('date', [$monday->toDateString(), $monday->copy()->addDays(6)->toDateString()])
            ->whereHas('quarter.classRecord', function ($q) use ($poolSectionIds, $schoolYearId) {
                $q->whereIn('section_id', $poolSectionIds)
                    ->where('school_year_id', $schoolYearId)
                    // Same archived-record exclusion as schoolYearScopeQuery():
                    // a pending ILA date on an archived record must not surface.
                    ->where('status', '<>', 'archived');
            })
            ->with([
                'quarter.classRecord:id,subject_name,subject_id,teacher_id,section_id,category_label',
                'quarter.classRecord.section:id,sectionname',
            ])
            ->get(['id', 'class_record_quarter_id', 'date', 'title']);

        $teachers = User::whereIn('id', $rows->pluck('teacher_id')
            ->concat($ilaDates->pluck('quarter.classRecord.teacher_id'))
            ->filter()->unique())
            ->get(['id', 'name'])->keyBy('id');

        // Compliance: students with a recorded score / quarter roster size
        $quarterIds = $rows->pluck('class_record_quarter_id')->unique()->values();
        $rosterCounts = ClassRecordStudent::whereIn('class_record_quarter_id', $quarterIds)
            ->selectRaw('class_record_quarter_id, COUNT(*) as n')
            ->groupBy('class_record_quarter_id')
            ->pluck('n', 'class_record_quarter_id');
        $scoreCounts = ClassRecordScore::whereIn('class_record_assessment_id', $rows->pluck('id'))
            ->whereNotNull('score')
            ->selectRaw('class_record_assessment_id, COUNT(*) as n')
            ->groupBy('class_record_assessment_id')
            ->pluck('n', 'class_record_assessment_id');

        // Time: assessments have no time-of-day of their own — resolve the
        // class period's scheduled start/end from ClassSchedule, matched on
        // section + subject + the activity's day-of-week. Batched by the
        // distinct (section_id, subject_id) pairs actually present this
        // week, keyed by "sectionId|subjectId|DayName" for O(1) lookup per
        // item. Falls back to null (rendered as "—") when subject_id is
        // absent (e.g. an elective/other class record with no subject link)
        // or no matching schedule row exists.
        $scheduleRows = $rows
            ->filter(fn ($row) => $row->section_id && $row->subject_id)
            ->map(fn ($row) => [
                'section_id' => (int) $row->section_id,
                'subject_id' => (int) $row->subject_id,
                'subject_type' => $row->subject_type,
                'activity_date' => $row->activity_date instanceof Carbon
                    ? $row->activity_date->toDateString()
                    : Carbon::parse((string) $row->activity_date)->toDateString(),
            ])
            ->concat($ilaDates
                ->filter(fn ($ilaDate) => $ilaDate->quarter->classRecord?->section_id
                    && $ilaDate->quarter->classRecord?->subject_id)
                ->map(fn ($ilaDate) => [
                    'section_id' => (int) $ilaDate->quarter->classRecord->section_id,
                    'subject_id' => (int) $ilaDate->quarter->classRecord->subject_id,
                    'activity_date' => $ilaDate->date->toDateString(),
                ]))
            ->unique(fn ($row) => self::scheduleSlotKey($row))
            ->values();

        $scheduleResolutions = self::scheduleResolutionForRows($scheduleRows, $schoolYearId);

        $items = $rows->map(function ($row) use ($teachers, $rosterCounts, $scoreCounts, $scheduleResolutions, $sectionId) {
            $roster = (int) ($rosterCounts[$row->class_record_quarter_id] ?? 0);
            $submitted = (int) ($scoreCounts[$row->id] ?? 0);
            $date = $row->activity_date instanceof Carbon
                ? $row->activity_date
                : Carbon::parse((string) $row->activity_date);

            $scheduleKey = $row->section_id && $row->subject_id
                ? self::scheduleSlotKey([
                    'section_id' => $row->section_id,
                    'subject_id' => $row->subject_id,
                    'activity_date' => $date->toDateString(),
                ])
                : null;
            $scheduleResolution = $scheduleKey ? $scheduleResolutions->get($scheduleKey) : null;
            $scheduleSlot = $scheduleResolution['display_slot'] ?? null;
            $watBlock = $scheduleResolution['wat_block'] ?? null;
            [$scheduleStart, $scheduleEnd] = $scheduleSlot
                ? explode('|', $scheduleSlot, 2)
                : [null, null];

            // Rows pooled in from a Science Core/Elective synthetic section
            // (grade-wide, not this specific homeroom) are tagged so the
            // coordinator can see why their own tally moved.
            $pooledTag = null;
            if ((int) $row->section_id !== $sectionId) {
                $pooledTag = str_starts_with((string) $row->row_section_name, 'SCI-')
                    ? 'Science Core'
                    : (str_starts_with((string) $row->row_section_name, 'ELEC-') ? 'Elective' : null);
            }
            $subjectName = $row->category_label ? "{$row->subject_name} — {$row->category_label}" : $row->subject_name;

            return [
                'id' => $row->assessment_date_id
                    ? "{$row->id}:{$row->assessment_date_id}"
                    : (string) $row->id,
                'assessment_id' => $row->id,
                'class_record_id' => $row->class_record_id,
                'date' => $date->toDateString(),
                'title' => $row->title,
                'subject_name' => $pooledTag ? "{$subjectName} ({$pooledTag})" : $subjectName,
                'teacher_name' => $teachers[$row->teacher_id]?->name,
                'assessment_type' => $row->assessment_type,
                'type_label' => ClassRecordAssessment::TYPES[$row->assessment_type] ?? $row->category_name,
                'category_code' => $row->category_code,
                'is_graded' => (bool) $row->is_graded,
                'is_major' => (bool) $row->is_major,
                '_wat_section_id' => (int) $row->section_id,
                '_wat_subject_id' => (int) $row->subject_id,
                '_wat_subject_type' => $row->subject_type,
                '_wat_slot' => $scheduleSlot,
                '_wat_block' => $watBlock,
                'plotted_at' => $row->plotted_at,
                'roster_count' => $roster,
                'submitted_count' => $row->is_graded ? $submitted : null,
                'compliance' => ($row->is_graded && $roster > 0)
                    ? round($submitted / $roster * 100, 1)
                    : null,
                'time_label' => $scheduleSlot
                    ? $scheduleStart.'–'.$scheduleEnd
                    : null,
            ];
        });

        $ilaItems = $ilaDates->map(function ($ilaDate) use ($teachers, $sectionId, $scheduleResolutions) {
            $classRecord = $ilaDate->quarter->classRecord;
            $pooledTag = null;
            if ($classRecord && (int) $classRecord->section_id !== $sectionId) {
                $sectionName = (string) $classRecord->section?->sectionname;
                $pooledTag = str_starts_with($sectionName, 'SCI-')
                    ? 'Science Core'
                    : (str_starts_with($sectionName, 'ELEC-') ? 'Elective' : null);
            }
            $subjectName = $classRecord?->category_label
                ? "{$classRecord->subject_name} — {$classRecord->category_label}"
                : $classRecord?->subject_name;

            $scheduleKey = $classRecord?->section_id && $classRecord?->subject_id
                ? self::scheduleSlotKey([
                    'section_id' => $classRecord->section_id,
                    'subject_id' => $classRecord->subject_id,
                    'activity_date' => $ilaDate->date->toDateString(),
                ])
                : null;
            $scheduleResolution = $scheduleKey ? $scheduleResolutions->get($scheduleKey) : null;
            $scheduleSlot = $scheduleResolution['display_slot'] ?? null;
            [$scheduleStart, $scheduleEnd] = $scheduleSlot
                ? explode('|', $scheduleSlot, 2)
                : [null, null];

            return [
                'id' => 'ila-'.$ilaDate->id,
                'ila_date_id' => $ilaDate->id,
                'class_record_id' => $classRecord?->id,
                'date' => $ilaDate->date->toDateString(),
                'title' => $ilaDate->title ?: 'Independent Learning Activity',
                'subject_name' => $pooledTag ? "{$subjectName} ({$pooledTag})" : $subjectName,
                'teacher_name' => $teachers[$classRecord?->teacher_id]?->name,
                'assessment_type' => 'ila',
                'type_label' => ClassRecordAssessment::TYPES['ila'],
                'category_code' => null,
                'is_graded' => false,
                'is_major' => false,
                'plotted_at' => null,
                'roster_count' => null,
                'submitted_count' => null,
                'compliance' => null,
                'time_label' => $scheduleSlot
                    ? $scheduleStart.'–'.$scheduleEnd
                    : null,
                'source' => 'ila_pending',
            ];
        });

        $items = $items->concat($ilaItems);

        // Exam windows configured for this school year (any quarter) — used only
        // to annotate the reviewer view with *why* a day/week may legitimately
        // exceed the normal caps. Enforcement itself is per-quarter, in
        // ClassRecordAssessmentController.
        $examWindows = QuarterExamWindow::where('school_year_id', $schoolYearId)->get(['start_date', 'end_date']);
        $isExamWindowDate = fn (string $date) => $examWindows->contains(
            fn ($w) => $date >= $w->start_date->toDateString() && $date <= $w->end_date->toDateString()
        );

        $days = collect(range(0, 4))->map(function ($offset) use ($monday, $items, $isExamWindowDate, $schoolYearId) {
            $date = $monday->copy()->addDays($offset)->toDateString();
            $dayRows = $items->where('date', $date)->values();
            $counts = self::trackerItemCounts($dayRows, $schoolYearId);
            $graded = $counts['graded'];
            $major = $counts['major'];

            return [
                'date' => $date,
                'items' => $dayRows,
                'graded_count' => $graded,
                'major_count' => $major,
                'over_daily' => $graded > self::DAILY_GRADED_MAX || $major > self::DAILY_MAJOR_MAX,
                'is_exam_window' => $isExamWindowDate($date),
            ];
        });

        $weekCounts = self::trackerItemCounts($items, $schoolYearId);
        $weekGraded = $weekCounts['graded'];
        $weekMajor = $weekCounts['major'];

        return [
            'week_start' => $monday->toDateString(),
            'week_end' => $friday->toDateString(),
            'days' => $days,
            'totals' => [
                'graded' => $weekGraded,
                'major' => $weekMajor,
                'over_weekly' => $weekGraded > self::WEEKLY_GRADED_MAX || $weekMajor > self::WEEKLY_MAJOR_MAX,
                'has_exam_window' => $days->contains('is_exam_window', true),
            ],
            'limits' => [
                'daily_graded' => self::DAILY_GRADED_MAX,
                'daily_major' => self::DAILY_MAJOR_MAX,
                'weekly_graded' => self::WEEKLY_GRADED_MAX,
                'weekly_major' => self::WEEKLY_MAJOR_MAX,
            ],
            'review' => WatReview::with('reviewedBy:id,name')
                ->where('section_id', $sectionId)
                ->where('school_year_id', $schoolYearId)
                ->where('week_start', $monday->toDateString())
                ->first(),
        ];
    }

    private static function trackerItemCounts(Collection $items, int $schoolYearId): array
    {
        return self::consolidatedCounts(
            $items->map(fn ($item) => [
                'assessment_key' => (string) ($item['assessment_id'] ?? $item['id']),
                'activity_date' => $item['date'],
                'section_id' => (int) ($item['_wat_section_id'] ?? 0),
                'subject_id' => (int) ($item['_wat_subject_id'] ?? 0),
                'subject_type' => $item['_wat_subject_type'] ?? null,
                'resolved_slot' => $item['_wat_block'] ?? null,
                'is_graded' => (bool) $item['is_graded'],
                'is_major' => (bool) $item['is_major'],
            ]),
            $schoolYearId
        );
    }

    // ── Teacher-level plotting compliance (CID/ACIDAA analytics) ─────────────

    /**
     * Per-teacher plotting compliance for one section's grade-week — built on
     * top of weekData()'s existing items (no separate query for what's
     * already plotted) plus the section's 'teaching' LoadAssignment roster,
     * so a teacher who plotted NOTHING this week still appears (they'd
     * otherwise be invisible — there's no assessment row to find them by).
     *
     * Visibility-only: this does not change any WAT rule. Non-plotters are
     * classified so CID can tell genuine non-compliance apart from a
     * teacher who simply had no room left in the section's shared cap:
     *
     *  - 'not_yet_due'   : the plotting deadline for this week hasn't passed
     *  - 'blocked_by_cap': deadline has passed, teacher plotted nothing, AND
     *                      the section's weekly graded/major budget was
     *                      already exhausted by (other) teachers before it did
     *  - 'not_plotted'   : deadline has passed, teacher plotted nothing, and
     *                      the section still had room — genuine non-compliance
     *  - 'plotted'       : teacher plotted at least one graded assessment
     *
     * @return array{
     *   week_start: string, week_end: string,
     *   remaining: array{weekly_graded:int, weekly_major:int},
     *   teachers: array<int, array>,
     * }
     */
    public static function teacherBreakdown(
        int $sectionId,
        int $schoolYearId,
        string $weekStart,
        ?array $preparedWeek = null
    ): array {
        $monday = Carbon::parse($weekStart)->startOfWeek(Carbon::MONDAY);
        $week = $preparedWeek ?? self::weekData($sectionId, $schoolYearId, $monday->toDateString());

        // Deadline for the week is judged off Monday (the week's own plotting
        // cutoff is "Friday before Monday, 12NN") — same anchor violatesPlottingDeadline()
        // uses when called per-item elsewhere.
        $deadlinePassed = now()->greaterThan(self::plottingDeadline($monday->toDateString()));

        $remainingGraded = max(0, self::WEEKLY_GRADED_MAX - $week['totals']['graded']);
        $remainingMajor = max(0, self::WEEKLY_MAJOR_MAX - $week['totals']['major']);
        $capExhausted = $remainingGraded <= 0 || $remainingMajor <= 0;

        // Roster: every teacher with a 'teaching' LoadAssignment on this
        // section this school year — this is what surfaces a zero-plot
        // teacher who has no item in $week['days'] to be found by at all.
        $roster = LoadAssignment::where('section_id', $sectionId)
            ->where('school_year_id', $schoolYearId)
            ->where('assignment_type', 'teaching')
            ->whereNotNull('user_id')
            ->with('subject:id,name', 'faculty:id,name')
            ->get()
            ->unique(fn ($a) => $a->user_id.'|'.$a->subject_id);

        $items = collect($week['days'])->flatMap(fn ($d) => $d['items']);

        $byTeacher = $roster->groupBy('user_id')->map(function ($assignments, $userId) use ($items, $deadlinePassed, $capExhausted) {
            $teacherName = $assignments->first()->faculty?->name;
            $subjects = $assignments->pluck('subject.name')->filter()->unique()->values();

            // Matched on teacher_name (resolved from the roster's faculty
            // relation) rather than subject — a co-taught synthetic Science
            // Core/Elective row pooled into this section's items carries the
            // ORIGINAL owning teacher's name from weekData(), same name this
            // roster entry resolves to, so it's correctly attributed even
            // though the row's section_id differs from $sectionId.
            $mine = $items->filter(function ($item) use ($teacherName) {
                return $item['teacher_name'] === $teacherName;
            })->values();

            $graded = $mine->where('is_graded', true);
            $major = $graded->where('is_major', true);

            $plottedCount = $graded->unique('assessment_id')->count();
            $lastPlottedAt = $mine->pluck('plotted_at')->filter()->sort()->last();

            $status = 'plotted';
            if ($plottedCount === 0) {
                $status = ! $deadlinePassed
                    ? 'not_yet_due'
                    : ($capExhausted ? 'blocked_by_cap' : 'not_plotted');
            }

            return [
                'user_id' => (int) $userId,
                'teacher_name' => $teacherName,
                'subjects' => $subjects->all(),
                'graded_count' => $graded->count(),
                'major_count' => $major->count(),
                'plotted' => $plottedCount > 0,
                'last_plotted_at' => $lastPlottedAt,
                'status' => $status,
            ];
        })->values()->sortBy('teacher_name')->values()->all();

        return [
            'week_start' => $week['week_start'],
            'week_end' => $week['week_end'],
            'deadline_passed' => $deadlinePassed,
            'remaining' => [
                'weekly_graded' => $remainingGraded,
                'weekly_major' => $remainingMajor,
            ],
            'teachers' => $byTeacher,
        ];
    }

    // ── Individual Faculty WAT Tracker (teacher → sections direction) ────────

    /**
     * One teacher's own plotted assessments across every class record they
     * own (as primary teacher_id, or as a PEHM co-teacher via
     * ClassRecordTeacher) this school year, for one Mon–Fri week — the
     * mirror image of teacherBreakdown() (which starts from one section and
     * breaks it down by teacher). This starts from one teacher and cuts
     * across every section/subject they teach.
     *
     * Built on top of weekData() per distinct section so the exact same
     * pooling (Science Core/Elective synthetic sections), exam-window
     * exemption, and cap arithmetic apply — no separate query logic that
     * could drift from the section-facing tracker.
     *
     * @return array{
     *   week_start: string, week_end: string,
     *   totals: array{graded:int, major:int},
     *   sections: array<int, array>,
     *   days: array<int, array>,
     * }
     */
    public static function facultyWeekData(int $userId, int $schoolYearId, string $weekStart): array
    {
        $monday = Carbon::parse($weekStart)->startOfWeek(Carbon::MONDAY);
        $friday = $monday->copy()->addDays(4);

        // Every class record this teacher owns this SY, either as the
        // primary teacher_id or as a PEHM co-teacher pivot row — mirrors
        // ClassRecord::teacherIdsFor()'s own fallback logic, just run in the
        // opposite direction (teacher -> records instead of record -> teachers).
        $ownRecordIds = ClassRecord::where('school_year_id', $schoolYearId)
            ->where('status', '<>', 'archived')
            ->where('teacher_id', $userId)
            ->pluck('id');
        $coTeachRecordIds = ClassRecordTeacher::where('user_id', $userId)
            ->whereHas('classRecord', fn ($q) => $q->where('school_year_id', $schoolYearId)->where('status', '<>', 'archived'))
            ->pluck('class_record_id');

        $records = ClassRecord::with('section:id,levelid,sectionname')
            ->whereIn('id', $ownRecordIds->concat($coTeachRecordIds)->unique())
            ->whereNotNull('section_id')
            ->get();

        $bySection = $records->groupBy('section_id');

        $sections = [];
        $dayBucket = collect(range(0, 4))->mapWithKeys(fn ($o) => [$monday->copy()->addDays($o)->toDateString() => collect()]);

        foreach ($bySection as $sectionId => $sectionRecords) {
            $first = $sectionRecords->first();
            $week = self::weekData((int) $sectionId, $schoolYearId, $monday->toDateString());

            // This teacher's own items only — matched by teacher_name the
            // same way teacherBreakdown() does, since a pooled Science
            // Core/Elective row keeps its ORIGINAL owning teacher's name
            // regardless of which section's items array it landed in.
            $teacherName = User::find($userId)?->name;
            $mine = collect($week['days'])->flatMap(fn ($d) => collect($d['items'])->map(fn ($i) => $i + ['date' => $d['date']]))
                ->filter(fn ($i) => $i['teacher_name'] === $teacherName)
                ->values();

            foreach ($mine as $item) {
                if ($dayBucket->has($item['date'])) {
                    $dayBucket[$item['date']]->push($item + [
                        'section_id' => (int) $sectionId,
                        'section_name' => $first->section?->sectionname,
                        'grade' => $first->section?->levelid,
                    ]);
                }
            }

            $gradedMine = $mine->where('is_graded', true)->unique('assessment_id')->count();
            $majorMine = $mine->where('is_graded', true)->where('is_major', true)->unique('assessment_id')->count();

            $sections[] = [
                'section_id' => (int) $sectionId,
                'section_name' => $first->section?->sectionname,
                'grade' => $first->section?->levelid,
                'subjects' => $sectionRecords->pluck('subject_name')->unique()->values()->all(),
                'graded_count' => $gradedMine,
                'major_count' => $majorMine,
                'remaining' => [
                    // Room left in the SECTION's shared budget (pools every
                    // teacher on it, not just this one) — matches what the
                    // section-scoped tracker/upsert endpoint actually enforces.
                    'weekly_graded' => max(0, self::WEEKLY_GRADED_MAX - $week['totals']['graded']),
                    'weekly_major' => max(0, self::WEEKLY_MAJOR_MAX - $week['totals']['major']),
                ],
                'section_totals' => [
                    'graded' => $week['totals']['graded'],
                    'major' => $week['totals']['major'],
                ],
            ];
        }

        $days = $dayBucket->map(function ($items, $date) {
            $sorted = $items->sortBy('section_name')->values();

            return [
                'date' => $date,
                'items' => $sorted,
                'graded_count' => $sorted->where('is_graded', true)->count(),
                'major_count' => $sorted->where('is_graded', true)->where('is_major', true)->count(),
            ];
        })->values();

        $weekItems = $days->flatMap(fn ($day) => $day['items']);
        $weekGraded = $weekItems->where('is_graded', true)->unique('assessment_id')->count();
        $weekMajor = $weekItems->where('is_graded', true)->where('is_major', true)->unique('assessment_id')->count();

        return [
            'week_start' => $monday->toDateString(),
            'week_end' => $friday->toDateString(),
            'totals' => ['graded' => $weekGraded, 'major' => $weekMajor],
            'sections' => collect($sections)->sortBy('section_name')->values()->all(),
            'days' => $days->all(),
        ];
    }
}
