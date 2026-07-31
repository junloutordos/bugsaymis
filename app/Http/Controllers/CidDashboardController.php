<?php

namespace App\Http\Controllers;

use App\Models\CidSchedule;
use App\Models\ClassRecord\ClassRecord;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\FacultyLoading\TeacherTapLog;
use App\Models\User;
use App\Services\ClassRecord\WatRuleService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class CidDashboardController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('cid.dashboard');

        $schoolYear = SchoolYear::where('is_current', true)->first();
        $today      = Carbon::today();
        $month      = $request->integer('month', $today->month);
        $year       = $request->integer('year', $today->year);

        $calendarStart = Carbon::create($year, $month, 1)->startOfMonth();
        $calendarEnd   = $calendarStart->copy()->endOfMonth();

        $calendarEvents = $this->getEvents($schoolYear?->id, $calendarStart, $calendarEnd);
        $todayEvents    = $this->getEvents($schoolYear?->id, $today, $today);
        $cards          = $this->getCards($schoolYear?->id, $today);
        $charts         = $this->getCharts($schoolYear?->id, $today);

        $sections = Section::where('is_active', true)
            ->where('syid', $schoolYear?->syid ?? $schoolYear?->id)
            ->orderBy('levelid')
            ->orderBy('sectionname')
            ->get(['id', 'sectionname', 'levelid'])
            ->map(fn ($s) => [
                'id'    => $s->id,
                'name'  => $s->sectionname,
                'label' => "Grade {$s->levelid} — {$s->sectionname}",
            ]);

        $subjects = Subject::orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn ($s) => [
                'id'   => $s->id,
                'name' => $s->name,
                'code' => $s->code,
            ]);

        return Inertia::render('CID/Dashboard', [
            'schoolYear'     => $schoolYear ? ['id' => $schoolYear->id, 'name' => $schoolYear->name] : null,
            'calendarEvents' => $calendarEvents,
            'todayEvents'    => $todayEvents,
            'cards'          => $cards,
            'charts'         => $charts,
            'sections'       => $sections,
            'subjects'       => $subjects,
            'currentMonth'   => $month,
            'currentYear'    => $year,
        ]);
    }

    public function events(Request $request)
    {
        $this->authorize('cid.dashboard');

        $schoolYear = SchoolYear::where('is_current', true)->first();
        $month      = $request->integer('month');
        $year       = $request->integer('year');

        $start  = Carbon::create($year, $month, 1)->startOfMonth();
        $end    = $start->copy()->endOfMonth();
        $events = $this->getEvents($schoolYear?->id, $start, $end);

        return response()->json($events);
    }

    public function store(Request $request)
    {
        $this->authorize('cid.dashboard');

        $data = $request->validate([
            'title'          => 'required|string|max:255',
            'type'           => 'required|in:meeting,event,training,other',
            'scheduled_date' => 'required|date',
            'section_id'     => 'nullable|integer',
            'subject_id'     => 'nullable|integer',
            'start_time'     => 'nullable|date_format:H:i',
            'end_time'       => 'nullable|date_format:H:i|after_or_equal:start_time',
            'description'    => 'nullable|string|max:1000',
        ]);

        $schoolYear = SchoolYear::where('is_current', true)->firstOrFail();

        $schedule = CidSchedule::create([
            ...$data,
            'school_year_id' => $schoolYear->id,
            'created_by'     => Auth::id(),
        ]);

        return response()->json(['schedule' => $this->formatCidEvent($schedule->load(['section', 'subject']))], 201);
    }

    public function update(Request $request, CidSchedule $schedule)
    {
        $this->authorize('cid.dashboard');

        $data = $request->validate([
            'title'          => 'sometimes|required|string|max:255',
            'type'           => 'sometimes|required|in:meeting,event,training,other',
            'scheduled_date' => 'sometimes|required|date',
            'section_id'     => 'nullable|integer',
            'subject_id'     => 'nullable|integer',
            'start_time'     => 'nullable|date_format:H:i',
            'end_time'       => 'nullable|date_format:H:i|after_or_equal:start_time',
            'description'    => 'nullable|string|max:1000',
        ]);

        $schedule->update($data);

        return response()->json(['schedule' => $this->formatCidEvent($schedule->fresh(['section', 'subject']))]);
    }

    public function destroy(CidSchedule $schedule)
    {
        $this->authorize('cid.dashboard');
        $schedule->delete();
        return response()->json(['ok' => true]);
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    private function getEvents(?int $schoolYearId, Carbon $start, Carbon $end): array
    {
        if (! $schoolYearId) return [];

        $cidEvents = CidSchedule::with(['section', 'subject', 'creator'])
            ->where('school_year_id', $schoolYearId)
            ->whereBetween('scheduled_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('scheduled_date')
            ->orderBy('start_time')
            ->get()
            ->map(fn ($s) => $this->formatCidEvent($s))
            ->toArray();

        $crEvents = $this->getClassRecordAssessments($schoolYearId, $start, $end);

        return collect(array_merge($cidEvents, $crEvents))
            ->sortBy('scheduled_date')
            ->values()
            ->toArray();
    }

    private function getClassRecordAssessments(?int $schoolYearId, Carbon $start, Carbon $end): array
    {
        if (! $schoolYearId) return [];

        $rows = WatRuleService::assessmentOccurrencesQuery($schoolYearId)
            ->select([
                'class_record_assessments.id',
                'class_record_assessments.title',
                'crad.id as assessment_date_id',
                'cr.section_id',
                'cr.subject_name',
                'cr.teacher_id',
                'gc.name as category_name',
                'gc.code as category_code',
            ])
            ->join('grading_categories as gc',      'class_record_assessments.grading_category_id',    '=', 'gc.id')
            ->whereRaw(
                WatRuleService::OCCURRENCE_DATE_SQL.' BETWEEN ? AND ?',
                [$start->toDateString(), $end->toDateString()]
            )
            ->selectRaw(WatRuleService::OCCURRENCE_DATE_SQL.' as activity_date')
            ->orderByRaw(WatRuleService::OCCURRENCE_DATE_SQL)
            ->get();

        $sectionIds = $rows->pluck('section_id')->filter()->unique()->values()->toArray();
        $sections   = Section::whereIn('id', $sectionIds)->get(['id', 'sectionname', 'levelid'])->keyBy('id');

        $teacherIds = $rows->pluck('teacher_id')->filter()->unique()->values()->toArray();
        $teachers   = User::whereIn('id', $teacherIds)->get(['id', 'name'])->keyBy('id');

        return $rows->map(fn ($row) => [
            'id'             => 'cr_'.$row->id.'_'.($row->assessment_date_id ?? 'legacy'),
            'title'          => "[{$row->category_code}] {$row->title}",
            'type'           => 'assessment',
            'source'         => 'class_record',
            'scheduled_date' => $row->activity_date instanceof Carbon
                ? $row->activity_date->toDateString()
                : (string) $row->activity_date,
            'start_time'     => null,
            'end_time'       => null,
            'description'    => $row->subject_name . ' — ' . $row->category_name,
            'section_id'     => $row->section_id,
            'section_name'   => isset($sections[$row->section_id])
                ? "Grade {$sections[$row->section_id]->levelid} — {$sections[$row->section_id]->sectionname}"
                : null,
            'subject_name'   => $row->subject_name,
            'created_by'     => $teachers[$row->teacher_id]?->name ?? null,
        ])->toArray();
    }

    private function formatCidEvent(CidSchedule $s): array
    {
        return [
            'id'             => $s->id,
            'title'          => $s->title,
            'type'           => $s->type,
            'source'         => 'cid',
            'scheduled_date' => $s->scheduled_date->toDateString(),
            'start_time'     => $s->start_time,
            'end_time'       => $s->end_time,
            'description'    => $s->description,
            'section_id'     => $s->section_id,
            'section_name'   => $s->section
                ? "Grade {$s->section->levelid} — {$s->section->sectionname}"
                : null,
            'subject_id'     => $s->subject_id,
            'subject_name'   => $s->subject?->name,
            'created_by'     => $s->creator?->name,
        ];
    }

    private function getCards(?int $schoolYearId, Carbon $today): array
    {
        if (! $schoolYearId) {
            return [
                'assessments_today'         => 0,
                'sections_at_max'           => 0,
                'teachers_present_today'    => 0,
                'class_records_pending'     => 0,
                'activities_this_week'      => 0,
            ];
        }

        $todayStr  = $today->toDateString();
        $weekStart = $today->copy()->startOfWeek()->toDateString();
        $weekEnd   = $today->copy()->endOfWeek()->toDateString();

        $assessmentsToday = WatRuleService::assessmentOccurrencesQuery($schoolYearId)
            ->whereRaw(WatRuleService::OCCURRENCE_DATE_SQL.' = ?', [$todayStr])
            ->distinct('class_record_assessments.id')
            ->count('class_record_assessments.id');

        $sectionsAtMax = WatRuleService::assessmentOccurrencesQuery($schoolYearId)
            ->select('cr.section_id', DB::raw('COUNT(DISTINCT class_record_assessments.id) as cnt'))
            ->whereRaw(WatRuleService::OCCURRENCE_DATE_SQL.' = ?', [$todayStr])
            ->whereNotNull('cr.section_id')
            ->groupBy('cr.section_id')
            ->havingRaw('cnt >= 3')
            ->get()
            ->count();

        $teachersPresentToday = TeacherTapLog::whereDate('tapped_at', $todayStr)
            ->distinct('user_id')
            ->count('user_id');

        $classRecordsPending = ClassRecord::where('school_year_id', $schoolYearId)
            ->active()
            ->whereNull('checked_at')
            ->count();

        $activitiesThisWeek = CidSchedule::where('school_year_id', $schoolYearId)
            ->whereBetween('scheduled_date', [$weekStart, $weekEnd])
            ->count();

        return [
            'assessments_today'      => $assessmentsToday,
            'sections_at_max'        => $sectionsAtMax,
            'teachers_present_today' => $teachersPresentToday,
            'class_records_pending'  => $classRecordsPending,
            'activities_this_week'   => $activitiesThisWeek,
        ];
    }

    private function getCharts(?int $schoolYearId, Carbon $today): array
    {
        if (! $schoolYearId) {
            return ['assessmentLoad' => [], 'teacherAttendance' => [], 'classRecordStatus' => []];
        }

        $monthStart = $today->copy()->startOfMonth()->toDateString();
        $monthEnd   = $today->copy()->endOfMonth()->toDateString();

        // Assessment load by section this month (from class record assessments)
        $loadRows = WatRuleService::assessmentOccurrencesQuery($schoolYearId)
            ->select('cr.section_id', DB::raw('COUNT(DISTINCT class_record_assessments.id) as count'))
            ->leftJoin('sections as s', 'cr.section_id', '=', 's.id')
            ->addSelect('s.sectionname', 's.levelid')
            ->whereRaw(
                WatRuleService::OCCURRENCE_DATE_SQL.' BETWEEN ? AND ?',
                [$monthStart, $monthEnd]
            )
            ->whereNotNull('cr.section_id')
            ->groupBy('cr.section_id', 's.sectionname', 's.levelid')
            ->orderByDesc('count')
            ->get();

        $assessmentLoad = $loadRows->map(fn ($row) => [
            'section' => $row->sectionname
                ? "Gr.{$row->levelid} {$row->sectionname}"
                : "Section #{$row->section_id}",
            'count'   => (int) $row->count,
        ])->toArray();

        // Teacher attendance Mon–Fri this week
        $weekStart = $today->copy()->startOfWeek();
        $weekDays  = [];
        for ($i = 0; $i < 5; $i++) {
            $day          = $weekStart->copy()->addDays($i);
            $dayStr       = $day->toDateString();
            $presentCount = TeacherTapLog::whereDate('tapped_at', $dayStr)
                ->distinct('user_id')
                ->count('user_id');
            $weekDays[]   = [
                'label'   => $day->format('D'),
                'date'    => $dayStr,
                'present' => $presentCount,
            ];
        }

        // Class record status breakdown
        $checked   = ClassRecord::where('school_year_id', $schoolYearId)->active()->whereNotNull('checked_at')->count();
        $unchecked = ClassRecord::where('school_year_id', $schoolYearId)->active()->whereNull('checked_at')->count();

        return [
            'assessmentLoad'    => $assessmentLoad,
            'teacherAttendance' => $weekDays,
            'classRecordStatus' => [
                ['label' => 'Checked', 'count' => $checked],
                ['label' => 'Pending', 'count' => $unchecked],
            ],
        ];
    }
}
