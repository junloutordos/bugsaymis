<?php

namespace App\Http\Controllers\ALP;

use App\Http\Controllers\Controller;
use App\Models\ALP\AlpDocument;
use App\Models\ALP\AlpMembership;
use App\Models\ALP\AlpProgramCycle;
use App\Models\ALP\AlpReport;
use App\Models\ALP\AlpSession;
use App\Models\FacultyLoading\SchoolYear;
use App\Services\ALP\AlpAccessService;
use App\Services\ALP\AlpFileService;
use App\Services\ALP\AlpPdfService;
use App\Services\ALP\AlpRosterService;
use App\Services\ALP\AlpWorkflowService;
use Illuminate\Http\Request;

class AlpPdfController extends Controller
{
    public function __construct(private AlpAccessService $access, private AlpPdfService $pdf, private AlpFileService $files, private AlpWorkflowService $workflow, private AlpRosterService $roster) {}

    public function document(Request $request, AlpProgramCycle $cycle, AlpDocument $document)
    {
        $this->access->authorizeView($request->user(), $cycle);
        abort_unless($document->alp_program_cycle_id === $cycle->id, 404);

        return $this->response($this->pdf->document($document), $document->form_code.'-'.$cycle->program->code.'.pdf');
    }

    public function membersList(Request $request)
    {
        $schoolYears = SchoolYear::orderByDesc('name')->get(['id', 'name', 'is_current']);
        $schoolYearId = $request->integer('school_year_id') ?: $schoolYears->firstWhere('is_current', true)?->id;
        $schoolYear = $schoolYears->firstWhere('id', $schoolYearId);
        abort_unless($schoolYear, 404);
        $members = $this->roster->activeMembers($schoolYearId, $request->user());

        return $this->response($this->pdf->membersList($members->all(), $schoolYear->name), 'ALP-Active-Members-'.$schoolYear->name.'.pdf');
    }

    public function unassignedList(Request $request)
    {
        $schoolYears = SchoolYear::orderByDesc('name')->get(['id', 'name', 'is_current']);
        $schoolYearId = $request->integer('school_year_id') ?: $schoolYears->firstWhere('is_current', true)?->id;
        $schoolYear = $schoolYears->firstWhere('id', $schoolYearId);
        abort_unless($schoolYear, 404);
        $students = $this->roster->unassignedGrades7To10($schoolYearId);

        return $this->response($this->pdf->unassignedList($students->all(), $schoolYear->name), 'ALP-Unassigned-7-10-'.$schoolYear->name.'.pdf');
    }

    public function package(Request $request, AlpProgramCycle $cycle)
    {
        $this->access->authorizeView($request->user(), $cycle);

        return $this->response($this->pdf->package($cycle), 'ALP-'.$cycle->program->code.'-'.$cycle->schoolYear->name.'-package.pdf');
    }

    public function report(Request $request, AlpProgramCycle $cycle, AlpReport $report)
    {
        $this->access->authorizeView($request->user(), $cycle);
        abort_unless($report->alp_program_cycle_id === $cycle->id, 404);

        return $this->response($this->pdf->report($report), 'ALP-'.$report->report_type.'-'.$report->period.'.pdf');
    }

    public function certificate(Request $request, AlpProgramCycle $cycle, AlpMembership $membership)
    {
        $this->access->authorizeManage($request->user(), $cycle);
        abort_unless($membership->alp_program_cycle_id === $cycle->id, 404);
        abort_unless($cycle->status === 'closed' && $membership->status === 'completed', 422, 'Certificates are available after successful year-end completion.');
        $bytes = $this->pdf->certificate($membership);
        if (! $membership->completion_certificate_file_id) {
            $fileId = $this->files->storeBytes(
                "{$cycle->school_year_id}/{$cycle->id}/certificates",
                "student-{$membership->student_id}.pdf",
                $bytes,
            );
            $membership->update(['completion_certificate_file_id' => $fileId, 'completed_at' => now()]);
            $this->workflow->log($cycle, $request->user(), 'completion_certificate_issued', $membership);
        }

        return $this->response($bytes, 'ALP-Certificate-'.$membership->student->pisaysystemID.'.pdf');
    }

    public function attendance(Request $request, AlpProgramCycle $cycle, AlpSession $session)
    {
        $this->access->authorizeView($request->user(), $cycle);
        abort_unless($session->alp_program_cycle_id === $cycle->id, 404);

        return $this->response($this->pdf->attendance($session), 'PSHS-00-F-DSA-33-'.$cycle->program->code.'-'.$session->session_date->format('Y-m-d').'.pdf');
    }

    private function response(string $bytes, string $filename)
    {
        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.preg_replace('/[^A-Za-z0-9._-]/', '-', $filename).'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
