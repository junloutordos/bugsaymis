<?php

namespace App\Http\Controllers\HomeroomAttendance;

use App\Http\Controllers\Controller;
use App\Models\FacultyLoading\Section;
use App\Models\HomeroomAttendance\AdmissionSlip;
use App\Services\HomeroomAttendance\AdmissionSlipPdfService;
use App\Services\HomeroomAttendance\AdmissionSlipService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdmissionSlipController extends Controller
{
    public function __construct(
        private AdmissionSlipService $slips,
        private AdmissionSlipPdfService $pdf,
    ) {
    }

    // ── GET /homeroom-attendance/admission-slips ───────────────────────────────

    public function index(Request $request)
    {
        $sectionId = $request->query('section') ? (int) $request->query('section') : null;

        $sections = Section::where('is_active', true)
            ->where('sectionname', 'not like', 'SCI-%')
            ->where('sectionname', 'not like', 'ELEC-%')
            ->orderBy('levelid')->orderBy('sectionname')
            ->get(['id', 'sectionname', 'levelid']);

        $pending = $sectionId ? $this->slips->pending($sectionId) : collect();

        $issued = $sectionId
            ? AdmissionSlip::where('section_id', $sectionId)
                ->with('student')
                ->latest('issued_at')
                ->limit(20)
                ->get()
            : collect();

        return Inertia::render('HomeroomAttendance/AdmissionSlips/Index', [
            'sections'  => $sections,
            'sectionId' => $sectionId,
            'pending'   => $pending->map(fn ($record) => [
                'attendance_record_id' => $record->id,
                'student_id'           => $record->student_id,
                'student_name'         => trim("{$record->student->lastname}, {$record->student->firstname} {$record->student->middlename}"),
                'date'                 => $record->attendanceDate->date->toDateString(),
                'status'               => $record->status,
            ])->values(),
            'issued'    => $issued->map(fn (AdmissionSlip $slip) => [
                'id'             => $slip->id,
                'student_name'   => trim("{$slip->student->lastname}, {$slip->student->firstname} {$slip->student->middlename}"),
                'infraction_date'=> $slip->infraction_date->toDateString(),
                'infraction_type'=> $slip->infraction_type,
                'excused_status' => $slip->excused_status,
                'print_url'      => route('homeroom-attendance.admission-slips.print', $slip),
                'document_url'   => $slip->supporting_document_path
                    ? route('homeroom-attendance.admission-slips.document', $slip)
                    : null,
            ])->values(),
        ]);
    }

    // ── POST /homeroom-attendance/admission-slips ──────────────────────────────

    public function store(Request $request)
    {
        $data = $request->validate([
            'student_id'        => ['required', 'integer'],
            'section_id'        => ['required', 'integer'],
            'infraction_date'   => ['required', 'date'],
            'infraction_type'   => ['required', 'in:absence,tardy,cutting'],
            'excused_status'    => ['required', 'in:excused,unexcused'],
            'reason'            => ['nullable', 'string', 'max:255'],
            'document_base64'   => ['nullable', 'string'],
        ]);

        if (! empty($data['document_base64'])) {
            $data['supporting_document_path'] = $this->storeDocument($data['document_base64'], $data['student_id']);
        }
        unset($data['document_base64']);

        $slip = $this->slips->issue($data, Auth::id());

        return redirect()
            ->route('homeroom-attendance.admission-slips.index', ['section' => $data['section_id']])
            ->with('success', 'Class Admission Slip issued.')
            ->with('slip_id', $slip->id);
    }

    // ── GET /homeroom-attendance/admission-slips/{slip}/print ──────────────────

    public function print(AdmissionSlip $slip): StreamedResponse
    {
        return $this->pdf->render($slip);
    }

    // ── GET /homeroom-attendance/admission-slips/{slip}/document ────────────────

    /**
     * Streams the document attached to THIS slip only — the S3 key always
     * comes from the slip's own DB column, never from client input, so there
     * is no way to walk this endpoint into an arbitrary S3 path.
     */
    public function document(AdmissionSlip $slip): StreamedResponse
    {
        $s3Key = $slip->supporting_document_path;
        abort_unless($s3Key && Storage::disk('s3')->exists($s3Key), 404);

        $stream = Storage::disk('s3')->readStream($s3Key);

        return new StreamedResponse(function () use ($stream) {
            fpassthru($stream);
        }, 200, [
            'Content-Type' => Storage::disk('s3')->mimeType($s3Key) ?: 'application/octet-stream',
        ]);
    }

    private function storeDocument(string $dataUri, int $studentId): string
    {
        [, $base64] = str_contains($dataUri, ',') ? explode(',', $dataUri, 2) : [null, $dataUri];
        $imageData = base64_decode($base64);

        $s3Key = 'homeroom-attendance/admission-slips/'.$studentId.'/'.uniqid('doc_', true);
        Storage::disk('s3')->put($s3Key, $imageData);

        return $s3Key;
    }
}
