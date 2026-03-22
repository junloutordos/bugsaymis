<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Http\Requests\HR\ManualDtrEditRequest;
use App\Jobs\HR\GenerateDTRRecords;
use App\Models\HR\DtrRecord;
use App\Models\User;
use App\Services\HR\DTRService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Inertia\Inertia;

class DtrRecordController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('hr.dtr.view');

        $query = DtrRecord::with(['user', 'schedule'])
            ->when($request->user_id, fn ($q) => $q->where('user_id', $request->user_id))
            ->when($request->month, function ($q) use ($request) {
                [$y, $m] = explode('-', $request->month . '-01');
                $q->whereYear('work_date', $y)->whereMonth('work_date', $m);
            })
            ->when($request->status, fn ($q) => $q->where('attendance_status', $request->status))
            ->orderByDesc('work_date')
            ->orderBy('user_id');

        return Inertia::render('HR/DTR/Index', [
            'records' => $query->paginate(50)->withQueryString(),
            'users'   => User::where('status', 'active')
                ->select('id', 'name', 'badge_id')
                ->orderBy('name')
                ->get(),
            'filters' => $request->only(['user_id', 'month', 'status']),
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

    public function generate(Request $request)
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

        $jobs = $users->map(
            fn ($u) => new GenerateDTRRecords($u->id, $data['date_from'], $data['date_to'])
        )->all();

        Bus::batch($jobs)
            ->name('DTR Generation ' . $data['date_from'] . ' to ' . $data['date_to'])
            ->dispatch();

        return back()->with('success', 'DTR generation queued for ' . count($jobs) . ' employee(s).');
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

    public function lock(DtrRecord $record)
    {
        $this->authorize('hr.dtr.manage');

        $record->update(['is_locked' => ! $record->is_locked]);

        return back()->with('success', $record->is_locked ? 'Record locked.' : 'Record unlocked.');
    }
}
