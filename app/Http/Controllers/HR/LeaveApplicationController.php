<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\LeaveApplication;
use App\Models\HR\LeaveCredit;
use App\Models\HR\LeaveType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class LeaveApplicationController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('hr.leave.view');

        $query = LeaveApplication::with(['user', 'leaveType'])
            ->orderByDesc('filed_at');

        // Regular employees only see their own
        if (! Auth::user()->hasAnyPermission(['hr.leave.approve', 'hr.employee.manage'])) {
            $query->where('user_id', Auth::id());
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return Inertia::render('HR/Leave/Index', [
            'applications' => $query->paginate(20)->withQueryString(),
            'leaveTypes'   => LeaveType::where('is_active', true)->orderBy('sort_order')->get(),
            'filters'      => $request->only('status'),
        ]);
    }

    public function create()
    {
        $this->authorize('hr.leave.file');

        return Inertia::render('HR/Leave/Create', [
            'leaveTypes' => LeaveType::where('is_active', true)->orderBy('sort_order')->get(),
            'credits'    => LeaveCredit::where('user_id', Auth::id())
                ->where('year', now()->year)
                ->with('leaveType')
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('hr.leave.file');

        $data = $request->validate([
            'leave_type_id'         => 'required|exists:leave_types,id',
            'date_from'             => 'required|date|after_or_equal:today',
            'date_to'               => 'required|date|after_or_equal:date_from',
            'leave_details'         => 'nullable|string',
            'leave_details_specify' => 'nullable|string|max:255',
            'reason'                => 'nullable|string|max:1000',
            'supporting_document'   => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $data['user_id']     = Auth::id();
        $data['days_applied'] = $this->computeWorkingDays($data['date_from'], $data['date_to']);
        $data['status']      = 'pending';

        if ($request->hasFile('supporting_document')) {
            $data['supporting_document'] = $request->file('supporting_document')
                ->store('hr/leave_documents', 'local');
        }

        LeaveApplication::create($data);

        return redirect()->route('hr.leave.index')->with('success', 'Leave application filed.');
    }

    public function show(LeaveApplication $leaveApplication)
    {
        $this->authorize('hr.leave.view');

        return Inertia::render('HR/Leave/Show', [
            'application' => $leaveApplication->load(['user', 'leaveType', 'divisionChief', 'approvedBy']),
        ]);
    }

    public function approve(Request $request, LeaveApplication $leaveApplication)
    {
        $this->authorize('hr.leave.approve');

        $data = $request->validate([
            'action'  => 'required|in:approved,rejected,forwarded',
            'remarks' => 'nullable|string|max:500',
            'stage'   => 'required|in:division_chief,hr',
        ]);

        if ($data['stage'] === 'division_chief') {
            $leaveApplication->update([
                'division_chief_id'      => Auth::id(),
                'division_chief_action'  => $data['action'],
                'division_chief_at'      => now(),
                'division_chief_remarks' => $data['remarks'],
                'status'                 => $data['action'] === 'forwarded' ? 'forwarded' : $data['action'],
            ]);
        } else {
            $leaveApplication->update([
                'approved_by'      => Auth::id(),
                'approval_action'  => $data['action'],
                'approved_at'      => now(),
                'approval_remarks' => $data['remarks'],
                'status'           => $data['action'],
            ]);
        }

        return back()->with('success', 'Action recorded.');
    }

    public function cancel(LeaveApplication $leaveApplication)
    {
        if ($leaveApplication->user_id !== Auth::id()) {
            abort(403);
        }

        if (! in_array($leaveApplication->status, ['pending', 'forwarded'])) {
            return back()->with('error', 'Only pending applications can be cancelled.');
        }

        $leaveApplication->update(['status' => 'cancelled']);

        return back()->with('success', 'Application cancelled.');
    }

    private function computeWorkingDays(string $from, string $to): float
    {
        $start   = \Carbon\Carbon::parse($from);
        $end     = \Carbon\Carbon::parse($to);
        $days    = 0;

        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            if ($d->isWeekend()) {
                continue;
            }
            // TODO Phase 2: exclude holidays from holidays table
            $days++;
        }

        return $days;
    }
}
