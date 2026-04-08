<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Http\Requests\HR\ManualDtrEditRequest;
use App\Models\HR\DtrRecord;
use App\Models\HR\LeaveApplication;
use App\Models\User;
use App\Models\WFHAttendance;
use App\Services\HR\DTRService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DtrRecordController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('hr.dtr.view');

        $month = $request->input('month', now()->format('Y-m'));
        [$y, $m] = explode('-', $month);

        $summaries = DtrRecord::query()
            ->selectRaw('user_id,
                SUM(CASE WHEN attendance_status = "present"  THEN 1 ELSE 0 END) as present_count,
                SUM(CASE WHEN attendance_status = "absent"   THEN 1 ELSE 0 END) as absent_count,
                SUM(CASE WHEN attendance_status = "half_day" THEN 1 ELSE 0 END) as half_day_count,
                SUM(CASE WHEN attendance_status = "on_leave" THEN 1 ELSE 0 END) as on_leave_count,
                SUM(CASE WHEN attendance_status = "holiday"  THEN 1 ELSE 0 END) as holiday_count,
                SUM(CASE WHEN attendance_status = "wfh"      THEN 1 ELSE 0 END) as wfh_count,
                SUM(CASE WHEN late_minutes > 0              THEN 1 ELSE 0 END) as late_days,
                ROUND(SUM(hours_worked), 2)        as total_hours,
                ROUND(SUM(late_minutes), 0)        as total_late,
                ROUND(SUM(undertime_minutes), 0)   as total_ut,
                ROUND(SUM(overtime_minutes), 0)    as total_ot
            ')
            ->whereYear('work_date', $y)
            ->whereMonth('work_date', $m)
            ->when($request->user_id, fn ($q) => $q->where('user_id', $request->user_id))
            ->groupBy('user_id')
            ->get();

        $userMap = User::whereIn('id', $summaries->pluck('user_id'))
            ->select('id', 'name', 'badge_id', 'emp_category')
            ->get()
            ->keyBy('id');

        $rows = $summaries
            ->map(fn ($s) => array_merge($s->toArray(), ['user' => $userMap->get($s->user_id)]))
            ->sortBy('user.name')
            ->values();

        return Inertia::render('HR/DTR/Index', [
            'summaries' => $rows,
            'users'     => User::where('status', 'active')
                ->select('id', 'name', 'badge_id')
                ->orderBy('name')
                ->get(),
            'filters'   => $request->only(['user_id', 'month']),
            'month'     => $month,
        ]);
    }

    public function show(Request $request, User $user)
    {
        $this->authorize('hr.dtr.view');

        $month = $request->input('month', now()->format('Y-m'));
        [$y, $m] = explode('-', $month);

        $records = DtrRecord::where('user_id', $user->id)
            ->whereYear('work_date', $y)
            ->whereMonth('work_date', $m)
            ->with('schedule', 'leaveApplication')
            ->orderBy('work_date')
            ->get()
            ->map(function ($record) {
                $dateStr  = \Carbon\Carbon::parse($record->work_date)->toDateString();
                $schedule = $record->schedule;
                $record->scheduled_time_in  = $schedule?->getTimeIn($dateStr);
                $record->scheduled_time_out = $schedule?->getTimeOut($dateStr);
                $record->grace_minutes      = $schedule?->grace_period_minutes;
                $record->schedule_name      = $schedule?->name;
                return $record;
            });

        $summary = [
            'present'     => $records->where('attendance_status', 'present')->count(),
            'absent'      => $records->where('attendance_status', 'absent')->count(),
            'half_day'    => $records->where('attendance_status', 'half_day')->count(),
            'on_leave'    => $records->where('attendance_status', 'on_leave')->count(),
            'holiday'     => $records->where('attendance_status', 'holiday')->count(),
            'wfh'         => $records->where('attendance_status', 'wfh')->count(),
            'late_days'   => $records->where('late_minutes', '>', 0)->count(),
            'total_hours' => round($records->sum('hours_worked'), 2),
            'total_late'  => round($records->sum('late_minutes'), 2),
            'total_ut'    => round($records->sum('undertime_minutes'), 2),
            'total_ot'    => round($records->sum('overtime_minutes'), 2),
        ];

        return Inertia::render('HR/DTR/Show', [
            'employee' => $user->load('employeeProfile'),
            'records'  => $records,
            'summary'  => $summary,
            'month'    => $month,
        ]);
    }

    // ── Employee self-service ──────────────────────────────────────────────────

    public function myDtr(Request $request)
    {
        $this->authorize('dtr.view_own');

        $user  = Auth::user();
        $month = $request->input('month', now()->format('Y-m'));
        [$y, $m] = explode('-', $month);

        $records = DtrRecord::where('user_id', $user->id)
            ->whereYear('work_date', $y)
            ->whereMonth('work_date', $m)
            ->with('schedule', 'leaveApplication')
            ->orderBy('work_date')
            ->get()
            ->map(function ($record) {
                $dateStr = \Carbon\Carbon::parse($record->work_date)->toDateString();
                $schedule = $record->schedule;
                $record->scheduled_time_in  = $schedule?->getTimeIn($dateStr);
                $record->scheduled_time_out = $schedule?->getTimeOut($dateStr);
                $record->grace_minutes      = $schedule?->grace_period_minutes;
                $record->schedule_name      = $schedule?->name;
                return $record;
            });

        $summary = [
            'present'     => $records->where('attendance_status', 'present')->count(),
            'absent'      => $records->where('attendance_status', 'absent')->count(),
            'half_day'    => $records->where('attendance_status', 'half_day')->count(),
            'on_leave'    => $records->where('attendance_status', 'on_leave')->count(),
            'holiday'     => $records->where('attendance_status', 'holiday')->count(),
            'wfh'         => $records->where('attendance_status', 'wfh')->count(),
            'late_days'   => $records->where('late_minutes', '>', 0)->count(),
            'total_hours' => round($records->sum('hours_worked'), 2),
            'total_late'  => round($records->sum('late_minutes'), 2),
            'total_ut'    => round($records->sum('undertime_minutes'), 2),
            'total_ot'    => round($records->sum('overtime_minutes'), 2),
        ];

        $wfhByDate      = $this->loadWfhByDate($user->id, $y, $m);
        $gatepassByDate = $this->loadGatepassByDate($user->badge_id, $y, $m);

        // Determine whether the employee can submit penned entries this month
        $hasPenned    = $records->contains(fn ($r) =>
            $r->penned_time_in_am || $r->penned_time_out_am ||
            $r->penned_time_in_pm || $r->penned_time_out_pm || $r->penned_remarks
        );
        $allSubmitted = $records->isNotEmpty() && $records->every(fn ($r) => $r->penned_submitted_at !== null);

        return Inertia::render('HR/DTR/My', [
            'employee'       => $user->load('employeeProfile'),
            'records'        => $records,
            'summary'        => $summary,
            'month'          => $month,
            'wfhByDate'      => $wfhByDate,
            'gatepassByDate' => $gatepassByDate,
            'hasPenned'      => $hasPenned,
            'allSubmitted'   => $allSubmitted,
        ]);
    }

    public function myDtrChecklist(Request $request)
    {
        $this->authorize('dtr.view_own');

        $user  = Auth::user();
        $month = $request->input('month', now()->format('Y-m'));
        [$y, $m] = explode('-', $month);
        $firstDay = "$y-" . str_pad($m, 2, '0', STR_PAD_LEFT) . "-01";

        $records = DtrRecord::where('user_id', $user->id)
            ->whereYear('work_date', $y)
            ->whereMonth('work_date', $m)
            ->with(['schedule', 'leaveApplication.leaveType'])
            ->orderBy('work_date')
            ->get();

        $schedule = \App\Models\HR\EmployeeSchedule::where('user_id', $user->id)
            ->activeOnDate($firstDay)
            ->orderByDesc('effective_date')
            ->first()
            ?? \App\Models\HR\EmployeeSchedule::where('user_id', $user->id)
                ->where('is_default', true)
                ->first();

        $officialTimes = [];
        foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri'] as $day) {
            if (! $schedule) continue;
            $ds = $schedule->daily_schedules ?? [];
            if (isset($ds[$day])) {
                $officialTimes[$day] = [
                    'time_in'  => $ds[$day]['time_in']  ?? null,
                    'time_out' => $ds[$day]['time_out'] ?? null,
                ];
            } elseif (in_array($day, $schedule->getWorkDaysArray())) {
                $officialTimes[$day] = [
                    'time_in'  => $schedule->time_in  ? (string) $schedule->time_in  : null,
                    'time_out' => $schedule->time_out ? (string) $schedule->time_out : null,
                ];
            }
        }

        $pennedEntries = $records->filter(fn ($r) =>
            $r->penned_remarks ||
            $r->penned_time_in_am || $r->penned_time_out_am ||
            $r->penned_time_in_pm || $r->penned_time_out_pm
        )->map(function ($r) {
            $time = $r->penned_time_in_am ?? $r->penned_time_out_am
                 ?? $r->penned_time_in_pm ?? $r->penned_time_out_pm;
            return [
                'date'   => \Carbon\Carbon::parse($r->work_date)->format('m/d/Y'),
                'time'   => $time,
                'reason' => $r->penned_remarks ?? '',
            ];
        })->values();

        $declaredRows = $records->filter(fn ($r) =>
            $r->late_minutes > 0 || $r->undertime_minutes > 0 ||
            $r->attendance_status === 'absent'
        )->map(function ($r) {
            if ($r->attendance_status === 'absent') {
                return ['nature' => 'A', 'date' => \Carbon\Carbon::parse($r->work_date)->format('m/d/Y'), 'minutes' => null, 'reason' => ''];
            }
            return ['nature' => 'T', 'date' => \Carbon\Carbon::parse($r->work_date)->format('m/d/Y'), 'minutes' => (int) round((float) $r->late_minutes + (float) $r->undertime_minutes), 'reason' => ''];
        })->values();

        [$wfhEntries, $gatepassEntries, $leaveEntries] = $this->buildChecklistEntries(
            $user->id, $user->badge_id, $records, $y, $m
        );

        return Inertia::render('HR/DTR/PrintChecklist', [
            'employee'       => $user->load('employeeProfile'),
            'month'          => $month,
            'officialTimes'  => $officialTimes,
            'pennedEntries'  => $pennedEntries,
            'declaredRows'   => $declaredRows,
            'wfhEntries'     => $wfhEntries,
            'gatepassEntries'=> $gatepassEntries,
            'leaveEntries'   => $leaveEntries,
            'absentCount'    => $records->where('attendance_status', 'absent')->count(),
            'totalTardy'     => (int) round($records->sum(fn ($r) => (float) $r->late_minutes + (float) $r->undertime_minutes)),
        ]);
    }

    public function myPenned(Request $request, DtrRecord $record)
    {
        $this->authorize('dtr.view_own');

        // Employees can only submit penned entries for their own records
        abort_unless($record->user_id === Auth::id(), 403);

        if ($record->is_locked) {
            return back()->with('error', 'This DTR record is locked and cannot be edited.');
        }

        if ($record->penned_submitted_at) {
            return back()->with('error', 'Penned entries have already been submitted for this record. Contact HR to unlock.');
        }

        $validated = $request->validate([
            'penned_time_in_am'  => 'nullable|date_format:H:i',
            'penned_time_out_am' => 'nullable|date_format:H:i',
            'penned_time_in_pm'  => 'nullable|date_format:H:i',
            'penned_time_out_pm' => 'nullable|date_format:H:i',
            'penned_remarks'     => 'nullable|string|max:255',
        ]);

        $updates = ['penned_remarks' => $validated['penned_remarks'] ?? null];

        foreach (['time_in_am', 'time_out_am', 'time_in_pm', 'time_out_pm'] as $field) {
            $key = 'penned_' . $field;
            // Only allow penned entry where the biometric slot is empty
            if (! $record->$field) {
                $updates[$key] = isset($validated[$key]) && $validated[$key]
                    ? $validated[$key] . ':00'
                    : null;
            }
        }

        $record->update(array_merge($updates, [
            'penned_by' => Auth::id(),
            'penned_at' => now(),
        ]));

        app(DTRService::class)->recompute($record->fresh());

        return back()->with('success', 'Penned entry submitted successfully.');
    }

    /**
     * Employee submits all penned entries for the month.
     * Marks every record in the month as penned_submitted, blocking further
     * employee edits. HR / Administrator can reset via unlockPenned().
     */
    public function submitPenned(Request $request)
    {
        $this->authorize('dtr.view_own');

        $user  = Auth::user();
        $month = $request->input('month', now()->format('Y-m'));
        [$y, $m] = explode('-', $month);

        $updated = DtrRecord::where('user_id', $user->id)
            ->whereYear('work_date', $y)
            ->whereMonth('work_date', $m)
            ->where('is_locked', false)
            ->whereNull('penned_submitted_at')
            ->update([
                'penned_submitted_at' => now(),
                'penned_submitted_by' => $user->id,
            ]);

        return back()->with('success', "Penned entries submitted and locked for {$month}. HR has been notified.");
    }

    /**
     * HR / Administrator unlocks penned entries for an employee's month,
     * allowing them to re-submit corrections.
     */
    public function unlockPenned(Request $request, User $user)
    {
        $this->authorize('hr.dtr.manage');

        $month = $request->input('month', now()->format('Y-m'));
        [$y, $m] = explode('-', $month);

        DtrRecord::where('user_id', $user->id)
            ->whereYear('work_date', $y)
            ->whereMonth('work_date', $m)
            ->whereNotNull('penned_submitted_at')
            ->update([
                'penned_submitted_at' => null,
                'penned_submitted_by' => null,
            ]);

        return back()->with('success', "Penned entries unlocked for {$user->name} — {$month}.");
    }

    public function generate(Request $request, DTRService $dtrService)
    {
        $this->authorize('hr.dtr.manage');

        $data = $request->validate([
            'user_id'   => 'nullable|exists:users,id',
            'category'  => 'nullable|in:all,plantilla,non_plantilla',
            'date_from' => 'required|date',
            'date_to'   => 'required|date|after_or_equal:date_from',
        ]);

        $query = User::where('status', 'active');

        if (!empty($data['user_id'])) {
            $query->where('id', $data['user_id']);
        } elseif (($data['category'] ?? 'all') === 'plantilla') {
            $query->whereIn('emp_category', ['Plantilla Teaching', 'Plantilla Non-Teaching']);
        } elseif (($data['category'] ?? 'all') === 'non_plantilla') {
            $query->whereIn('emp_category', ['COS Teaching', 'COS Non Teaching']);
        }

        $users = $query->get();

        foreach ($users as $user) {
            $dtrService->generate($user->id, $data['date_from'], $data['date_to']);
        }

        return back()->with('success', 'DTR generated for ' . $users->count() . ' employee(s).');
    }

    public function printBatch(Request $request)
    {
        $this->authorize('hr.dtr.view');

        // Support explicit date range (non-plantilla) OR month-based (plantilla/all)
        if ($request->filled('date_from') && $request->filled('date_to')) {
            $dateFrom = $request->input('date_from');
            $dateTo   = $request->input('date_to');
        } else {
            $month    = $request->input('month', now()->format('Y-m'));
            [$y, $m]  = explode('-', $month);
            $dateFrom = "$y-" . str_pad($m, 2, '0', STR_PAD_LEFT) . "-01";
            $dateTo   = \Carbon\Carbon::createFromDate($y, $m, 1)->endOfMonth()->format('Y-m-d');
        }

        // Load holidays for the entire period keyed by date string
        $holidays = \App\Models\HR\Holiday::where('is_active', true)
            ->whereBetween('holiday_date', [$dateFrom, $dateTo])
            ->get()
            ->keyBy(fn ($h) => \Carbon\Carbon::parse($h->holiday_date)->format('Y-m-d'));

        // Resolve which users to print
        $usersQuery = User::where('status', 'active')->with('employeeProfile');
        if ($request->user_id) {
            $usersQuery->where('id', $request->user_id);
        } elseif ($request->category) {
            $categoryMap = [
                'Plantilla Teaching'     => ['Plantilla Teaching'],
                'Plantilla Non-Teaching' => ['Plantilla Non-Teaching'],
                'COS Teaching'           => ['COS Teaching'],
                'COS Non Teaching'       => ['COS Non Teaching'],
                'plantilla'              => ['Plantilla Teaching', 'Plantilla Non-Teaching'],
                'non_plantilla'          => ['COS Teaching', 'COS Non Teaching'],
            ];
            if (isset($categoryMap[$request->category])) {
                $usersQuery->whereIn('emp_category', $categoryMap[$request->category]);
            }
        }
        $users = $usersQuery->orderBy('name')->get();

        // For each user, load their DTR records for the period
        $employees = $users->map(function ($user) use ($dateFrom, $dateTo, $holidays) {
            $records = DtrRecord::where('user_id', $user->id)
                ->whereBetween('work_date', [$dateFrom, $dateTo])
                ->with(['schedule', 'leaveApplication.leaveType'])
                ->orderBy('work_date')
                ->get()
                ->keyBy(fn ($r) => \Carbon\Carbon::parse($r->work_date)->format('Y-m-d'));

            foreach ($records as $date => $rec) {
                if (isset($holidays[$date])) {
                    $rec->holiday_name = $holidays[$date]->name;
                }
            }

            $totalLate   = $records->sum('late_minutes');
            $totalUt     = $records->sum('undertime_minutes');
            $totalTardy  = $totalLate + $totalUt;

            return [
                'user'                => $user,
                'records'             => $records,
                'total_tardy_hours'   => (int) floor($totalTardy / 60),
                'total_tardy_minutes' => (int) ($totalTardy % 60),
            ];
        })->values();

        // Resolve supervisor: OCD head
        $supervisor = null;
        $officeDivision = \Illuminate\Support\Facades\DB::table('divisions')
            ->where('division_name', 'Office of the Campus Director')
            ->first();
        if ($officeDivision?->division_chief_id) {
            $d = User::find($officeDivision->division_chief_id);
            if ($d) {
                $supervisor = ['name' => $d->name, 'position' => $d->position ?? 'OIC - Campus Director'];
            }
        }

        return Inertia::render('HR/DTR/PrintBatch', [
            'employees'  => $employees,
            'date_from'  => $dateFrom,
            'date_to'    => $dateTo,
            'supervisor' => $supervisor,
            'holidays'   => $holidays->map(fn ($h) => ['name' => $h->name, 'type' => $h->type]),
        ]);
    }

    public function printCsc(Request $request, User $user)
    {
        $this->authorize('hr.dtr.view');

        $month = $request->input('month', now()->format('Y-m'));
        [$y, $m] = explode('-', $month);

        $holidays = \App\Models\HR\Holiday::where('is_active', true)
            ->whereYear('holiday_date', $y)
            ->whereMonth('holiday_date', $m)
            ->get()
            ->keyBy(fn ($h) => \Carbon\Carbon::parse($h->holiday_date)->format('Y-m-d'));

        $records = DtrRecord::where('user_id', $user->id)
            ->whereYear('work_date', $y)
            ->whereMonth('work_date', $m)
            ->with(['schedule', 'leaveApplication.leaveType'])
            ->orderBy('work_date')
            ->get()
            ->keyBy(fn ($r) => \Carbon\Carbon::parse($r->work_date)->format('Y-m-d'));

        // Attach holiday name to records
        foreach ($records as $date => $rec) {
            if (isset($holidays[$date])) {
                $rec->holiday_name = $holidays[$date]->name;
            }
        }

        // Supervisor: division chief, fallback to OCD head
        $supervisor = null;
        if ($user->division_id) {
            $division = \Illuminate\Support\Facades\DB::table('divisions')->where('id', $user->division_id)->first();
            if ($division?->division_chief_id) {
                $chief = User::find($division->division_chief_id);
                if ($chief) $supervisor = ['name' => $chief->name, 'position' => $chief->position ?? 'Division Chief'];
            }
        }
        if (!$supervisor) {
            $ocdDiv = \Illuminate\Support\Facades\DB::table('divisions')->where('division_name', 'Office of the Campus Director')->first();
            if ($ocdDiv?->division_chief_id) {
                $d = User::find($ocdDiv->division_chief_id);
                if ($d) $supervisor = ['name' => $d->name, 'position' => $d->position ?? 'OIC - Campus Director'];
            }
        }

        $totalLate  = $records->sum('late_minutes');
        $totalUt    = $records->sum('undertime_minutes');
        $totalTardy = $totalLate + $totalUt;

        return Inertia::render('HR/DTR/PrintCsc', [
            'employee'          => $user->load('employeeProfile'),
            'records'           => $records,
            'month'             => $month,
            'holidays'          => $holidays->map(fn ($h) => ['name' => $h->name, 'type' => $h->type]),
            'supervisor'        => $supervisor,
            'totalTardyHours'   => (int) floor($totalTardy / 60),
            'totalTardyMinutes' => (int) ($totalTardy % 60),
        ]);
    }

    public function printChecklist(Request $request, User $user)
    {
        $this->authorize('hr.dtr.view');

        $month = $request->input('month', now()->format('Y-m'));
        [$y, $m] = explode('-', $month);
        $firstDay = "$y-" . str_pad($m, 2, '0', STR_PAD_LEFT) . "-01";

        $records = DtrRecord::where('user_id', $user->id)
            ->whereYear('work_date', $y)
            ->whereMonth('work_date', $m)
            ->with(['schedule', 'leaveApplication.leaveType'])
            ->orderBy('work_date')
            ->get();

        // Resolve schedule (same fallback logic as recompute)
        $schedule = \App\Models\HR\EmployeeSchedule::where('user_id', $user->id)
            ->activeOnDate($firstDay)
            ->orderByDesc('effective_date')
            ->first();
        if (! $schedule) {
            $schedule = \App\Models\HR\EmployeeSchedule::where('user_id', $user->id)
                ->where('is_default', true)
                ->first();
        }

        // Build official time rows for Mon–Fri
        $officialTimes = [];
        foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri'] as $day) {
            if (! $schedule) {
                continue;
            }
            $ds = $schedule->daily_schedules ?? [];
            if (isset($ds[$day])) {
                $officialTimes[$day] = [
                    'time_in'  => $ds[$day]['time_in']  ?? null,
                    'time_out' => $ds[$day]['time_out'] ?? null,
                ];
            } elseif (in_array($day, $schedule->getWorkDaysArray())) {
                $officialTimes[$day] = [
                    'time_in'  => $schedule->time_in  ? (string) $schedule->time_in  : null,
                    'time_out' => $schedule->time_out ? (string) $schedule->time_out : null,
                ];
            }
        }

        // Penned entries: any record with penned time or remarks
        $pennedEntries = $records->filter(fn ($r) =>
            $r->penned_remarks ||
            $r->penned_time_in_am || $r->penned_time_out_am ||
            $r->penned_time_in_pm || $r->penned_time_out_pm
        )->map(function ($r) {
            $time = $r->penned_time_in_am ?? $r->penned_time_out_am
                 ?? $r->penned_time_in_pm ?? $r->penned_time_out_pm;
            return [
                'date'   => \Carbon\Carbon::parse($r->work_date)->format('m/d/Y'),
                'time'   => $time,
                'reason' => $r->penned_remarks ?? '',
            ];
        })->values();

        // Declared tardy/undertime and absences combined, ordered by date
        $declaredRows = $records->filter(fn ($r) =>
            $r->late_minutes > 0 || $r->undertime_minutes > 0 ||
            $r->attendance_status === 'absent'
        )->map(function ($r) {
            if ($r->attendance_status === 'absent') {
                return [
                    'nature'  => 'A',
                    'date'    => \Carbon\Carbon::parse($r->work_date)->format('m/d/Y'),
                    'minutes' => null,
                    'reason'  => '',
                ];
            }
            return [
                'nature'  => 'T',
                'date'    => \Carbon\Carbon::parse($r->work_date)->format('m/d/Y'),
                'minutes' => (int) round((float) $r->late_minutes + (float) $r->undertime_minutes),
                'reason'  => '',
            ];
        })->values();

        $absentCount = $records->where('attendance_status', 'absent')->count();
        $totalTardy  = (int) round($records->sum(
            fn ($r) => (float) $r->late_minutes + (float) $r->undertime_minutes
        ));

        [$wfhEntries, $gatepassEntries, $leaveEntries] = $this->buildChecklistEntries(
            $user->id, $user->badge_id, $records, $y, $m
        );

        return Inertia::render('HR/DTR/PrintChecklist', [
            'employee'        => $user->load('employeeProfile'),
            'month'           => $month,
            'officialTimes'   => $officialTimes,
            'pennedEntries'   => $pennedEntries,
            'declaredRows'    => $declaredRows,
            'wfhEntries'      => $wfhEntries,
            'gatepassEntries' => $gatepassEntries,
            'leaveEntries'    => $leaveEntries,
            'absentCount'     => $absentCount,
            'totalTardy'      => $totalTardy,
        ]);
    }

    public function edit(ManualDtrEditRequest $request, DtrRecord $record)
    {
        if ($record->is_locked) {
            return back()->with('error', 'This DTR record is locked and cannot be edited.');
        }

        $record->update(array_merge($request->validated(), [
            'processed_by' => Auth::id(),
            'processed_at' => now(),
        ]));

        app(DTRService::class)->recompute($record->fresh());

        return back()->with('success', 'DTR record updated and recomputed.');
    }

    public function penned(Request $request, DtrRecord $record)
    {
        $this->authorize('hr.dtr.manage');

        if ($record->is_locked) {
            return back()->with('error', 'This DTR record is locked.');
        }

        $validated = $request->validate([
            'penned_time_in_am'  => 'nullable|date_format:H:i',
            'penned_time_out_am' => 'nullable|date_format:H:i',
            'penned_time_in_pm'  => 'nullable|date_format:H:i',
            'penned_time_out_pm' => 'nullable|date_format:H:i',
            'penned_remarks'     => 'nullable|string|max:255',
        ]);

        $updates = ['penned_remarks' => $validated['penned_remarks'] ?? null];

        foreach (['time_in_am', 'time_out_am', 'time_in_pm', 'time_out_pm'] as $field) {
            $key = 'penned_' . $field;
            // Only allow penned entry where the biometric slot is empty
            if (! $record->$field) {
                $updates[$key] = isset($validated[$key]) && $validated[$key]
                    ? $validated[$key] . ':00'
                    : null;
            }
        }

        $record->update(array_merge($updates, [
            'penned_by' => Auth::id(),
            'penned_at' => now(),
        ]));

        app(DTRService::class)->recompute($record->fresh());

        return back()->with('success', 'Penned entry saved.');
    }

    /**
     * Recompute late/undertime/overtime for all unlocked records of a user
     * in the requested month, based on their currently active schedule.
     * Used when a schedule is assigned or changed after DTR generation.
     */
    public function recompute(Request $request, User $user, DTRService $dtrService)
    {
        $this->authorize('hr.dtr.manage');

        $month = $request->input('month', now()->format('Y-m'));
        [$y, $m] = explode('-', $month);
        $dateFrom = "$y-" . str_pad($m, 2, '0', STR_PAD_LEFT) . "-01";
        $dateTo   = \Carbon\Carbon::createFromDate($y, $m, 1)->endOfMonth()->format('Y-m-d');

        $count = $dtrService->recomputeForUser($user->id, $dateFrom, $dateTo);

        return back()->with('success', "Recomputed $count record(s) for {$user->name}.");
    }

    public function lock(DtrRecord $record)
    {
        $this->authorize('hr.dtr.manage');

        $record->update(['is_locked' => ! $record->is_locked]);

        return back()->with('success', $record->is_locked ? 'Record locked.' : 'Record unlocked.');
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    /**
     * Load WFH attendances for the user/month, keyed by date string.
     * Returns array of { time_in, time_out } per date.
     */
    private function loadWfhByDate(int $userId, int $year, int $month): array
    {
        return WFHAttendance::where('user_id', $userId)
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->get()
            ->mapWithKeys(fn ($w) => [
                Carbon::parse($w->date)->toDateString() => [
                    'time_in'  => $w->time_in  ? Carbon::parse($w->time_in)->format('H:i:s')  : null,
                    'time_out' => $w->time_out ? Carbon::parse($w->time_out)->format('H:i:s') : null,
                ],
            ])
            ->toArray();
    }

    /**
     * Load approved gate passes for the employee/month, keyed by date string.
     * Returns array of { label: 'OB'|'OT'|'UT', type: full string, purpose } per date.
     */
    private function loadGatepassByDate(?string $badgeId, int $year, int $month): array
    {
        if (! $badgeId) {
            return [];
        }

        $typeMap = [
            'official business' => 'OB',
            'official time'     => 'OT',
            'undertime'         => 'UT',
        ];

        $rows = DB::table('gatepass')
            ->whereRaw("CAST(badgeNumber AS CHAR) = ?", [(string) $badgeId])
            ->where('status', 'Approved')
            ->get();

        $result = [];
        foreach ($rows as $gp) {
            // gatepass_date is stored as a string; try common formats
            try {
                $parsed = Carbon::parse($gp->gatepass_date);
            } catch (\Throwable) {
                continue;
            }
            if ($parsed->year !== $year || $parsed->month !== $month) {
                continue;
            }
            $dateStr = $parsed->toDateString();
            $label   = $typeMap[strtolower(trim($gp->gatepass_type ?? ''))] ?? 'OB';
            $result[$dateStr] = [
                'label'   => $label,
                'type'    => $gp->gatepass_type ?? '',
                'purpose' => $gp->purpose ?? '',
            ];
        }

        return $result;
    }

    /**
     * Build the WFH, gate-pass, and leave entry arrays for the PrintChecklist view.
     */
    private function buildChecklistEntries(
        int $userId,
        ?string $badgeId,
        \Illuminate\Support\Collection $records,
        int $year,
        int $month
    ): array {
        // ── WFH entries ───────────────────────────────────────────────────────
        $wfhByDate = $this->loadWfhByDate($userId, $year, $month);
        $wfhEntries = [];
        foreach ($wfhByDate as $dateStr => $w) {
            $wfhEntries[] = [
                'date'     => Carbon::parse($dateStr)->format('m/d/Y'),
                'time_in'  => $w['time_in'],
                'time_out' => $w['time_out'],
            ];
        }

        // ── Gate pass entries ─────────────────────────────────────────────────
        $gpByDate = $this->loadGatepassByDate($badgeId, $year, $month);
        $gatepassEntries = [];
        foreach ($gpByDate as $dateStr => $gp) {
            $gatepassEntries[] = [
                'date'    => Carbon::parse($dateStr)->format('m/d/Y'),
                'label'   => $gp['label'],
                'type'    => $gp['type'],
                'purpose' => $gp['purpose'],
            ];
        }

        // ── Leave entries ─────────────────────────────────────────────────────
        $leaveEntries = $records->filter(fn ($r) => $r->attendance_status === 'on_leave')
            ->map(fn ($r) => [
                'date'       => Carbon::parse($r->work_date)->format('m/d/Y'),
                'leave_type' => $r->leaveApplication?->leaveType?->name ?? 'Leave',
                'code'       => $r->leaveApplication?->leaveType?->code ?? 'L',
            ])
            ->values()
            ->toArray();

        return [$wfhEntries, $gatepassEntries, $leaveEntries];
    }
}
