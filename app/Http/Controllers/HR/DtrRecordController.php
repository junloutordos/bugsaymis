<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Http\Requests\HR\ManualDtrEditRequest;
use App\Models\HR\DtrRecord;
use App\Models\User;
use App\Services\HR\DTRService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
            ->select('id', 'name', 'badge_id')
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
            ->get();

        $summary = [
            'present'     => $records->where('attendance_status', 'present')->count(),
            'absent'      => $records->where('attendance_status', 'absent')->count(),
            'half_day'    => $records->where('attendance_status', 'half_day')->count(),
            'on_leave'    => $records->where('attendance_status', 'on_leave')->count(),
            'holiday'     => $records->where('attendance_status', 'holiday')->count(),
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

    public function generate(Request $request, DTRService $dtrService)
    {
        $this->authorize('hr.dtr.manage');

        $data = $request->validate([
            'user_id'   => 'nullable|exists:users,id',
            'date_from' => 'required|date',
            'date_to'   => 'required|date|after_or_equal:date_from',
        ]);

        $users = $data['user_id']
            ? User::where('id', $data['user_id'])->get()
            : User::where('status', 'active')->get();

        foreach ($users as $user) {
            $dtrService->generate($user->id, $data['date_from'], $data['date_to']);
        }

        return back()->with('success', 'DTR generated for ' . $users->count() . ' employee(s).');
    }

    public function printCsc(Request $request, User $user)
    {
        $this->authorize('hr.dtr.view');

        $month = $request->input('month', now()->format('Y-m'));
        [$y, $m] = explode('-', $month);

        $records = DtrRecord::where('user_id', $user->id)
            ->whereYear('work_date', $y)
            ->whereMonth('work_date', $m)
            ->with('schedule')
            ->orderBy('work_date')
            ->get()
            ->keyBy(fn ($r) => \Carbon\Carbon::parse($r->work_date)->format('Y-m-d'));

        return Inertia::render('HR/DTR/PrintCsc', [
            'employee' => $user->load('employeeProfile'),
            'records'  => $records,
            'month'    => $month,
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

    public function lock(DtrRecord $record)
    {
        $this->authorize('hr.dtr.manage');

        $record->update(['is_locked' => ! $record->is_locked]);

        return back()->with('success', $record->is_locked ? 'Record locked.' : 'Record unlocked.');
    }
}
