<?php

namespace App\Http\Controllers\StudentPortal;

use App\Http\Controllers\Controller;
use App\Services\StudentPortal\MedicalRecordsService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MedicalController extends Controller
{
    public function show(MedicalRecordsService $service)
    {
        return Inertia::render('StudentPortal/Medical', $service->showData(session('student_pisaysystemID')));
    }

    public function saveSection(Request $request, MedicalRecordsService $service, string $section)
    {
        $service->saveSection(
            $request,
            $section,
            session('student_pisaysystemID'),
            (int) session('student_grade_level'),
        );

        return back()->with('success', 'Records saved successfully.');
    }
}
