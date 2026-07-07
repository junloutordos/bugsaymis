<?php

namespace App\Http\Controllers\StudentPortal;

use App\Http\Controllers\Controller;
use App\Services\StudentPortal\GuidanceProfileService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProfileController extends Controller
{
    public function show(GuidanceProfileService $service)
    {
        $data = $service->showData(
            session('student_pisaysystemID'),
            (int) session('student_grade_level'),
        );

        return Inertia::render('StudentPortal/Profile', $data);
    }

    public function saveSection(Request $request, GuidanceProfileService $service, string $section)
    {
        $service->saveSection(
            $request,
            $section,
            session('student_pisaysystemID'),
            (int) session('student_grade_level'),
        );

        return back()->with('success', 'Section saved successfully.');
    }
}
