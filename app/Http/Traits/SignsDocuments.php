<?php

namespace App\Http\Traits;

use App\Models\DigitalSignature;
use App\Services\DigitalSignatureService;
use Illuminate\Http\Request;

trait SignsDocuments
{
    /**
     * Attempt to create a DigitalSignature record for the given signable.
     *
     * - If the signer has a PIN, the request must supply a matching `pin` field.
     * - If no PIN is set, the signature is recorded automatically.
     * - Returns true if a DigitalSignature record was created, false otherwise
     *   (missing/incorrect PIN, or signing failed) — callers that gate an
     *   approval on the signature should check this before mutating state.
     *
     * Expects $this->sigService to be a DigitalSignatureService instance.
     */
    private function performSign(
        Request $request,
        string  $signableType,
        int     $signableId,
        string  $stage,
        string  $documentTitle,
        string  $contentHash,
        array   $extraMeta = []
    ): bool {
        $signer = $request->user();

        if (! empty($signer->signature_pin)) {
            $pin = $request->input('pin');
            if (! $pin || ! $this->sigService->verifyPin($signer, $pin)) {
                return false;
            }
        }

        try {
            $this->sigService->sign(
                signer:        $signer,
                signableType:  $signableType,
                signableId:    $signableId,
                documentTitle: $documentTitle,
                contentToHash: $contentHash,
                metadata:      array_merge(['stage' => $stage], $extraMeta),
            );

            return true;
        } catch (\Throwable $e) {
            logger()->error('Digital sign failed', [
                'signable_type' => $signableType,
                'signable_id'   => $signableId,
                'stage'         => $stage,
                'error'         => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Load all DigitalSignature records for a signable and resolve each signer's
     * signature image as a base64 data URI. Returns an array keyed by stage:
     *
     *   $sigs['dc_approval'] = [
     *     'sig'       => DigitalSignature,
     *     'uri'       => 'data:image/png;base64,...' | null,
     *     'name'      => 'Juan dela Cruz',
     *     'signed_at' => Carbon,
     *   ]
     */
    private function loadSigsForPrint(string $signableType, int $signableId): array
    {
        $records = DigitalSignature::where('signable_type', $signableType)
            ->where('signable_id', $signableId)
            ->with('signer')
            ->get();

        $result = [];
        foreach ($records as $sig) {
            $stage = $sig->metadata['stage'] ?? '';
            if (! $stage) {
                continue;
            }
            $result[$stage] = [
                'sig'       => $sig,
                'uri'       => $sig->signer ? $this->sigService->getSignatureDataUri($sig->signer) : null,
                'name'      => $sig->signer?->name,
                'signed_at' => $sig->signed_at?->toIso8601String(),
            ];
        }

        return $result;
    }
}
