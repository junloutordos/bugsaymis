<?php

namespace App\Services\StudentPortal;

use App\Models\ResidenceHall\RhApplication;
use App\Models\ResidenceHall\RhIntern;
use App\Models\ResidenceHall\RhLeavePass;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Shared Residence Hall logic (accommodation application + leave passes)
 * for the student web portal and the mobile API.
 */
class RhPortalService
{
    /**
     * Data for the RH application screen.
     */
    public function applicationData(string $pisayID): array
    {
        $student = DB::table('students')->where('pisaysystemID', $pisayID)->first();
        $sy      = DB::table('school_years')->where('is_current', 1)->first();

        $application = ($sy && $student)
            ? RhApplication::where('student_id', $student->id)
                ->where('school_year_id', $sy->id)
                ->latest()
                ->first()
            : null;

        $intern = ($sy && $student)
            ? RhIntern::where('student_id', $student->id)
                ->where('school_year_id', $sy->id)
                ->where('status', 'active')
                ->first(['id', 'rh_room_id', 'status'])
            : null;

        // Auto-suggest hall from student sex
        $suggestedHall = $student
            ? (strtolower($student->sex ?? '') === 'female' ? 'GRH' : 'BRH')
            : 'BRH';

        return [
            'student'       => $student,
            'school_year'   => $sy?->name,
            'application'   => $application,
            'intern'        => $intern,
            'suggestedHall' => $suggestedHall,
        ];
    }

    /**
     * Validate and file an RH accommodation application.
     * Returns an error message, or null on success.
     */
    public function storeApplication(Request $request, string $pisayID): ?string
    {
        $student = DB::table('students')
            ->where('pisaysystemID', $pisayID)
            ->first(['id', 'sex', 'province', 'schocat']);

        if (! $student) {
            return 'Student record not found.';
        }

        $sy = DB::table('school_years')->where('is_current', 1)->first();

        if (! $sy) {
            return 'No active school year found.';
        }

        $exists = RhApplication::where('student_id', $student->id)
            ->where('school_year_id', $sy->id)
            ->exists();

        if ($exists) {
            return 'You already have an application for this school year.';
        }

        $validated = $request->validate([
            'preferred_hall'        => ['required', 'in:BRH,GRH'],
            'home_province'         => ['required', 'string', 'max:100'],
            'estimated_distance_km' => ['required', 'integer', 'min:0'],
            'scholarship_category'  => ['nullable', 'string', 'max:50'],
            'foster_parent_name'    => ['required', 'string', 'max:200'],
            'foster_parent_contact' => ['required', 'string', 'max:50'],
            'foster_parent_address' => ['required', 'string', 'max:255'],
        ]);

        RhApplication::create([
            'student_id'            => $student->id,
            'school_year_id'        => $sy->id,
            'preferred_hall'        => $validated['preferred_hall'],
            'home_province'         => $validated['home_province'],
            'estimated_distance_km' => $validated['estimated_distance_km'],
            'scholarship_category'  => $validated['scholarship_category'] ?? null,
            'foster_parent_name'    => $validated['foster_parent_name'],
            'foster_parent_contact' => $validated['foster_parent_contact'],
            'foster_parent_address' => $validated['foster_parent_address'],
        ]);

        return null;
    }

    /**
     * Data for the leave passes screen.
     */
    public function leavePassData(string $pisayID): array
    {
        $intern = $this->resolveIntern($pisayID);

        $leavePasses = $intern
            ? RhLeavePass::where('rh_intern_id', $intern->id)
                ->orderByDesc('created_at')
                ->limit(30)
                ->get(['id', 'purpose', 'destination', 'status', 'expected_return_at', 'approved_at', 'remarks', 'created_at'])
            : collect();

        $sy = DB::table('school_years')->where('is_current', 1)->first(['name']);

        return [
            'intern'      => $intern,
            'leavePasses' => $leavePasses,
            'schoolYear'  => $sy?->name,
        ];
    }

    /**
     * Validate and file a leave pass for an active dormer.
     * Returns an error message, or null on success.
     */
    public function storeLeavePass(Request $request, string $pisayID): ?string
    {
        $intern = $this->resolveIntern($pisayID);

        if (! $intern) {
            return 'You are not currently enrolled as an active dormer.';
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

        return null;
    }

    /**
     * The student's active RhIntern record for the current school year.
     */
    public function resolveIntern(string $pisayID): ?RhIntern
    {
        $student = DB::table('students')->where('pisaysystemID', $pisayID)->first(['id']);
        if (! $student) {
            return null;
        }

        $sy = DB::table('school_years')->where('is_current', 1)->first(['id']);
        if (! $sy) {
            return null;
        }

        return RhIntern::where('student_id', $student->id)
            ->where('school_year_id', $sy->id)
            ->where('status', 'active')
            ->first(['id', 'rh_room_id', 'bed_number', 'school_year_id']);
    }
}
