<?php

namespace App\Services;

use App\Models\ClassRecord\ClassRecord;
use App\Models\ClassRecord\ClassRecordAssessment;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\ClassSchedule;
use App\Models\FacultyLoading\FacultyLoad;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\TeacherTapLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Builds the CID-focused personal dashboard payload for teaching faculty.
 *
 * Every read is wrapped in rescue() so a missing table (e.g. dev DB drift) or a
 * query error degrades the whole panel to null rather than breaking the page —
 * non-teaching users always get null and see the standard dashboard unchanged.
 */
class FacultyDashboardService
{
    public function payload(User $user): ?array
    {
        return $this->rescue('faculty dashboard', function () use ($user) {
            $sy = SchoolYear::where('is_current', true)->first();
            if (! $sy) {
                return null;
            }

            if (! $this->isFaculty($user, $sy->id)) {
                return null;
            }

            $term  = AcademicTerm::where('school_year_id', $sy->id)->where('is_current', true)->first();
            $today = Carbon::today();

            return [
                'school_year'       => ['id' => $sy->id, 'name' => $sy->name],
                'term'              => $term?->name,
                'cards'             => $this->cards($user, $sy->id, $term?->id, $today),
                'loadDistribution'  => $this->loadDistribution($user, $sy->id, $term?->id),
                'assessmentByQuarter' => $this->assessmentByQuarter($user, $sy->id),
                'weeklyAttendance'  => $this->weeklyAttendance($user, $today),
                'todaySchedule'     => $this->todaySchedule($user, $sy->id, $today),
                'classRecords'      => $this->classRecords($user, $sy->id),
            ];
        }, null);
    }

    /** A user is treated as faculty when they teach in the current SY. */
    private function isFaculty(User $user, int $schoolYearId): bool
    {
        $hasLoad = LoadAssignment::where('user_id', $user->id)
            ->where('assignment_type', 'teaching')
            ->where('school_year_id', $schoolYearId)
            ->exists();

        if ($hasLoad) {
            return true;
        }

        return ClassRecord::where('teacher_id', $user->id)
            ->where('school_year_id', $schoolYearId)
            ->active()
            ->exists();
    }

    private function facultyLoad(User $user, int $schoolYearId, ?int $termId): ?FacultyLoad
    {
        return FacultyLoad::where('user_id', $user->id)
            ->where('school_year_id', $schoolYearId)
            ->when($termId, fn ($q) => $q->where('academic_term_id', $termId))
            ->latest('id')
            ->first();
    }

    private function cards(User $user, int $schoolYearId, ?int $termId, Carbon $today): array
    {
        $load      = $this->facultyLoad($user, $schoolYearId, $termId);
        $totalUnits = $load ? (float) $load->total_units : $this->assignedUnits($user, $schoolYearId, $termId);
        $threshold  = $load ? (float) $load->full_load_threshold : 0.0;

        $records       = ClassRecord::where('teacher_id', $user->id)->where('school_year_id', $schoolYearId)->active();
        $recordsTotal  = (clone $records)->count();
        $recordsChecked = (clone $records)->whereNotNull('checked_at')->count();

        $sectionsCount = ClassRecord::where('teacher_id', $user->id)
            ->where('school_year_id', $schoolYearId)
            ->active()
            ->whereNotNull('section_id')
            ->distinct()
            ->count('section_id');

        $weekStart = $today->copy()->startOfWeek()->toDateString();
        $weekEnd   = $today->copy()->endOfWeek()->toDateString();

        $assessmentsThisWeek = ClassRecordAssessment::schoolYearScopeQuery($schoolYearId)
            ->where('cr.teacher_id', $user->id)
            ->whereBetween('class_record_assessments.activity_date', [$weekStart, $weekEnd])
            ->count();

        $todayClasses = ClassSchedule::where('user_id', $user->id)
            ->where('school_year_id', $schoolYearId)
            ->where('day_of_week', $this->dayOfWeek($today))
            ->where('status', '<>', 'inactive')
            ->classes()
            ->count();

        return [
            'total_units'         => round($totalUnits, 2),
            'full_load_threshold' => round($threshold, 2),
            'is_overload'         => $threshold > 0 && $totalUnits > $threshold,
            'overload_units'      => $threshold > 0 ? round(max(0, $totalUnits - $threshold), 2) : 0,
            'sections_count'      => $sectionsCount,
            'records_total'       => $recordsTotal,
            'records_checked'     => $recordsChecked,
            'assessments_week'    => $assessmentsThisWeek,
            'classes_today'       => $todayClasses,
        ];
    }

    /** Units per assignment type — feeds the load-distribution donut. */
    private function loadDistribution(User $user, int $schoolYearId, ?int $termId): array
    {
        $rows = LoadAssignment::where('user_id', $user->id)
            ->where('school_year_id', $schoolYearId)
            ->when($termId, fn ($q) => $q->where('academic_term_id', $termId))
            ->select('assignment_type', DB::raw('SUM(load_units) as units'))
            ->groupBy('assignment_type')
            ->pluck('units', 'assignment_type');

        $labels = [
            'teaching'    => 'Teaching',
            'research'    => 'Research',
            'admin'       => 'Administrative',
            'cocurricular' => 'Co-curricular',
            'committee'   => 'Committee',
        ];

        $out = [];
        foreach ($labels as $key => $label) {
            $units = (float) ($rows[$key] ?? 0);
            if ($units > 0) {
                $out[] = ['label' => $label, 'units' => round($units, 2)];
            }
        }

        return $out;
    }

    private function assignedUnits(User $user, int $schoolYearId, ?int $termId): float
    {
        return (float) LoadAssignment::where('user_id', $user->id)
            ->where('school_year_id', $schoolYearId)
            ->when($termId, fn ($q) => $q->where('academic_term_id', $termId))
            ->sum('load_units');
    }

    /** Count of assessments the teacher logged per quarter (1–4). */
    private function assessmentByQuarter(User $user, int $schoolYearId): array
    {
        $rows = ClassRecordAssessment::select('crq.quarter', DB::raw('COUNT(*) as count'))
            ->join('class_record_quarters as crq', 'class_record_assessments.class_record_quarter_id', '=', 'crq.id')
            ->join('class_records as cr', 'crq.class_record_id', '=', 'cr.id')
            ->where('cr.school_year_id', $schoolYearId)
            ->where('cr.teacher_id', $user->id)
            ->groupBy('crq.quarter')
            ->pluck('count', 'quarter');

        $out = [];
        for ($q = 1; $q <= 4; $q++) {
            $out[] = ['label' => "Q{$q}", 'count' => (int) ($rows[$q] ?? 0)];
        }

        return $out;
    }

    /** Own NFC tap-ins Mon–Fri this week. */
    private function weeklyAttendance(User $user, Carbon $today): array
    {
        $weekStart = $today->copy()->startOfWeek();
        $out = [];

        for ($i = 0; $i < 5; $i++) {
            $day = $weekStart->copy()->addDays($i);
            $present = TeacherTapLog::where('user_id', $user->id)
                ->whereDate('tapped_at', $day->toDateString())
                ->exists();
            $out[] = [
                'label'   => $day->format('D'),
                'date'    => $day->toDateString(),
                'present' => $present,
                'is_past' => $day->lte($today),
            ];
        }

        return $out;
    }

    private function todaySchedule(User $user, int $schoolYearId, Carbon $today): array
    {
        return ClassSchedule::with(['subject:id,name,code', 'section:id,sectionname,levelid', 'classroom:id,name'])
            ->where('user_id', $user->id)
            ->where('school_year_id', $schoolYearId)
            ->where('day_of_week', $this->dayOfWeek($today))
            ->where('status', '<>', 'inactive')
            ->classes()
            ->orderBy('start_time')
            ->get()
            ->map(fn (ClassSchedule $s) => [
                'id'      => $s->id,
                'title'   => $s->subject?->name ?? $s->title ?? 'Class',
                'code'    => $s->subject?->code,
                'section' => $s->section ? "Gr.{$s->section->levelid} {$s->section->sectionname}" : null,
                'room'    => $s->classroom?->name,
                'start'   => $s->start_time ? substr($s->start_time, 0, 5) : null,
                'end'     => $s->end_time ? substr($s->end_time, 0, 5) : null,
            ])
            ->values()
            ->all();
    }

    /** Per class-record checked/pending state for the completion panel. */
    private function classRecords(User $user, int $schoolYearId): array
    {
        return ClassRecord::with('section:id,sectionname,levelid')
            ->where('teacher_id', $user->id)
            ->where('school_year_id', $schoolYearId)
            ->active()
            ->orderBy('section_id')
            ->orderBy('subject_name')
            ->get()
            ->map(fn (ClassRecord $r) => [
                'id'       => $r->id,
                'subject'  => $r->subject_name ?? 'Subject',
                'section'  => $r->section ? "Gr.{$r->section->levelid} {$r->section->sectionname}" : 'Unassigned',
                'checked'  => $r->checked_at !== null,
                'url'      => route('class-records.page.show', $r->id),
            ])
            ->values()
            ->all();
    }

    /** ClassSchedule stores day_of_week as a capitalized day name (e.g. "Monday"). */
    private function dayOfWeek(Carbon $date): string
    {
        return $date->format('l');
    }

    private function rescue(string $label, callable $callback, mixed $fallback): mixed
    {
        try {
            return $callback();
        } catch (\Throwable $e) {
            logger()->warning("Faculty dashboard {$label} error: ".$e->getMessage());
            return $fallback;
        }
    }
}
