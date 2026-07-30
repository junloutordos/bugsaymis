<?php

namespace App\Services\ClassRecord;

use App\Models\ClassRecord\ClassRecordAssessment;
use App\Models\ClassRecord\ClassRecordIlaDate;
use App\Models\ClassRecord\ClassRecordScore;
use App\Models\ClassRecord\ClassRecordStudent;
use App\Models\ClassRecord\GradingCategory;
use App\Models\ClassRecord\QuarterExamWindow;
use App\Models\ClassRecord\WatReview;
use App\Models\FacultyLoading\ClassSchedule;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\Section;
use App\Models\User;
use Carbon\Carbon;
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
    public const DAILY_GRADED_MAX  = 3;
    public const DAILY_MAJOR_MAX   = 2;
    public const WEEKLY_GRADED_MAX = 15;
    public const WEEKLY_MAJOR_MAX  = 6;
    public const MAJOR_WEIGHT_SHARE = 0.10;

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
            'FA', 'QZ'       => 'formative',
            'AA', 'P1', 'P2' => 'alternative',
            'ILA'            => 'ila',
            'QE', 'SE', 'PE' => match ($assessmentNumber) {
                1       => 'long_test_1',
                2       => 'long_test_2',
                default => null,
            },
            default => null,
        };
    }

    // ── Major-assessment derivation ───────────────────────────────────────────

    public static function isMajor(?string $type, GradingCategory $category): bool
    {
        if (in_array($type, ['long_test_1', 'long_test_2'], true)) {
            return true;
        }

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
        $end   = Carbon::parse($activityDate)->endOfWeek(Carbon::SUNDAY);

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

    public static function gradeCountsOnDate(int $sectionId, int $grade, int $schoolYearId, string $date, array $excludeIds = []): array
    {
        $sectionIds = self::poolSectionIds($sectionId, $grade);

        return self::counts(
            ClassRecordAssessment::schoolYearScopeQuery($schoolYearId)
                ->whereIn('cr.section_id', $sectionIds)
                ->where('class_record_assessments.activity_date', $date),
            $excludeIds
        );
    }

    public static function gradeCountsInWeek(int $sectionId, int $grade, int $schoolYearId, string $weekStart, array $excludeIds = []): array
    {
        $sectionIds = self::poolSectionIds($sectionId, $grade);
        $monday     = Carbon::parse($weekStart)->startOfWeek(Carbon::MONDAY);

        return self::counts(
            ClassRecordAssessment::schoolYearScopeQuery($schoolYearId)
                ->whereIn('cr.section_id', $sectionIds)
                ->whereBetween('class_record_assessments.activity_date', [
                    $monday->toDateString(),
                    $monday->copy()->addDays(6)->toDateString(),
                ]),
            $excludeIds
        );
    }

    private static function counts($query, array $excludeIds): array
    {
        $row = $query
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
                            ->whereColumn('class_record_assessments.activity_date', '>=', 'qew.start_date')
                            ->whereColumn('class_record_assessments.activity_date', '<=', 'qew.end_date');
                    });
            })
            ->selectRaw('
                COALESCE(SUM(class_record_assessments.is_graded), 0)  as graded,
                COALESCE(SUM(class_record_assessments.is_major AND class_record_assessments.is_graded), 0) as major
            ')
            ->first();

        return ['graded' => (int) $row->graded, 'major' => (int) $row->major];
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

        $rows = ClassRecordAssessment::schoolYearScopeQuery($schoolYearId)
            ->whereIn('cr.section_id', $poolSectionIds)
            ->join('grading_categories as gc', 'class_record_assessments.grading_category_id', '=', 'gc.id')
            ->leftJoin('sections as sec', 'sec.id', '=', 'cr.section_id')
            ->whereBetween('class_record_assessments.activity_date', [
                $monday->toDateString(),
                $monday->copy()->addDays(6)->toDateString(),
            ])
            ->orderBy('class_record_assessments.activity_date')
            ->orderBy('cr.subject_name')
            ->get([
                'class_record_assessments.id',
                'class_record_assessments.class_record_quarter_id',
                'class_record_assessments.title',
                'class_record_assessments.assessment_type',
                'class_record_assessments.is_graded',
                'class_record_assessments.is_major',
                'class_record_assessments.activity_date',
                'class_record_assessments.plotted_at',
                'class_record_assessments.max_score',
                'cr.id as class_record_id',
                'cr.subject_id',
                'cr.section_id',
                'cr.subject_name',
                'cr.category_label',
                'cr.teacher_id',
                'gc.name as category_name',
                'gc.code as category_code',
                'sec.sectionname as row_section_name',
            ]);

        // Non-graded ILA dates (the default) are still surfaced in the WAT
        // grid — informational only, never counted toward the graded/major
        // caps below — so coordinators can see the ILA session happened even
        // when nobody elected to grade it. Graded ILA dates need no special
        // handling here: they're already a normal row in $rows above via
        // their linked class_record_assessments entry.
        $ilaDates = ClassRecordIlaDate::where('is_graded', false)
            ->whereBetween('date', [$monday->toDateString(), $monday->copy()->addDays(6)->toDateString()])
            ->whereHas('quarter.classRecord', function ($q) use ($poolSectionIds, $schoolYearId) {
                $q->whereIn('section_id', $poolSectionIds)->where('school_year_id', $schoolYearId);
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
        $quarterIds    = $rows->pluck('class_record_quarter_id')->unique()->values();
        $rosterCounts  = ClassRecordStudent::whereIn('class_record_quarter_id', $quarterIds)
            ->selectRaw('class_record_quarter_id, COUNT(*) as n')
            ->groupBy('class_record_quarter_id')
            ->pluck('n', 'class_record_quarter_id');
        $scoreCounts   = ClassRecordScore::whereIn('class_record_assessment_id', $rows->pluck('id'))
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
        $schedulePairs = $rows
            ->filter(fn ($row) => $row->section_id && $row->subject_id)
            ->map(fn ($row) => ['section_id' => (int) $row->section_id, 'subject_id' => (int) $row->subject_id])
            ->concat($ilaDates
                ->map(fn ($ilaDate) => $ilaDate->quarter->classRecord)
                ->filter(fn ($cr) => $cr && $cr->section_id && $cr->subject_id)
                ->map(fn ($cr) => ['section_id' => (int) $cr->section_id, 'subject_id' => (int) $cr->subject_id]))
            ->unique(fn ($pair) => $pair['section_id'].'|'.$pair['subject_id'])
            ->values();

        $schedulesByKey = [];
        if ($schedulePairs->isNotEmpty()) {
            $query = ClassSchedule::query()->occupying();
            $query->where(function ($outer) use ($schedulePairs) {
                foreach ($schedulePairs as $pair) {
                    $outer->orWhere(function ($inner) use ($pair) {
                        $inner->where('section_id', $pair['section_id'])
                            ->where('subject_id', $pair['subject_id']);
                    });
                }
            });

            foreach ($query->get(['section_id', 'subject_id', 'day_of_week', 'start_time', 'end_time']) as $schedule) {
                $key = $schedule->section_id.'|'.$schedule->subject_id.'|'.$schedule->day_of_week;
                $schedulesByKey[$key] ??= ['start_time' => $schedule->start_time, 'end_time' => $schedule->end_time];
            }
        }

        $items = $rows->map(function ($row) use ($teachers, $rosterCounts, $scoreCounts, $schedulesByKey, $sectionId) {
            $roster    = (int) ($rosterCounts[$row->class_record_quarter_id] ?? 0);
            $submitted = (int) ($scoreCounts[$row->id] ?? 0);
            $date      = $row->activity_date instanceof Carbon
                ? $row->activity_date
                : Carbon::parse((string) $row->activity_date);

            $scheduleKey = $row->section_id && $row->subject_id
                ? $row->section_id.'|'.$row->subject_id.'|'.$date->format('l')
                : null;
            $schedule    = $scheduleKey ? ($schedulesByKey[$scheduleKey] ?? null) : null;

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
                'id'              => $row->id,
                'date'            => $date->toDateString(),
                'title'           => $row->title,
                'subject_name'    => $pooledTag ? "{$subjectName} ({$pooledTag})" : $subjectName,
                'teacher_name'    => $teachers[$row->teacher_id]?->name,
                'assessment_type' => $row->assessment_type,
                'type_label'      => ClassRecordAssessment::TYPES[$row->assessment_type] ?? $row->category_name,
                'category_code'   => $row->category_code,
                'is_graded'       => (bool) $row->is_graded,
                'is_major'        => (bool) $row->is_major,
                'plotted_at'      => $row->plotted_at,
                'roster_count'    => $roster,
                'submitted_count' => $row->is_graded ? $submitted : null,
                'compliance'      => ($row->is_graded && $roster > 0)
                    ? round($submitted / $roster * 100, 1)
                    : null,
                'time_label'      => $schedule
                    ? substr((string) $schedule['start_time'], 0, 5).'–'.substr((string) $schedule['end_time'], 0, 5)
                    : null,
            ];
        });

        $ilaItems = $ilaDates->map(function ($ilaDate) use ($teachers, $sectionId, $schedulesByKey) {
            $classRecord = $ilaDate->quarter->classRecord;
            $pooledTag   = null;
            if ($classRecord && (int) $classRecord->section_id !== $sectionId) {
                $sectionName = (string) $classRecord->section?->sectionname;
                $pooledTag   = str_starts_with($sectionName, 'SCI-')
                    ? 'Science Core'
                    : (str_starts_with($sectionName, 'ELEC-') ? 'Elective' : null);
            }
            $subjectName = $classRecord?->category_label
                ? "{$classRecord->subject_name} — {$classRecord->category_label}"
                : $classRecord?->subject_name;

            $scheduleKey = $classRecord?->section_id && $classRecord?->subject_id
                ? $classRecord->section_id.'|'.$classRecord->subject_id.'|'.$ilaDate->date->format('l')
                : null;
            $schedule = $scheduleKey ? ($schedulesByKey[$scheduleKey] ?? null) : null;

            return [
                'id'              => 'ila-'.$ilaDate->id,
                'ila_date_id'     => $ilaDate->id,
                'class_record_id' => $classRecord?->id,
                'date'            => $ilaDate->date->toDateString(),
                'title'           => $ilaDate->title ?: 'Independent Learning Activity',
                'subject_name'    => $pooledTag ? "{$subjectName} ({$pooledTag})" : $subjectName,
                'teacher_name'    => $teachers[$classRecord?->teacher_id]?->name,
                'assessment_type' => 'ila',
                'type_label'      => ClassRecordAssessment::TYPES['ila'],
                'category_code'   => null,
                'is_graded'       => false,
                'is_major'        => false,
                'plotted_at'      => null,
                'roster_count'    => null,
                'submitted_count' => null,
                'compliance'      => null,
                'time_label'      => $schedule
                    ? substr((string) $schedule['start_time'], 0, 5).'–'.substr((string) $schedule['end_time'], 0, 5)
                    : null,
                'source'          => 'ila_pending',
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

        $days = collect(range(0, 4))->map(function ($offset) use ($monday, $items, $isExamWindowDate) {
            $date    = $monday->copy()->addDays($offset)->toDateString();
            $dayRows = $items->where('date', $date)->values();
            $graded  = $dayRows->where('is_graded', true)->count();
            $major   = $dayRows->where('is_graded', true)->where('is_major', true)->count();

            return [
                'date'            => $date,
                'items'           => $dayRows,
                'graded_count'    => $graded,
                'major_count'     => $major,
                'over_daily'      => $graded > self::DAILY_GRADED_MAX || $major > self::DAILY_MAJOR_MAX,
                'is_exam_window'  => $isExamWindowDate($date),
            ];
        });

        $weekGraded = $items->where('is_graded', true)->count();
        $weekMajor  = $items->where('is_graded', true)->where('is_major', true)->count();

        return [
            'week_start'   => $monday->toDateString(),
            'week_end'     => $friday->toDateString(),
            'days'         => $days,
            'totals'       => [
                'graded'           => $weekGraded,
                'major'            => $weekMajor,
                'over_weekly'      => $weekGraded > self::WEEKLY_GRADED_MAX || $weekMajor > self::WEEKLY_MAJOR_MAX,
                'has_exam_window'  => $days->contains('is_exam_window', true),
            ],
            'limits'       => [
                'daily_graded'  => self::DAILY_GRADED_MAX,
                'daily_major'   => self::DAILY_MAJOR_MAX,
                'weekly_graded' => self::WEEKLY_GRADED_MAX,
                'weekly_major'  => self::WEEKLY_MAJOR_MAX,
            ],
            'review'       => WatReview::with('reviewedBy:id,name')
                ->where('section_id', $sectionId)
                ->where('school_year_id', $schoolYearId)
                ->where('week_start', $monday->toDateString())
                ->first(),
        ];
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
    public static function teacherBreakdown(int $sectionId, int $schoolYearId, string $weekStart): array
    {
        $monday = Carbon::parse($weekStart)->startOfWeek(Carbon::MONDAY);
        $week   = self::weekData($sectionId, $schoolYearId, $monday->toDateString());

        // Deadline for the week is judged off Monday (the week's own plotting
        // cutoff is "Friday before Monday, 12NN") — same anchor violatesPlottingDeadline()
        // uses when called per-item elsewhere.
        $deadlinePassed = now()->greaterThan(self::plottingDeadline($monday->toDateString()));

        $remainingGraded = max(0, self::WEEKLY_GRADED_MAX - $week['totals']['graded']);
        $remainingMajor  = max(0, self::WEEKLY_MAJOR_MAX - $week['totals']['major']);
        $capExhausted    = $remainingGraded <= 0 || $remainingMajor <= 0;

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
            $subjects    = $assignments->pluck('subject.name')->filter()->unique()->values();

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
            $major  = $graded->where('is_major', true);

            $plottedCount = $graded->count();
            $lastPlottedAt = $mine->pluck('plotted_at')->filter()->sort()->last();

            $status = 'plotted';
            if ($plottedCount === 0) {
                $status = ! $deadlinePassed
                    ? 'not_yet_due'
                    : ($capExhausted ? 'blocked_by_cap' : 'not_plotted');
            }

            return [
                'user_id'         => (int) $userId,
                'teacher_name'    => $teacherName,
                'subjects'        => $subjects->all(),
                'graded_count'    => $graded->count(),
                'major_count'     => $major->count(),
                'plotted'         => $plottedCount > 0,
                'last_plotted_at' => $lastPlottedAt,
                'status'          => $status,
            ];
        })->values()->sortBy('teacher_name')->values()->all();

        return [
            'week_start'      => $week['week_start'],
            'week_end'        => $week['week_end'],
            'deadline_passed' => $deadlinePassed,
            'remaining'       => [
                'weekly_graded' => $remainingGraded,
                'weekly_major'  => $remainingMajor,
            ],
            'teachers'        => $byTeacher,
        ];
    }
}
