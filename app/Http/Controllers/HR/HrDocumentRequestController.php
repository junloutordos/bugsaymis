<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\HrDocumentRequest;
use App\Models\HR\HrDocumentRequestAttachment;
use App\Models\HR\HrDocumentRequestType;
use App\Models\HR\HrIssuedDocument;
use App\Services\DigitalSignatureService;
use App\Services\HR\HrDocumentRequestService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class HrDocumentRequestController extends Controller
{
    public function __construct(
        private HrDocumentRequestService $service,
        private DigitalSignatureService $signatures,
    ) {}

    public function index(Request $request)
    {
        abort_unless($request->user()->hasPermission('hr.document-requests.file'), 403);

        $requests = HrDocumentRequest::with(['type', 'processor:id,name', 'latestIssuedDocument'])
            ->where('requester_id', $request->user()->id)
            ->latest()->get();

        return Inertia::render('HR/DocumentRequests/Index', [
            'requests' => $requests,
            'types' => HrDocumentRequestType::where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->hasPermission('hr.document-requests.file'), 403);
        $data = $this->validateRequest($request);
        $type = HrDocumentRequestType::where('is_active', true)->findOrFail($data['request_type_id']);
        abort_if($data['delivery_method'] === 'digital' && ! $type->supports_digital, 422, 'Digital delivery is unavailable for this document type.');
        abort_if($data['delivery_method'] === 'pickup' && ! $type->supports_pickup, 422, 'Pickup is unavailable for this document type.');

        $created = $this->service->submit($request->user(), $type, $data);

        return redirect()->route('hr.document-requests.show', $created)
            ->with('success', 'Document request submitted successfully.');
    }

    public function show(Request $request, HrDocumentRequest $documentRequest)
    {
        $this->authorizeView($request, $documentRequest);
        $canProcess = $this->canProcess($request);
        $canApprove = $request->user()->hasPermission('hr.document-requests.ocd-approve');
        $documentRequest->load([
            'type', 'requester.employeeProfile', 'processor:id,name', 'ocdApprover:id,name',
            'events.actor:id,name', 'attachments', 'issuedDocuments.issuer:id,name',
        ]);

        if (! $canProcess && ! $canApprove) {
            $documentRequest->makeHidden('internal_notes');
            $documentRequest->events->each(function ($event) {
                if ($event->action === 'ocd_returned') {
                    $event->makeHidden(['remarks', 'metadata']);
                }
            });
        }

        return Inertia::render('HR/DocumentRequests/Show', [
            'documentRequest' => $documentRequest,
            'canProcess' => $canProcess,
            'canIssue' => $request->user()->hasPermission('hr.document-requests.issue'),
            'hasPin' => ! empty($request->user()->signature_pin),
            'signatureUri' => $this->signatures->getSignatureDataUri($request->user()),
        ]);
    }

    public function cancel(Request $request, HrDocumentRequest $documentRequest)
    {
        $this->service->cancel($documentRequest, $request->user());

        return back()->with('success', 'Request cancelled.');
    }

    public function resubmit(Request $request, HrDocumentRequest $documentRequest)
    {
        $data = $request->validate([
            'purpose' => 'required|string|max:2000',
            'addressed_to' => 'nullable|string|max:255',
            'destination_agency' => 'nullable|string|max:255',
            'needed_at' => 'nullable|date|after_or_equal:today',
            'delivery_method' => 'required|in:digital,pickup',
            'special_instructions' => 'nullable|string|max:2000',
        ]);
        $this->service->resubmit($documentRequest->load('type'), $request->user(), $data);

        return back()->with('success', 'Request resubmitted to HR.');
    }

    public function manage(Request $request)
    {
        abort_unless($this->canProcess($request), 403);
        $requests = HrDocumentRequest::with(['type', 'requester:id,name,position', 'processor:id,name'])
            ->latest()->get();

        $stats = [
            'new' => $requests->where('status', 'submitted')->count(),
            'processing' => $requests->whereIn('status', ['processing', 'ocd_returned'])->count(),
            'awaiting_ocd' => $requests->where('status', 'pending_ocd_approval')->count(),
            'overdue' => $requests->whereNotIn('status', ['issued', 'rejected', 'cancelled'])
                ->filter(fn ($row) => $row->due_at?->isPast())->count(),
            'issued' => $requests->where('status', 'issued')->count(),
        ];

        return Inertia::render('HR/DocumentRequests/Manage', [
            'requests' => $requests,
            'stats' => $stats,
            'types' => HrDocumentRequestType::orderBy('sort_order')->get(),
            'canManageTypes' => $request->user()->hasPermission('hr.document-requests.manage-types'),
        ]);
    }

    public function start(Request $request, HrDocumentRequest $documentRequest)
    {
        $this->authorizeProcess($request);
        $this->service->start($documentRequest->load(['type', 'requester.employeeProfile', 'requester.pds.personalInfo']), $request->user());

        return redirect()->route('hr.document-requests.show', $documentRequest)->with('success', 'Request accepted for processing.');
    }

    public function prepare(Request $request, HrDocumentRequest $documentRequest)
    {
        $this->authorizeProcess($request);
        $data = $request->validate([
            'document_payload' => 'required|array',
            'document_payload.employee_name' => 'required|string|max:255',
            'document_payload.position' => 'nullable|string|max:255',
            'document_payload.employee_no' => 'nullable|string|max:100',
            'document_payload.appointment_status' => 'nullable|string|max:255',
            'document_payload.body' => 'nullable|string|max:10000',
            'document_payload.certification_clause' => 'nullable|string|max:2000',
            'document_payload.service_rows' => 'nullable|array|max:100',
            'document_payload.service_rows.*.from' => 'nullable|string|max:50',
            'document_payload.service_rows.*.to' => 'nullable|string|max:50',
            'document_payload.service_rows.*.position' => 'nullable|string|max:255',
            'document_payload.service_rows.*.agency' => 'nullable|string|max:255',
            'document_payload.service_rows.*.appointment_status' => 'nullable|string|max:100',
            'document_payload.service_rows.*.government_service' => 'nullable|string|max:20',
            'document_payload.service_record_snapshot' => 'nullable|array',
            'document_payload.service_rows.*.employment_status' => 'nullable|string|max:100',
            'document_payload.service_rows.*.appointment_nature' => 'nullable|string|max:100',
            'document_payload.service_rows.*.salary_amount' => 'nullable|numeric|min:0',
            'document_payload.service_rows.*.salary_basis' => 'nullable|string|max:20',
            'document_payload.service_rows.*.salary_grade' => 'nullable|integer|min:1|max:33',
            'document_payload.service_rows.*.salary_step' => 'nullable|integer|min:1|max:8',
            'document_payload.service_rows.*.station_place' => 'nullable|string|max:255',
            'document_payload.service_rows.*.branch_division' => 'nullable|string|max:255',
            'document_payload.service_rows.*.lwop_periods' => 'nullable|array|max:30',
            'document_payload.service_rows.*.separation_date' => 'nullable|string|max:50',
            'document_payload.service_rows.*.separation_cause' => 'nullable|string|max:255',
            'document_payload.birth_date' => 'nullable|string|max:50',
            'document_payload.birth_place' => 'nullable|string|max:255',
            'internal_notes' => 'nullable|string|max:3000',
        ]);
        if ($documentRequest->type->template_key === 'service_record'
            && isset($data['document_payload']['service_record_snapshot'])) {
            $data['document_payload']['service_record_snapshot']['entries'] = $data['document_payload']['service_rows'] ?? [];
        }
        $this->service->saveDraft($documentRequest, $request->user(), $data['document_payload'], $data['internal_notes'] ?? null);

        return back()->with('success', 'Document draft saved.');
    }

    public function returnToEmployee(Request $request, HrDocumentRequest $documentRequest)
    {
        $this->authorizeProcess($request);
        $data = $request->validate(['remarks' => 'required|string|max:2000']);
        $this->service->returnToEmployee($documentRequest, $request->user(), $data['remarks']);

        return back()->with('success', 'Request returned to the employee.');
    }

    public function reject(Request $request, HrDocumentRequest $documentRequest)
    {
        $this->authorizeProcess($request);
        $data = $request->validate(['remarks' => 'required|string|max:2000']);
        $this->service->reject($documentRequest, $request->user(), $data['remarks']);

        return back()->with('success', 'Request rejected.');
    }

    public function routeToOcd(Request $request, HrDocumentRequest $documentRequest)
    {
        $this->authorizeProcess($request);
        $this->service->routeToOcd($documentRequest->load('type'), $request->user());

        return back()->with('success', 'Document routed to the OCD Approval Inbox.');
    }

    public function issue(Request $request, HrDocumentRequest $documentRequest)
    {
        abort_unless($request->user()->hasPermission('hr.document-requests.issue'), 403);
        $data = $request->validate(['pin' => 'nullable|string|max:20']);
        $issued = $this->service->issue($documentRequest->load('type'), $request->user(), $data['pin'] ?? null);

        return back()->with('success', "Document {$issued->control_number} issued successfully.");
    }

    public function download(Request $request, HrIssuedDocument $issuedDocument)
    {
        $issuedDocument->load('request');
        $this->authorizeView($request, $issuedDocument->request);
        abort_unless(Storage::disk('s3')->exists($issuedDocument->s3_path), 404);

        return response(Storage::disk('s3')->get($issuedDocument->s3_path), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$issuedDocument->control_number.'.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function attachment(Request $request, HrDocumentRequestAttachment $attachment)
    {
        $attachment->load('request');
        $this->authorizeView($request, $attachment->request);
        abort_unless(Storage::disk('s3')->exists($attachment->s3_path), 404);

        return response(Storage::disk('s3')->get($attachment->s3_path), 200, [
            'Content-Type' => $attachment->mime_type,
            'Content-Disposition' => 'attachment; filename="'.basename($attachment->original_filename).'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function updateType(Request $request, HrDocumentRequestType $requestType)
    {
        abort_unless($request->user()->hasPermission('hr.document-requests.manage-types'), 403);
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'instructions' => 'nullable|string|max:2000',
            'requires_ocd_approval' => 'required|boolean',
            'signatory' => ['required', Rule::in(['hr', 'ocd'])],
            'sla_business_days' => 'required|integer|min:1|max:60',
            'supports_digital' => 'required|boolean',
            'supports_pickup' => 'required|boolean',
            'is_active' => 'required|boolean',
        ]);
        $data['signatory'] = $data['requires_ocd_approval'] ? 'ocd' : 'hr';
        $requestType->update($data);

        return back()->with('success', 'Document type settings updated.');
    }

    private function validateRequest(Request $request): array
    {
        return $request->validate([
            'request_type_id' => 'required|integer|exists:hr_document_request_types,id',
            'purpose' => 'required|string|max:2000',
            'addressed_to' => 'nullable|string|max:255',
            'destination_agency' => 'nullable|string|max:255',
            'needed_at' => 'nullable|date|after_or_equal:today',
            'copies' => 'required|integer|min:1|max:10',
            'delivery_method' => 'required|in:digital,pickup',
            'special_instructions' => 'nullable|string|max:2000',
            'attachments' => 'nullable|array|max:5',
            'attachments.*.name' => 'required|string|max:255',
            'attachments.*.data_uri' => 'required|string|max:15000000',
        ]);
    }

    private function authorizeView(Request $request, HrDocumentRequest $documentRequest): void
    {
        abort_unless(
            $documentRequest->isOwnedBy($request->user())
            || $this->canProcess($request)
            || (
                $request->user()->hasPermission('hr.document-requests.ocd-approve')
                && $documentRequest->type->requires_ocd_approval
                && in_array($documentRequest->status, ['pending_ocd_approval', 'ocd_returned', 'ready_for_release', 'issued'], true)
            ),
            403,
        );
    }

    private function authorizeProcess(Request $request): void
    {
        abort_unless($this->canProcess($request), 403);
    }

    private function canProcess(Request $request): bool
    {
        return $request->user()->hasPermission('hr.document-requests.process');
    }
}
