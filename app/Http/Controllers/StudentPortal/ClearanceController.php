<?php

namespace App\Http\Controllers\StudentPortal;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Services\StudentClearance\StudentClearancePdfService;
use App\Services\StudentClearance\StudentClearanceService;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ClearanceController extends Controller
{
    public function show(StudentClearanceService $service): Response
    {
        $student = Student::where('pisaysystemID', session('student_pisaysystemID'))->firstOrFail();
        $period = $service->activeOrLatestPeriod();

        $clearance = $period ? $service->clearanceForStudent($period, $student->id) : null;

        return Inertia::render('StudentPortal/Clearance/Show', [
            'student' => [
                'id'        => $student->id,
                'full_name' => $student->full_name,
                'pisays_id' => $student->pisaysystemID,
            ],
            'period' => $period ? [
                'id'       => $period->id,
                'title'    => $period->title,
                'status'   => $period->status,
                'sy_name'  => $period->schoolYear?->name,
                'opens_at' => $period->opens_at?->format('Y-m-d'),
                'closes_at'=> $period->closes_at?->format('Y-m-d'),
            ] : null,
            'clearance' => $clearance ? $service->serializeForStudent($clearance) : null,
        ]);
    }

    public function download(StudentClearanceService $service, StudentClearancePdfService $pdfService): SymfonyResponse
    {
        $student = Student::where('pisaysystemID', session('student_pisaysystemID'))->firstOrFail();
        $period = $service->activeOrLatestPeriod();

        abort_unless($period, 404);

        $clearance = \App\Models\StudentClearance\StudentClearance::where('student_clearance_period_id', $period->id)
            ->where('student_id', $student->id)
            ->firstOrFail();

        $pdf = $pdfService->generate($clearance);
        $filename = 'Year_End_Clearance_'.str_replace([',', ' '], ['', '_'], $student->full_name).'.pdf';

        return response($pdf, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$filename}\"",
            'Cache-Control'       => 'private, no-cache',
        ]);
    }
}
