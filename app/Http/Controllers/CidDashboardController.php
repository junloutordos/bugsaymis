<?php

namespace App\Http\Controllers;

use App\Models\CidSchedule;
use App\Models\ClassRecord\ClassRecord;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\FacultyLoading\TeacherTapLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
                'id'    => $s->id,
                'name'  => $s->name,
                'code'  => $s->code,
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
            'type'           => 'required|in:assessment,meeting,event,training,other',
            'scheduled_date' => 'required|date',
            'section_id'     => 'nullable|integer',
            'subject_id'     => 'nullable|integer',
            'start_time'     => 'nullable|date_format:H:i',
            'end_time'       => 'nullable|date_format:H:i|after_or_equal:start_time',
            'description'    => 'nullable|string|max:1000',
        ]);

        $schoolYear = SchoolYear::where('is_current', true)->firstOrFail();

        if ($data['type'] === 'assessment' && ! empty($data['section_id'])) {
            $count = CidSchedule::where('school_year_id', $schoolYear->id)
                ->where('section_id', $data['section_id'])
                ->where('scheduled_date', $data['scheduled_date'])
                ->where('type', 'assessment')
                ->count();

            if ($count >= 3) {
                return response()->json([
                    'message' => 'Maximum of 3 assessments per section per day reached.',
                ], 422);
            }
        }

        $schedule = CidSchedule::create([
            ...$data,
            'school_year_id' => $schoolYear->id,
            'created_by'     => Auth::id(),
        ]);

        return response()->json(['schedule' => $this->formatEvent($schedule->load(['section', 'subject']))], 201);
    }

    public function update(Request $request, CidSchedule $schedule)
    {
        $this->authorize('cid.dashboard');

        $data = $request->validate([
            'title'          => 'sometimes|required|string|max:255',
            'type'           => 'sometimes|required|in:assessment,meeting,event,training,other',
            'scheduled_date' => 'sometimes|required|date',
            'section_id'     => 'nullable|integer',
            'subject_id'     => 'nullable|integer',
            'start_time'     => 'nullable|date_format:H:i',
            'end_time'       => 'nullable|date_format:H:i|after_or_equal:start_time',
            'description'    => 'nullable|string|max:1000',
        ]);

        $type    = $data['type'] ?? $schedule->type;
        $secId   = array_key_exists('section_id', $data) ? $data['section_id'] : $schedule->section_id;
        $date    = $data['scheduled_date'] ?? $schedule->scheduled_date->toDateString();

        if ($type === 'assessment' && $secId) {
            $count = CidSchedule::where('school_year_id', $schedule->school_year_id)
                ->where('section_id', $secId)
                ->where('scheduled_date', $date)
                ->where('type', 'assessment')
                ->where('id', '!=', $schedule->id)
                ->count();

            if ($count >= 3) {
                return response()->json([
                    'message' => 'Maximum of 3 assessments per section per day reached.',
                ], 422);
            }
        }

        $schedule->update($data);

        return response()->json(['schedule' => $this->formatEvent($schedule->fresh(['section', 'subject']))]);
    }

    public function destroy(CidSchedule $schedule)
    {
        $this->authorize('cid.dashboard');
        $schedule->delete();
        return response()->json(['ok' => true]);
    }

    public function assessmentCount(Request $request)
    {
        $this->authorize('cid.dashboard');

        $request->validate([
            'section_id'     => 'required|integer',
            'scheduled_date' => 'required|date',
            'exclude_id'     => 'nullable|integer',
        ]);

        $schoolYear = SchoolYear::where('is_current', true)->firstOrFail();

        $query = CidSchedule::where('school_year_id', $schoolYear->id)
            ->where('section_id', $request->section_id)
            ->where('scheduled_date', $request->scheduled_date)
            ->where('type', 'assessment');

        if ($request->exclude_id) {
            $query->where('id', '!=', $request->exclude_id);
        }

        return response()->json(['count' => $query->count()]);
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    private function getEvents(?int $schoolYearId, Carbon $start, Carbon $end): array
    {
        if (! $schoolYearId) return [];

        return CidSchedule::with(['section', 'subject', 'creator'])
            ->where('school_year_id', $schoolYearId)
            ->whereBetween('scheduled_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('scheduled_date')
            ->orderBy('start_time')
            ->get()
            ->map(fn ($s) => $this->formatEvent($s))
            ->toArray();
    }

    private function formatEvent(CidSchedule $s): array
    {
        return [
            'id'             => $s->id,
            'title'          => $s->title,
            'type'           => $s->type,
            'scheduled_date' => $s->scheduled_date->toDateString(),
            'start_time'     => $s->start_time,
            'end_time'       => $s->end_time,
            'description'    => $s->description,
            'section_id'     => $s->section_id,
            'section_name'   => $s->section ? "Grade {$s->section->levelid} — {$s->section->sectionname}" : null,
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

        $assessmentsToday = CidSchedule::where('school_year_id', $schoolYearId)
            ->where('scheduled_date', $todayStr)
            ->where('type', 'assessment')
            ->count();

        // Sections that already have 3 assessments today
        $sectionsAtMax = CidSchedule::selectRaw('section_id, COUNT(*) as cnt')
            ->where('school_year_id', $schoolYearId)
            ->where('scheduled_date', $todayStr)
            ->where('type', 'assessment')
            ->whereNotNull('section_id')
            ->groupBy('section_id')
            ->havingRaw('cnt >= 3')
            ->count();

        $teachersPresentToday = TeacherTapLog::whereDate('tapped_at', $todayStr)
            ->distinct('user_id')
            ->count('user_id');

        $classRecordsPending = ClassRecord::where('school_year_id', $schoolYearId)
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

        // Assessment load by section this month
        $assessmentLoad = CidSchedule::selectRaw(
                'section_id, COUNT(*) as count'
            )
            ->with('section')
            ->where('school_year_id', $schoolYearId)
            ->whereBetween('scheduled_date', [$monthStart, $monthEnd])
            ->where('type', 'assessment')
            ->whereNotNull('section_id')
            ->groupBy('section_id')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($row) => [
                'section' => $row->section
                    ? "Gr.{$row->section->levelid} {$row->section->sectionname}"
                    : "Section #{$row->section_id}",
                'count'   => $row->count,
            ])
            ->toArray();

        // Teacher attendance rate Mon–Fri this week
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

        // Class record status breakdown (current SY)
        $checked   = ClassRecord::where('school_year_id', $schoolYearId)->whereNotNull('checked_at')->count();
        $unchecked = ClassRecord::where('school_year_id', $schoolYearId)->whereNull('checked_at')->count();

        return [
            'assessmentLoad'     => $assessmentLoad,
            'teacherAttendance'  => $weekDays,
            'classRecordStatus'  => [
                ['label' => 'Checked',   'count' => $checked],
                ['label' => 'Pending',   'count' => $unchecked],
            ],
        ];
    }
}
