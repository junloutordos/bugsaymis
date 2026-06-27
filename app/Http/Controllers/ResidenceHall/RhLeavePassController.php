<?php

namespace App\Http\Controllers\ResidenceHall;

use App\Http\Controllers\Controller;
use App\Models\ResidenceHall\RhLeavePass;
use App\Models\ResidenceHall\RhIntern;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class RhLeavePassController extends Controller
{
    private function hallScope(): ?string
    {
        $user = Auth::user();
        if ($user->isSuperAdmin()) return null;
        return $user->rh_hall ?: null;
    }

    public function index(Request $request)
    {
        $this->authorize('rh.leave-passes.view');

        $hall   = $this->hallScope();
        $status = $request->input('status', '');
        $search = $request->input('search', '');

        $passes = RhLeavePass::with(['intern.room'])
            ->whereHas('intern', function ($q) use ($hall) {
                if ($hall) $q->whereHas('room', fn($r) => $r->where('residence_hall', $hall));
            })
            ->when($status, fn($q) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($lp) use ($search) {
                $resolver = app(\App\Services\Discipline\StudentResolver::class);
                $student  = $resolver->resolve($lp->intern->student_id ?? 0);
                $name     = $student['name'] ?? ('Student #' . $lp->intern->student_id);
                if ($search && !str_contains(strtolower($name), strtolower($search))) return null;
                return [
                    'id'                 => $lp->id,
                    'intern_id'          => $lp->rh_intern_id,
                    'student_name'       => $name,
                    'residence_hall'     => $lp->intern->room?->residence_hall,
                    'room_number'        => $lp->intern->room?->room_number,
                    'purpose'            => $lp->purpose,
                    'destination'        => $lp->destination,
                    'with_companion'     => $lp->with_companion,
                    'companion_name'     => $lp->companion_name,
                    'companion_contact'  => $lp->companion_contact,
                    'expected_return_at' => $lp->expected_return_at,
                    'actual_departure_at'=> $lp->actual_departure_at,
                    'actual_return_at'   => $lp->actual_return_at,
                    'status'             => $lp->status,
                    'remarks'            => $lp->remarks,
                    'created_at'         => $lp->created_at,
                    'student_signature_at' => $lp->student_signature_at,
                ];
            })
            ->filter()
            ->values();

        return Inertia::render('ResidenceHall/LeavePasses/Index', [
            'passes'  => $passes,
            'filters' => compact('status', 'search'),
            'myHall'  => $hall,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('rh.leave-passes.view');

        $data = $request->validate([
            'rh_intern_id'       => 'required|exists:rh_interns,id',
            'purpose'            => 'required|in:go_home,school_activity,other',
            'destination'        => 'required|string|max:255',
            'with_companion'     => 'boolean',
            'companion_name'     => 'nullable|string|max:255',
            'companion_contact'  => 'nullable|string|max:50',
            'expected_return_at' => 'nullable|date',
            'student_signature_at' => 'nullable|date',
        ]);

        $intern = RhIntern::findOrFail($data['rh_intern_id']);
        $this->checkHallAccess($intern);

        $data['status'] = 'pending';
        RhLeavePass::create($data);

        return back()->with('success', 'Leave pass created.');
    }

    public function approve(Request $request, RhLeavePass $rhLeavePass)
    {
        $this->authorize('rh.leave-passes.approve');
        $this->checkHallAccess($rhLeavePass->intern);

        $data = $request->validate([
            'action'  => 'required|in:approve,reject',
            'remarks' => 'nullable|string|max:500',
        ]);

        $rhLeavePass->update([
            'status'      => $data['action'] === 'approve' ? 'approved' : 'rejected',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
            'remarks'     => $data['remarks'] ?? null,
        ]);

        return back()->with('success', 'Leave pass ' . $data['action'] . 'd.');
    }

    public function depart(Request $request, RhLeavePass $rhLeavePass)
    {
        $this->authorize('rh.leave-passes.guard');
        $this->checkHallAccess($rhLeavePass->intern);

        if ($rhLeavePass->status !== 'approved') {
            return back()->withErrors(['message' => 'Leave pass is not approved.']);
        }

        $rhLeavePass->update([
            'status'             => 'departed',
            'actual_departure_at'=> now(),
            'guard_out_id'       => Auth::id(),
        ]);

        return back()->with('success', 'Departure logged.');
    }

    public function returnIn(RhLeavePass $rhLeavePass)
    {
        $this->authorize('rh.leave-passes.guard');
        $this->checkHallAccess($rhLeavePass->intern);

        if ($rhLeavePass->status !== 'departed') {
            return back()->withErrors(['message' => 'Leave pass is not in departed status.']);
        }

        $rhLeavePass->update([
            'status'          => 'returned',
            'actual_return_at'=> now(),
            'guard_in_id'     => Auth::id(),
        ]);

        return back()->with('success', 'Return logged.');
    }

    private function checkHallAccess(RhIntern $intern): void
    {
        $hall = $this->hallScope();
        if ($hall && $intern->room && $intern->room->residence_hall !== $hall) {
            abort(403);
        }
    }
}
