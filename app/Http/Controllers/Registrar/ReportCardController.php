<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Controller;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\Student;
use App\Services\Registrar\StudentReportCardService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ReportCardController extends Controller
{
    public function __construct(
        private readonly StudentReportCardService $reportCardService,
    ) {}

    /**
     * GET /registrar/students/{student}/report-card/{schoolYear}
     * Streams the PDF report card directly to the browser.
     */
    public function download(Request $request, int $studentId, int $schoolYearId): Response
    {
        $this->authorize('students.transcript.view');

        $student    = Student::findOrFail($studentId);
        $schoolYear = SchoolYear::findOrFail($schoolYearId);

        $pdf = $this->reportCardService->generate($studentId, $schoolYearId);

        $filename = 'ReportCard_' . str_replace([',', ' '], ['', '_'], $student->full_name)
            . '_' . str_replace('-', '', $schoolYear->name) . '.pdf';

        return response($pdf, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$filename}\"",
            'Cache-Control'       => 'private, no-cache',
        ]);
    }
}
