<?php

namespace App\Http\Controllers\StudentPortal;

use App\Http\Controllers\Controller;
use App\Models\ResidenceHall\RhApplication;
use App\Models\ResidenceHall\RhIntern;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class RhApplicationController extends Controller
{
    public function show()
    {
        $pisayID = session('student_pisaysystemID');

        $student = DB::table('students')
            ->where('pisaysystemID', $pisayID)
            ->first();

        $sy = DB::table('school_years')->where('is_current', 1)->first();

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

        return Inertia::render('StudentPortal/RhApplication', [
            'student'       => $student,
            'school_year'   => $sy?->name,
            'application'   => $application,
            'intern'        => $intern,
            'suggestedHall' => $suggestedHall,
        ]);
    }

    public function store(Request $request)
    {
        $pisayID = session('student_pisaysystemID');

        $student = DB::table('students')
            ->where('pisaysystemID', $pisayID)
            ->first(['id', 'sex', 'province', 'schocat']);

        if (!$student) {
            return back()->with('error', 'Student record not found.');
        }

        $sy = DB::table('school_years')->where('is_current', 1)->first();

        if (!$sy) {
            return back()->with('error', 'No active school year found.');
        }

        $exists = RhApplication::where('student_id', $student->id)
            ->where('school_year_id', $sy->id)
            ->exists();

        if ($exists) {
            return back()->with('error', 'You already have an application for this school year.');
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

        return back()->with('success', 'Application submitted. The Residence Hall Committee will review your application.');
    }
}
