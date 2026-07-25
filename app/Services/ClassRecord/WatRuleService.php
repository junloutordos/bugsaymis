<?php

namespace App\Services\ClassRecord;

use App\Models\ClassRecord\ClassRecordAssessment;
use App\Models\ClassRecord\ClassRecordScore;
use App\Models\ClassRecord\ClassRecordStudent;
use App\Models\ClassRecord\GradingCategory;
use App\Models\ClassRecord\QuarterExamWindow;
use App\Models\ClassRecord\WatReview;
use App\Models\FacultyLoading\ClassSchedule;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Weekly Assessment Tracker (WAT) rules — single source of truth for the
 * campus assessment-plotting policy:
 *
 *  - Plotting deadline: no later than Friday preceding the week of implementation
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
        // Monday of the implementation week minus 3 days = preceding Friday
        return Carbon::parse($activityDate)->startOfWeek(Carbon::MONDAY)->subDays(3)->endOfDay();
    }

    public static function violatesPlottingDeadline(string $activityDate): bool
    {
        return now()->greaterThan(self::plottingDeadline($activityDate));
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

    // ── Section-wide graded/major counts (exclude IDs being replaced) ─────────

    public static function sectionCountsOnDate(int $sectionId, int $schoolYearId, string $date, array $excludeIds = []): array
    {
        return self::counts(
            ClassRecordAssessment::sectionScopeQuery($sectionId, $schoolYearId)
                ->where('class_record_assessments.activity_date', $date),
            $excludeIds
        );
    }

    public static function sectionCountsInWeek(int $sectionId, int $schoolYearId, string $weekStart, array $excludeIds = []): array
    {
        $monday = Carbon::parse($weekStart)->startOfWeek(Carbon::MONDAY);

        return self::counts(
            ClassRecordAssessment::sectionScopeQuery($sectionId, $schoolYearId)
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
     * Full WAT dataset for one section-week: per-day assessment rows with
     * compliance %, per-day and weekly graded/major tallies, limit flags,
     * and the ACIDAA review record if one exists.
     */
    public static function weekData(int $sectionId, int $schoolYearId, string $weekStart): array
    {
        $monday = Carbon::parse($weekStart)->startOfWeek(Carbon::MONDAY);
        $friday = $monday->copy()->addDays(4);

        $rows = ClassRecordAssessment::sectionScopeQuery($sectionId, $schoolYearId)
            ->join('grading_categories as gc', 'class_record_assessments.grading_category_id', '=', 'gc.id')
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
                'cr.teacher_id',
                'gc.name as category_name',
                'gc.code as category_code',
            ]);

        $teachers = User::whereIn('id', $rows->pluck('teacher_id')->filter()->unique())
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

        $items = $rows->map(function ($row) use ($teachers, $rosterCounts, $scoreCounts, $schedulesByKey) {
            $roster    = (int) ($rosterCounts[$row->class_record_quarter_id] ?? 0);
            $submitted = (int) ($scoreCounts[$row->id] ?? 0);
            $date      = $row->activity_date instanceof Carbon
                ? $row->activity_date
                : Carbon::parse((string) $row->activity_date);

            $scheduleKey = $row->section_id && $row->subject_id
                ? $row->section_id.'|'.$row->subject_id.'|'.$date->format('l')
                : null;
            $schedule    = $scheduleKey ? ($schedulesByKey[$scheduleKey] ?? null) : null;

            return [
                'id'              => $row->id,
                'date'            => $date->toDateString(),
                'title'           => $row->title,
                'subject_name'    => $row->subject_name,
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
}
