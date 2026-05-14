<?php

namespace App\Http\Controllers;

use App\Models\DigitalSignature;
use App\Models\ITJobRequest;
use App\Services\DigitalSignatureService;
use Inertia\Inertia;

class DocumentVerificationController extends Controller
{
    public function __construct(private DigitalSignatureService $svc) {}

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
}
