<?php

namespace App\Services\HR;

use App\Models\FacultyLoading\SalarySchedule;
use App\Models\HR\HrDocumentRequest;
use App\Models\HR\HrDocumentRequestAttachment;
use App\Models\HR\HrDocumentRequestEvent;
use App\Models\HR\HrDocumentRequestType;
use App\Models\HR\HrIssuedDocument;
use App\Models\HR\LeaveCredit;
use App\Models\User;
use App\Services\DigitalSignatureService;
use App\Services\NotificationService;
use App\Services\PersonNameFormatter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class HrDocumentRequestService
{
    public function __construct(
        private DigitalSignatureService $signatures,
        private PersonNameFormatter $names,
        private HrDocumentPdfService $pdfs,
        private ServiceRecordService $serviceRecords,
    ) {}

    public function submit(User $requester, HrDocumentRequestType $type, array $data): HrDocumentRequest
    {
        return DB::transaction(function () use ($requester, $type, $data) {
            $request = HrDocumentRequest::create([
                'reference_no' => $this->nextReferenceNumber(),
                'requester_id' => $requester->id,
                'request_type_id' => $type->id,
                'status' => 'submitted',
                'purpose' => $data['purpose'],
                'addressed_to' => $data['addressed_to'] ?? null,
                'destination_agency' => $data['destination_agency'] ?? null,
                'needed_at' => $data['needed_at'] ?? null,
                'copies' => $data['copies'] ?? 1,
                'delivery_method' => $data['delivery_method'],
                'special_instructions' => $data['special_instructions'] ?? null,
                'submitted_at' => now(),
                'due_at' => now()->addWeekdays($type->sla_business_days),
            ]);

            $this->recordEvent($request, $requester, 'submitted', null, 'submitted');

            foreach ($data['attachments'] ?? [] as $attachment) {
                $this->storeAttachment($request, $requester, $attachment);
            }

            $this->notifyHr($request, 'New request submitted');

            return $request;
        });
    }

    public function start(HrDocumentRequest $request, User $processor): void
    {
        $this->transition($request, $processor, ['submitted'], 'processing', 'accepted', null, [
            'processor_id' => $processor->id,
            'started_at' => now(),
            'document_payload' => $this->defaultPayload($request),
        ]);
        $this->notifyRequester($request, 'Your request is now being processed');
    }

    public function saveDraft(HrDocumentRequest $request, User $processor, array $payload, ?string $internalNotes): void
    {
        abort_unless(in_array($request->status, ['processing', 'ocd_returned'], true), 409, 'This request is not editable.');

        $from = $request->status;
        $request->update([
            'processor_id' => $processor->id,
            'status' => 'processing',
            'document_payload' => $payload,
            'internal_notes' => $internalNotes,
        ]);
        $this->recordEvent($request, $processor, 'draft_saved', $from, 'processing');
    }

    public function returnToEmployee(HrDocumentRequest $request, User $actor, string $remarks): void
    {
        $this->transition($request, $actor, ['submitted', 'processing'], 'returned', 'returned', $remarks, [
            'employee_visible_remarks' => $remarks,
        ]);
        $this->notifyRequester($request, 'Returned for additional information', $remarks);
    }

    public function resubmit(HrDocumentRequest $request, User $requester, array $data): void
    {
        abort_unless($request->isOwnedBy($requester) && $request->status === 'returned', 403);
        $request->update([
            'purpose' => $data['purpose'],
            'addressed_to' => $data['addressed_to'] ?? null,
            'destination_agency' => $data['destination_agency'] ?? null,
            'needed_at' => $data['needed_at'] ?? null,
            'delivery_method' => $data['delivery_method'],
            'special_instructions' => $data['special_instructions'] ?? null,
            'status' => 'submitted',
            'submitted_at' => now(),
            'due_at' => now()->addWeekdays($request->type->sla_business_days),
            'employee_visible_remarks' => null,
        ]);
        $this->recordEvent($request, $requester, 'resubmitted', 'returned', 'submitted');
        $this->notifyHr($request, 'Request resubmitted');
    }

    public function reject(HrDocumentRequest $request, User $actor, string $remarks): void
    {
        $this->transition($request, $actor, ['submitted', 'processing', 'ocd_returned'], 'rejected', 'rejected', $remarks, [
            'employee_visible_remarks' => $remarks,
            'rejected_at' => now(),
        ]);
        $this->notifyRequester($request, 'Request rejected', $remarks);
    }

    public function cancel(HrDocumentRequest $request, User $requester): void
    {
        abort_unless($request->isOwnedBy($requester), 403);
        $this->transition($request, $requester, ['submitted', 'returned'], 'cancelled', 'cancelled', null, [
            'cancelled_at' => now(),
        ]);
    }

    public function routeToOcd(HrDocumentRequest $request, User $actor): void
    {
        abort_unless($request->type->requires_ocd_approval, 422, 'This document type does not require OCD approval.');
        abort_if(empty($request->document_payload['body']) && empty($request->document_payload['service_rows']), 422, 'Prepare the document before routing it.');

        $this->transition($request, $actor, ['processing', 'ocd_returned'], 'pending_ocd_approval', 'routed_to_ocd', null, [
            'routed_to_ocd_at' => now(),
        ]);

        User::whereHas('roles', fn ($q) => $q->where('name', 'OCD'))->get()
            ->each(fn (User $ocd) => NotificationService::notifyUser(
                $ocd,
                'HR Document Request',
                $request->reference_no,
                'Ready for OCD approval and signature',
                route('approvals.inbox'),
            ));
    }

    public function approveByOcd(HrDocumentRequest $request, User $ocd, string $pin): void
    {
        abort_unless($request->status === 'pending_ocd_approval', 409, 'This request is no longer awaiting OCD approval.');
        $this->assertCanSign($ocd, $pin);

        DB::transaction(function () use ($request, $ocd) {
            $locked = HrDocumentRequest::lockForUpdate()->findOrFail($request->id);
            abort_unless($locked->status === 'pending_ocd_approval', 409, 'This request has already been acted upon.');

            $signaturePath = $this->snapshotSignature($ocd, $locked, 'ocd');
            $this->signatures->sign(
                signer: $ocd,
                signableType: HrDocumentRequest::class,
                signableId: $locked->id,
                documentTitle: "{$locked->reference_no} — {$locked->type->name}",
                contentToHash: $this->contentFingerprint($locked),
                metadata: ['stage' => 'ocd_approval', 'signature_path' => $signaturePath],
            );

            $locked->update([
                'status' => 'ready_for_release',
                'ocd_approver_id' => $ocd->id,
                'ocd_approved_at' => now(),
                'ready_at' => now(),
            ]);
            $this->recordEvent($locked, $ocd, 'ocd_approved', 'pending_ocd_approval', 'ready_for_release');
        });

        $this->notifyHr($request->fresh(), 'Approved and signed by OCD — ready for issuance');
    }

    public function returnByOcd(HrDocumentRequest $request, User $ocd, string $remarks): void
    {
        $this->transition($request, $ocd, ['pending_ocd_approval'], 'ocd_returned', 'ocd_returned', $remarks, [
            'internal_notes' => $remarks,
        ]);
        $this->notifyHr($request, 'Returned by OCD for revision', $remarks);
    }

    public function issue(HrDocumentRequest $request, User $issuer, ?string $pin): HrIssuedDocument
    {
        $request->loadMissing(['type', 'requester.employeeProfile', 'requester.pds.workExperience']);
        $allowed = $request->type->requires_ocd_approval ? ['ready_for_release'] : ['processing'];
        abort_unless(in_array($request->status, $allowed, true), 409, 'This request is not ready for issuance.');
        abort_if(empty($request->document_payload), 422, 'Prepare the document before issuing it.');
        if ($request->type->template_key === 'service_record') {
            abort_if(empty($request->document_payload['service_rows']), 422, 'The employee has no verified Service Record entries to issue.');
            abort_if(empty($request->document_payload['service_record_snapshot']), 422, 'The Service Record snapshot is missing. Restart document preparation.');
        }

        if (! $request->type->requires_ocd_approval) {
            $this->assertCanSign($issuer, (string) $pin);
        }

        $issued = DB::transaction(function () use ($request, $issuer) {
            $locked = HrDocumentRequest::with('type')->lockForUpdate()->findOrFail($request->id);
            abort_if($locked->issuedDocuments()->exists(), 409, 'This request has already been issued.');

            if (! $locked->type->requires_ocd_approval) {
                $signaturePath = $this->snapshotSignature($issuer, $locked, 'hr');
                $this->signatures->sign(
                    signer: $issuer,
                    signableType: HrDocumentRequest::class,
                    signableId: $locked->id,
                    documentTitle: "{$locked->reference_no} — {$locked->type->name}",
                    contentToHash: $this->contentFingerprint($locked),
                    metadata: ['stage' => 'hr_issuance', 'signature_path' => $signaturePath],
                );
            }

            $issued = HrIssuedDocument::create([
                'request_id' => $locked->id,
                'control_number' => $this->nextControlNumber(),
                'version' => 1,
                's3_path' => 'pending',
                'content_hash' => hash('sha256', $this->contentFingerprint($locked)),
                'qr_token' => (string) Str::uuid(),
                'issued_by' => $issuer->id,
                'issued_at' => now(),
            ]);

            $path = $this->pdfs->generate($locked->fresh(), $issued);
            $issued->update(['s3_path' => $path]);
            if ($locked->type->template_key === 'service_record') {
                $snapshot = $locked->document_payload['service_record_snapshot'] ?? null;
                abort_unless(is_array($snapshot), 422, 'The Service Record does not contain a frozen ledger snapshot.');
                $locked->loadMissing('requester');
                $this->serviceRecords->createVersion(
                    $locked->requester,
                    $snapshot,
                    $issuer,
                    $locked->id,
                    $issued->id,
                );
            }
            $oldStatus = $locked->status;
            $locked->update(['status' => 'issued', 'issued_at' => now()]);
            $this->recordEvent($locked, $issuer, 'issued', $oldStatus, 'issued', null, [
                'control_number' => $issued->control_number,
            ]);

            return $issued;
        });

        $this->notifyRequester($request, 'Document issued — secure download is available');

        return $issued;
    }

    public function defaultPayload(HrDocumentRequest $request): array
    {
        $request->loadMissing(['type', 'requester.employeeProfile', 'requester.pds.personalInfo']);
        $user = $request->requester;
        $profile = $user->employeeProfile;
        $name = $this->names->formal($user);
        $appointmentDate = $profile?->date_original_appointment?->format('F j, Y');
        $monthlyRate = null;

        if ($user->salary_grade) {
            $monthlyRate = SalarySchedule::current()
                ->where('salary_grade', $user->salary_grade)
                ->where('step', $user->salary_step ?? 1)
                ->value('monthly_rate');
        }

        $body = match ($request->type->template_key) {
            'employment_compensation' => "This is to certify that {$name} is currently employed by the Philippine Science High School – Caraga Region Campus as {$user->position}. The employee presently receives a monthly basic salary of PHP ".number_format((float) $monthlyRate, 2).'.',
            'leave_credits' => $this->leaveCreditsBody($user, $name),
            'last_salary' => "This is to certify that the last monthly basic salary received by {$name}, {$user->position}, is PHP ".number_format((float) $monthlyRate, 2).'.',
            'no_pending_case' => "This is to certify that, based on the records available to the Human Resource Office, {$name} has no pending administrative case as of the date of issuance of this certification.",
            'government_service' => "This is to certify that {$name} is employed by the Philippine Science High School – Caraga Region Campus as {$user->position}".($appointmentDate ? " since {$appointmentDate}" : '').'.',
            'custom' => "This is to certify that {$name}, {$user->position}, is an employee of the Philippine Science High School – Caraga Region Campus.",
            'service_record' => '',
            default => "This is to certify that {$name} is currently employed by the Philippine Science High School – Caraga Region Campus as {$user->position}".($appointmentDate ? " since {$appointmentDate}" : '').'.',
        };

        $serviceRecordSnapshot = $request->type->template_key === 'service_record'
            ? $this->serviceRecords->snapshot($user)
            : null;
        $rows = $serviceRecordSnapshot['entries'] ?? [];

        return [
            'employee_name' => $name,
            'position' => $user->position,
            'employee_no' => $profile?->employee_no ?? $user->employee_no,
            'appointment_status' => $profile?->appointment_status,
            'birth_date' => $serviceRecordSnapshot['birth_date'] ?? null,
            'birth_place' => $serviceRecordSnapshot['birth_place'] ?? null,
            'body' => $body,
            'service_rows' => $rows,
            'service_record_snapshot' => $serviceRecordSnapshot,
            'certification_clause' => $request->type->template_key === 'service_record'
                ? 'This is to certify that the foregoing record is true and correct according to the official records of this Office, each period being supported by appointment and other papers on file.'
                : 'This certification is issued upon the request of the above-named employee for the stated purpose.',
        ];
    }

    public function contentFingerprint(HrDocumentRequest $request): string
    {
        return json_encode([
            'request_id' => $request->id,
            'reference_no' => $request->reference_no,
            'requester_id' => $request->requester_id,
            'type_id' => $request->request_type_id,
            'purpose' => $request->purpose,
            'payload' => $request->document_payload,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function transition(HrDocumentRequest $request, User $actor, array $from, string $to, string $action, ?string $remarks, array $updates = []): void
    {
        DB::transaction(function () use ($request, $actor, $from, $to, $action, $remarks, $updates) {
            $locked = HrDocumentRequest::lockForUpdate()->findOrFail($request->id);
            abort_unless(in_array($locked->status, $from, true), 409, 'This request has already moved to another stage.');
            $old = $locked->status;
            $locked->update([...$updates, 'status' => $to]);
            $this->recordEvent($locked, $actor, $action, $old, $to, $remarks);
        });
        $request->refresh();
    }

    private function recordEvent(HrDocumentRequest $request, ?User $actor, string $action, ?string $from, string $to, ?string $remarks = null, array $metadata = []): void
    {
        HrDocumentRequestEvent::create([
            'request_id' => $request->id,
            'actor_id' => $actor?->id,
            'action' => $action,
            'from_status' => $from,
            'to_status' => $to,
            'remarks' => $remarks,
            'metadata' => $metadata ?: null,
        ]);
    }

    private function storeAttachment(HrDocumentRequest $request, User $user, array $attachment): HrDocumentRequestAttachment
    {
        preg_match('/^data:([^;]+);base64,(.+)$/s', $attachment['data_uri'], $matches);
        $bytes = base64_decode($matches[2] ?? '', true);
        if ($bytes === false) {
            throw ValidationException::withMessages(['attachments' => 'An attachment could not be decoded.']);
        }

        $mime = $matches[1];
        $extension = match ($mime) {
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            default => throw ValidationException::withMessages(['attachments' => 'Only PDF, JPG, and PNG attachments are allowed.']),
        };
        if (strlen($bytes) > 10 * 1024 * 1024) {
            throw ValidationException::withMessages(['attachments' => 'Each attachment must not exceed 10 MB.']);
        }

        $detectedMime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        if ($detectedMime !== $mime) {
            throw ValidationException::withMessages(['attachments' => 'An attachment does not match its declared file type.']);
        }

        $path = "hr/document-requests/{$request->id}/attachments/".Str::uuid().".{$extension}";
        Storage::disk('s3')->put($path, $bytes, ['ContentType' => $mime]);

        $safeFilename = basename(str_replace('\\', '/', preg_replace('/[\x00-\x1F\x7F]/u', '', $attachment['name'])));

        return HrDocumentRequestAttachment::create([
            'request_id' => $request->id,
            'uploaded_by' => $user->id,
            'original_filename' => $safeFilename,
            'mime_type' => $mime,
            'file_size' => strlen($bytes),
            's3_path' => $path,
        ]);
    }

    private function assertCanSign(User $user, string $pin): void
    {
        if (! $user->electronic_signature) {
            throw ValidationException::withMessages(['signature' => 'Upload your electronic signature before signing.']);
        }
        if ($user->signature_pin && (! $pin || ! $this->signatures->verifyPin($user, $pin))) {
            throw ValidationException::withMessages(['pin' => 'Invalid signing PIN.']);
        }
    }

    private function snapshotSignature(User $user, HrDocumentRequest $request, string $stage): string
    {
        $uri = $this->signatures->getSignatureDataUri($user);
        preg_match('/^data:image\/[^;]+;base64,(.+)$/s', (string) $uri, $matches);
        $bytes = base64_decode($matches[1] ?? '', true);
        if ($bytes === false || $bytes === '') {
            throw ValidationException::withMessages(['signature' => 'The electronic signature image could not be loaded.']);
        }

        $path = "signatures/documents/hr-document-requests/request_{$request->id}_{$stage}.png";
        Storage::disk('s3')->put($path, $bytes, ['ContentType' => 'image/png']);

        return $path;
    }

    private function leaveCreditsBody(User $user, string $name): string
    {
        $balances = LeaveCredit::where('user_id', $user->id)
            ->where('year', now()->year)
            ->with('leaveType:id,name,code')
            ->get()
            ->map(fn ($credit) => ($credit->leaveType?->name ?? 'Leave').': '.number_format((float) $credit->balance, 3).' day(s)')
            ->implode('; ');

        return "This is to certify that {$name} has the following recorded leave credit balances as of ".now()->format('F j, Y').': '.($balances ?: 'No leave credit balance is currently recorded.').'.';
    }

    private function nextReferenceNumber(): string
    {
        return $this->nextNumber('HRDR', 'hr_document_requests', 'reference_no');
    }

    private function nextControlNumber(): string
    {
        return $this->nextNumber('HRDOC', 'hr_issued_documents', 'control_number');
    }

    private function nextNumber(string $prefix, string $table, string $column): string
    {
        $year = now()->year;
        $max = DB::table($table)
            ->where($column, 'like', "{$prefix}-{$year}-%")
            ->lockForUpdate()
            ->selectRaw("MAX(CAST(SUBSTRING_INDEX({$column}, '-', -1) AS UNSIGNED)) as seq")
            ->value('seq');

        return sprintf('%s-%d-%04d', $prefix, $year, ($max ?? 0) + 1);
    }

    private function notifyRequester(HrDocumentRequest $request, string $status, ?string $remarks = null): void
    {
        $request->loadMissing('requester');
        NotificationService::notifyUser(
            $request->requester,
            'HR Document Request',
            $request->reference_no,
            $status,
            route('hr.document-requests.show', $request),
            $remarks,
        );
    }

    private function notifyHr(HrDocumentRequest $request, string $status, ?string $remarks = null): void
    {
        User::whereHas('roles', fn ($q) => $q->where('name', 'HR'))->get()
            ->each(fn (User $hr) => NotificationService::notifyUser(
                $hr,
                'HR Document Request',
                $request->reference_no,
                $status,
                route('hr.document-requests.manage'),
                $remarks,
            ));
    }
}
