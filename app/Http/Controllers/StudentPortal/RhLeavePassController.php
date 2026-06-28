<?php

namespace App\Http\Controllers\StudentPortal;

use App\Http\Controllers\Controller;
use App\Models\ResidenceHall\RhIntern;
use App\Models\ResidenceHall\RhLeavePass;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class RhLeavePassController extends Controller
{
    private function resolveIntern(): ?object
    {
        $pisayID = session('student_pisaysystemID');
        $student = DB::table('students')->where('pisaysystemID', $pisayID)->first(['id']);
        if (!$student) return null;

        $sy = DB::table('school_years')->where('is_current', 1)->first(['id']);
        if (!$sy) return null;

        return RhIntern::where('student_id', $student->id)
            ->where('school_year_id', $sy->id)
            ->where('status', 'active')
            ->first(['id', 'rh_room_id', 'bed_number', 'school_year_id']);
    }

    public function index()
    {
        $intern = $this->resolveIntern();

        $leavePasses = $intern
            ? RhLeavePass::where('rh_intern_id', $intern->id)
                ->orderByDesc('created_at')
                ->limit(30)
                ->get(['id', 'purpose', 'destination', 'status', 'expected_return_at', 'approved_at', 'remarks', 'created_at'])
            : collect();

        $sy = DB::table('school_years')->where('is_current', 1)->first(['name']);

        return Inertia::render('StudentPortal/RhLeavePasses', [
            'intern'      => $intern,
            'leavePasses' => $leavePasses,
            'schoolYear'  => $sy?->name,
        ]);
    }

    public function store(Request $request)
    {
        $intern = $this->resolveIntern();

        if (!$intern) {
            return back()->with('error', 'You are not currently enrolled as an active dormer.');
        }

        $validated = $request->validate([
            'purpose'            => ['required', 'in:go_home,school_activity,other'],
            'destination'        => ['required', 'string', 'max:255'],
            'expected_return_at' => ['required', 'date', 'after:now'],
            'with_companion'     => ['boolean'],
            'companion_name'     => ['nullable', 'string', 'max:255'],
            'companion_contact'  => ['nullable', 'string', 'max:50'],
        ]);

        RhLeavePass::create([
            'rh_intern_id'         => $intern->id,
            'purpose'              => $validated['purpose'],
            'destination'          => $validated['destination'],
            'expected_return_at'   => $validated['expected_return_at'],
            'with_companion'       => $validated['with_companion'] ?? false,
            'companion_name'       => $validated['companion_name'] ?? null,
            'companion_contact'    => $validated['companion_contact'] ?? null,
            'student_signature_at' => now(),
            'status'               => 'pending',
        ]);

        return back()->with('success', 'Leave pass filed. Await approval from your Dorm Manager.');
    }
}
