<?php

namespace App\Http\Controllers\StudentPortal;

use App\Http\Controllers\Controller;
use App\Models\ResidenceHall\RhApplication;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $pisayID    = session('student_pisaysystemID');
        $gradeLevel = session('student_grade_level');

        $student = DB::table('students')
            ->where('pisaysystemID', $pisayID)
            ->first();

        $sy = DB::table('school_years')->where('is_current', 1)->first();

        // Sections students must complete each enrollment
        $sections = [
            'academic'       => 'Academic Preferences',
            'activities'     => 'Activities & Awards',
            'social'         => 'Social & Personality',
            'career'         => 'Career & Vocation',
            'residence'      => 'Residence & Transport',
            'health'         => 'Physical Health',
            'allergies'      => 'Allergies',
            'immunizations'  => 'Immunizations',
            'medical_history'=> 'Medical History',
            'vitamins'       => 'Vitamins & Supplements',
        ];

        // Which sections has this student submitted for the current SY?
        $submitted = $sy
            ? DB::table('student_form_submissions')
                ->where('pisaysystemID', $pisayID)
                ->where('school_year_id', $sy->id)
                ->pluck('submitted_at', 'section')
                ->toArray()
            : [];

        $completion = [];
        foreach ($sections as $key => $label) {
            $completion[] = [
                'key'          => $key,
                'label'        => $label,
                'submitted'    => isset($submitted[$key]),
                'submitted_at' => $submitted[$key] ?? null,
                'group'        => in_array($key, ['allergies','immunizations','medical_history','vitamins']) ? 'medical' : 'profile',
            ];
        }

        $totalDone = count(array_filter($completion, fn ($s) => $s['submitted']));

        $rhApplication = ($sy && $student)
            ? RhApplication::where('student_id', $student->id)
                ->where('school_year_id', $sy->id)
                ->latest()
                ->first(['id', 'status', 'preferred_hall', 'created_at'])
            : null;

        return Inertia::render('StudentPortal/Dashboard', [
            'student'       => $student,
            'grade_level'   => $gradeLevel,
            'school_year'   => $sy?->name,
            'completion'    => $completion,
            'total_done'    => $totalDone,
            'total'         => count($sections),
            'rhApplication' => $rhApplication,
        ]);
    }
}
