<?php

namespace App\Http\Controllers;

use App\Models\DigitalSignature;
use App\Models\FacilityRequest;
use App\Models\HR\LeaveApplication;
use App\Models\Issuance;
use App\Models\ITJobRequest;
use App\Models\Division;
use App\Models\MessengerialRequest;
use App\Models\Office;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Models\VehicleRequest;
use App\Models\WorkRequest;
use App\Models\Administration\CoaCertificate;
use App\Services\CertificateOfAppearanceService;
use App\Services\DigitalSignatureService;
use App\Services\IssuanceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class DocumentVerificationController extends Controller
{
    public function __construct(
        private DigitalSignatureService        $svc,
        private IssuanceService                $issuanceSvc,
        private CertificateOfAppearanceService $coaSvc,
    ) {}

    private const STAGE_LABELS = [
        'submission'  => 'Submission',
        'dc_approval' => 'Division Chief Approval',
        'ocd_approval'=> 'OCD Approval',
        'mis_acted'   => 'MIS Action',
        'completion'  => 'Completion',
    ];

    /**
     * Document-level ITJR verification page — shows all signers for one ITJR.
     * Public, no authentication required.
     */
    public function showItjr(string $itjrNo)
    {
        $jobRequest = ITJobRequest::where('itjr_no', $itjrNo)
            ->with('user:id,name,position')
            ->firstOrFail();

        $signatures = DigitalSignature::where('signable_type', ITJobRequest::class)
            ->where('signable_id', $jobRequest->id)
            ->with('signer:id,name,position,badge_id,electronic_signature')
            ->orderBy('signed_at')
            ->get();

        $entries = $signatures->map(function (DigitalSignature $sig) {
            $valid  = $this->svc->verify($sig->verification_token) !== null;
            $stage  = $sig->metadata['stage'] ?? 'unknown';
            $sigUri = $sig->signer ? $this->svc->getSignatureDataUri($sig->signer) : null;

            return [
                'stage'              => self::STAGE_LABELS[$stage] ?? ucfirst($stage),
                'signer'             => $sig->signer?->name ?? '—',
                'position'           => $sig->signer?->position ?? '—',
                'badge_id'           => $sig->signer?->badge_id ?? null,
                'signed_at'          => $sig->signed_at->format('F d, Y \a\t h:i A'),
                'valid'              => $valid,
                'signature_uri'      => $sigUri,
                'verification_token' => $sig->verification_token,
                'metadata'           => $sig->metadata ?? [],
            ];
        });

        return view('it-job-requests.verify', [
            'jobRequest' => $jobRequest,
            'entries'    => $entries,
        ]);
    }

    /**
     * Employee Digital ID verification — public, no authentication.
     * Resolves the opaque QR token to LIVE employment status. Shows minimal
     * PII only: photo, name, position, unit, employee number, status.
     */
    public function showEmployee(string $token)
    {
        $user = User::where('id_verification_token', $token)->first();

        if (! $user) {
            return Inertia::render('Verify/Employee', [
                'valid'    => false,
                'employee' => null,
                'photoUri' => null,
            ]);
        }

        $division = $user->division_id ? Division::find($user->division_id) : null;
        $office   = $user->office_id ? Office::find($user->office_id) : null;

        return Inertia::render('Verify/Employee', [
            'valid'    => true,
            'employee' => [
                'name'        => $user->name,
                'position'    => $user->position,
                'employee_no' => $user->employee_no,
                'division'    => $division?->division_name,
                'office'      => $office?->name,
                'is_active'   => $user->status === 'active',
            ],
            'photoUri'    => $this->photoDataUri($user->profile_picture),
            'verified_at' => now()->toIso8601String(),
        ]);
    }

    /** Inline a private-S3 profile photo as a data URI (verify page is public — no /media auth). */
    private function photoDataUri(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        try {
            if (Storage::disk('s3')->exists($path)) {
                $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                $mime = match ($ext) {
                    'png'         => 'image/png',
                    'webp'        => 'image/webp',
                    'gif'         => 'image/gif',
                    default       => 'image/jpeg',
                };

                return "data:{$mime};base64," . base64_encode(Storage::disk('s3')->get($path));
            }
        } catch (\Exception) {
        }

        return null;
    }

    /**
     * Public verification page — no authentication required.
     */
    public function show(string $token)
    {
        $record = $this->svc->verify($token);

        if (! $record) {
            return Inertia::render('Verify/Show', [
                'valid'   => false,
                'record'  => null,
                'signerSignatureUri' => null,
            ]);
        }

        $signerSignatureUri = $this->svc->getSignatureDataUri($record->signer);

        return Inertia::render('Verify/Show', [
            'valid'  => true,
            'record' => [
                'document_title'     => $record->document_title,
                'verification_token' => $record->verification_token,
                'signed_at'          => $record->signed_at->toIso8601String(),
                'metadata'           => $record->metadata,
                'signer' => [
                    'name'     => $record->signer->name,
                    'position' => $record->signer->position,
                    'badge_id' => $record->signer->badge_id,
                ],
            ],
            'signerSignatureUri' => $signerSignatureUri,
        ]);
    }

    /**
     * Public QR verification page for issuances — no authentication required.
     */
    public function showIssuance(string $token)
    {
        $issuance = Issuance::where('qr_token', $token)->with(['creator:id,name,position', 'signature.signer:id,name,position,electronic_signature'])->first();

        if (! $issuance || $issuance->status !== 'released') {
            return view('issuances.verify', [
                'valid' => false, 'issuance' => null, 'tampered' => false,
                'reason' => null, 'sig' => null, 'sigUri' => null, 'documentUrl' => null,
            ]);
        }

        $sig    = $issuance->signature;
        $sigUri = $sig?->signer ? $this->svc->getSignatureDataUri($sig->signer) : null;

        if ($sig) {
            // Cryptographic check: recompute the content hash from the LIVE row and
            // compare it against the signed, immutable document_hash on the
            // digital_signatures record (frozen at release time). Forging a match
            // requires the KMS/HMAC signing key — not just write access to `issuances`.
            $liveHash        = $this->issuanceSvc->computeHash($issuance);
            $expectedDocHash = hash('sha256', $liveHash);
            $contentMatches  = hash_equals($expectedDocHash, $sig->document_hash);
            $signatureValid  = $this->svc->verify($sig->verification_token) !== null;

            if (! $signatureValid) {
                $tampered = true;
                $reason   = 'signature';
            } elseif (! $contentMatches) {
                $tampered = true;
                $reason   = 'content';
            } else {
                $tampered = false;
                $reason   = null;
            }
        } elseif ($issuance->content_hash) {
            // Legacy fallback for issuances released before signing was wired up:
            // self-referential hash check only.
            $computedHash = $this->issuanceSvc->computeHash($issuance);
            $tampered     = $computedHash !== $issuance->content_hash;
            $reason       = $tampered ? 'legacy' : 'legacy_ok';
        } else {
            $tampered = false;
            $reason   = 'no_signature';
        }

        return view('issuances.verify', [
            'valid'       => true,
            'tampered'    => $tampered,
            'reason'      => $reason,
            'issuance'    => $issuance,
            'sig'         => $sig,
            'sigUri'      => $sigUri,
            'documentUrl' => route('issuances.verify.document', $token),
        ]);
    }

    /**
     * Public PDF proxy for the system's canonical copy of a released issuance —
     * lets the person scanning the QR visually compare it against the printed
     * document in their hand. No authentication required.
     */
    public function issuanceDocument(string $token)
    {
        $issuance = Issuance::where('qr_token', $token)->first();

        abort_if(! $issuance || $issuance->status !== 'released', 404);

        $pdfPath = 'issuances/' . $issuance->control_number . '.pdf';
        if (! Storage::disk('s3')->exists($pdfPath)) {
            $pdfPath = $this->issuanceSvc->generatePdf($issuance);
        }

        $content = Storage::disk('s3')->get($pdfPath);

        return response($content, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $issuance->control_number . '.pdf"',
            'Cache-Control'       => 'private, max-age=300',
        ]);
    }

    /**
     * Public QR verification page for Certificates of Appearance — no auth required.
     */
    public function showCoa(string $token)
    {
        $certificate = CoaCertificate::where('qr_token', $token)
            ->with(['visit', 'signature.signer:id,name,position'])
            ->first();

        if (! $certificate || $certificate->visit?->status !== 'issued') {
            return view('coa.verify', [
                'valid' => false, 'certificate' => null, 'tampered' => false,
                'reason' => null, 'sig' => null, 'documentUrl' => null,
            ]);
        }

        $sig = $certificate->signature;

        if ($sig) {
            // Recompute the content hash from the LIVE row and compare against the
            // signed, immutable document_hash frozen at issue time (see showIssuance).
            $liveHash        = $this->coaSvc->computeHash($certificate);
            $expectedDocHash = hash('sha256', $liveHash);
            $contentMatches  = hash_equals($expectedDocHash, $sig->document_hash);
            $signatureValid  = $this->svc->verify($sig->verification_token) !== null;

            if (! $signatureValid) {
                $tampered = true;
                $reason   = 'signature';
            } elseif (! $contentMatches) {
                $tampered = true;
                $reason   = 'content';
            } else {
                $tampered = false;
                $reason   = null;
            }
        } else {
            // Every certificate is signed at issue time — a missing signature is itself suspect
            $tampered = true;
            $reason   = 'signature';
        }

        return view('coa.verify', [
            'valid'       => true,
            'tampered'    => $tampered,
            'reason'      => $reason,
            'certificate' => $certificate,
            'sig'         => $sig,
            'documentUrl' => route('coa.verify.document', $token),
        ]);
    }

    /**
     * Public PDF proxy for the system's canonical copy of an issued certificate.
     * No authentication required.
     */
    public function coaDocument(string $token)
    {
        $certificate = CoaCertificate::where('qr_token', $token)->with('visit')->first();

        abort_if(! $certificate || $certificate->visit?->status !== 'issued', 404);

        $pdfPath = 'coa/' . $certificate->control_number . '.pdf';
        if (! Storage::disk('s3')->exists($pdfPath)) {
            $pdfPath = $this->coaSvc->generatePdf($certificate);
        }

        return response(Storage::disk('s3')->get($pdfPath), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $certificate->control_number . '.pdf"',
            'Cache-Control'       => 'private, max-age=300',
        ]);
    }

    /**
     * Generic document-level verification page for all non-ITJR modules.
     * Public, no authentication required.
     */
    public function showDocument(string $type, int $id)
    {
        $config = self::DOC_CONFIGS[$type] ?? null;
        abort_if(! $config, 404);

        // Resolve document info (title + requester name)
        $documentTitle  = $config['label'] . ' #' . $id;
        $documentMeta   = null;

        if ($type === 'gatepass') {
            $row = DB::table('gatepass')->where('id', $id)->first();
            abort_if(! $row, 404);
            $documentMeta = $row->purpose ?? null;
        } else {
            $model = $config['class'];
            $doc   = $model::find($id);
            abort_if(! $doc, 404);
            if ($config['title_field']) {
                $documentMeta = $doc->{$config['title_field']} ?? null;
            }
        }

        // Load all digital signatures for this document
        $signatures = DigitalSignature::where('signable_type', $config['signable_type'])
            ->where('signable_id', $id)
            ->with('signer:id,name,position,badge_id,electronic_signature')
            ->orderBy('signed_at')
            ->get();

        $entries = $signatures->map(function (DigitalSignature $sig) {
            $valid  = $this->svc->verify($sig->verification_token) !== null;
            $stage  = $sig->metadata['stage'] ?? 'unknown';
            $sigUri = $sig->signer ? $this->svc->getSignatureDataUri($sig->signer) : null;

            return [
                'stage'              => ucwords(str_replace('_', ' ', $stage)),
                'signer'             => $sig->signer?->name ?? '—',
                'position'           => $sig->signer?->position ?? '—',
                'badge_id'           => $sig->signer?->badge_id ?? null,
                'signed_at'          => $sig->signed_at->format('F d, Y \a\t h:i A'),
                'valid'              => $valid,
                'signature_uri'      => $sigUri,
                'verification_token' => $sig->verification_token,
                'metadata'           => $sig->metadata ?? [],
            ];
        });

        return view('documents.verify', [
            'documentLabel' => $config['label'],
            'documentTitle' => $documentTitle,
            'documentMeta'  => $documentMeta,
            'entries'       => $entries,
        ]);
    }

    private const DOC_CONFIGS = [
        'facility'     => ['class' => FacilityRequest::class,    'signable_type' => FacilityRequest::class,    'label' => 'Facility Request',     'title_field' => 'purpose'],
        'vehicle'      => ['class' => VehicleRequest::class,     'signable_type' => VehicleRequest::class,     'label' => 'Vehicle Request',      'title_field' => 'purpose'],
        'work-request' => ['class' => WorkRequest::class,        'signable_type' => WorkRequest::class,        'label' => 'Work Request',         'title_field' => 'description'],
        'service'      => ['class' => ServiceRequest::class,     'signable_type' => ServiceRequest::class,     'label' => 'Service Request',      'title_field' => 'description'],
        'gatepass'     => ['class' => null,                      'signable_type' => 'App\Models\GatePass',     'label' => 'Gate Pass',            'title_field' => null],
        'leave'        => ['class' => LeaveApplication::class,   'signable_type' => LeaveApplication::class,   'label' => 'Leave Application',    'title_field' => null],
        'messengerial' => ['class' => MessengerialRequest::class, 'signable_type' => MessengerialRequest::class,'label' => 'Messengerial Request', 'title_field' => 'purpose'],
    ];
}
