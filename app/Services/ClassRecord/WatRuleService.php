<?php

namespace App\Services\ClassRecord;

use App\Models\ClassRecord\ClassRecordAssessment;
use App\Models\ClassRecord\ClassRecordScore;
use App\Models\ClassRecord\ClassRecordStudent;
use App\Models\ClassRecord\GradingCategory;
use App\Models\ClassRecord\WatReview;
use App\Models\FacultyLoading\ClassSchedule;
use App\Models\User;
use Carbon\Carbon;

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

        $items = $rows->map(function ($row) use ($teachers, $rosterCounts, $scoreCounts) {
            $roster    = (int) ($rosterCounts[$row->class_record_quarter_id] ?? 0);
            $submitted = (int) ($scoreCounts[$row->id] ?? 0);

            return [
                'id'              => $row->id,
                'date'            => $row->activity_date instanceof Carbon
                    ? $row->activity_date->toDateString()
                    : (string) $row->activity_date,
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
            ];
        });

        $days = collect(range(0, 4))->map(function ($offset) use ($monday, $items) {
            $date    = $monday->copy()->addDays($offset)->toDateString();
            $dayRows = $items->where('date', $date)->values();
            $graded  = $dayRows->where('is_graded', true)->count();
            $major   = $dayRows->where('is_graded', true)->where('is_major', true)->count();

            return [
                'date'         => $date,
                'items'        => $dayRows,
                'graded_count' => $graded,
                'major_count'  => $major,
                'over_daily'   => $graded > self::DAILY_GRADED_MAX || $major > self::DAILY_MAJOR_MAX,
            ];
        });

        $weekGraded = $items->where('is_graded', true)->count();
        $weekMajor  = $items->where('is_graded', true)->where('is_major', true)->count();

        return [
            'week_start'   => $monday->toDateString(),
            'week_end'     => $friday->toDateString(),
            'days'         => $days,
            'totals'       => [
                'graded'      => $weekGraded,
                'major'       => $weekMajor,
                'over_weekly' => $weekGraded > self::WEEKLY_GRADED_MAX || $weekMajor > self::WEEKLY_MAJOR_MAX,
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
