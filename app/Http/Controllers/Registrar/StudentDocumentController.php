<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Controller;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\Student;
use App\Services\Registrar\StudentDocumentService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StudentDocumentController extends Controller
{
    private const TYPES = ['coe', 'good-moral', 'clearance'];

    public function __construct(
        private readonly StudentDocumentService $documentService,
    ) {}

    /**
     * GET /registrar/students/{student}/documents/{type}/{schoolYear}
     *
     * type: coe | good-moral | clearance
     */
    public function download(Request $request, int $studentId, string $type, int $schoolYearId): Response
    {
        $this->authorize('students.records.export');

        abort_unless(in_array($type, self::TYPES), 404);

        $student    = Student::findOrFail($studentId);
        $schoolYear = SchoolYear::findOrFail($schoolYearId);

        $pdf = match ($type) {
            'coe'        => $this->documentService->generateCertificateOfEnrollment($studentId, $schoolYearId),
            'good-moral' => $this->documentService->generateCertificateOfGoodMoral($studentId, $schoolYearId),
            'clearance'  => $this->documentService->generateClearance($studentId, $schoolYearId),
        };

        $label    = ['coe' => 'COE', 'good-moral' => 'GoodMoral', 'clearance' => 'Clearance'][$type];
        $name     = str_replace([',', ' '], ['', '_'], $student->full_name);
        $sy       = str_replace('-', '', $schoolYear->name);
        $filename = "{$label}_{$name}_{$sy}.pdf";

        return response($pdf, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$filename}\"",
            'Cache-Control'       => 'private, no-cache',
        ]);
    }
}
