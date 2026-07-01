<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\LeaveApplication;
use App\Models\HR\LeaveType;
use App\Models\User;
use App\Services\HR\DTRService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class LegacyLeaveImportController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('hr.leave.credits.manage');

        $employees = User::whereNotNull('emp_category')
            ->where('status', '<>', 'inactive')
            ->select('id', 'name', 'badge_id', 'position', 'emp_category')
            ->orderBy('name')
            ->when($request->filled('search'), function ($q) use ($request) {
                $q->where(function ($q2) use ($request) {
                    $q2->where('name', 'like', '%' . $request->search . '%')
                       ->orWhere('badge_id', 'like', '%' . $request->search . '%');
                });
            })
            ->get();

        return Inertia::render('HR/Leave/LegacyImport', [
            'employees'   => $employees,
            'leaveTypes'  => LeaveType::where('is_active', true)
                ->orderBy('sort_order')
                ->get(['id', 'code', 'name']),
            'filters'     => $request->only('search'),
        ]);
    }

    public function store(Request $request, DTRService $dtr)
    {
        $this->authorize('hr.leave.credits.manage');

        $data = $request->validate([
            'user_id'         => ['required', 'exists:users,id'],
            'leave_type_id'   => ['required', 'exists:leave_types,id'],
            'date_from'       => ['required', 'date'],
            'date_to'         => ['required', 'date', 'gte:date_from'],
            'days_applied'    => ['required', 'numeric', 'min:0.5', 'max:365'],
            'is_without_pay'  => ['boolean'],
            'legacy_ref'      => ['nullable', 'string', 'max:100'],
        ]);

        $leave = LeaveApplication::create([
            'user_id'          => $data['user_id'],
            'leave_type_id'    => $data['leave_type_id'],
            'date_from'        => $data['date_from'],
            'date_to'          => $data['date_to'],
            'days_applied'     => $data['days_applied'],
            'days_deducted'    => $data['days_applied'],
            'is_without_pay'   => $data['is_without_pay'] ?? false,
            'status'           => 'approved',
            'source'           => 'legacy',
            // Skip the 3-stage approval fields; record who imported and any reference
            'approved_by'      => Auth::id(),
            'approval_action'  => 'approved',
            'approved_at'      => now(),
            'approval_remarks' => $data['legacy_ref']
                ? 'Legacy import — ref: ' . $data['legacy_ref']
                : 'Imported from prior leave system by HR',
            'filed_at'         => now(),
        ]);

        // Immediately patch any already-generated DTR records within the date range
        $dtr->applyLeaveToExistingDtrRecords($leave);

        return back()->with('success',
            'Legacy leave imported. Re-run DTR generation for '
            . Carbon::parse($data['date_from'])->format('M j') . '–'
            . Carbon::parse($data['date_to'])->format('M j, Y')
            . ' to fully reflect the leave.'
        );
    }
}
