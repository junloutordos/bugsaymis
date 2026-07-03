<?php

namespace App\Traits;

use App\Models\User;
use App\Services\DigitalSignatureService;
use Illuminate\Validation\ValidationException;

/**
 * Shared PIN / digital-signature helper for laboratory approval steps.
 * Verifies the signer's PIN then records a tamper-evident DigitalSignature.
 */
trait SignsLabDocuments
{
    /**
     * Verify the user's signing PIN, throwing a 422 on mismatch.
     */
    protected function verifyLabPin(User $user, string $pin): void
    {
        if (empty($user->signature_pin)) {
            throw ValidationException::withMessages([
                'pin' => 'You have not set a signing PIN yet. Set it in your profile first.',
            ]);
        }

        if (! app(DigitalSignatureService::class)->verifyPin($user, $pin)) {
            throw ValidationException::withMessages(['pin' => 'Incorrect signing PIN.']);
        }
    }

    /**
     * Record a digital signature for a lab document stage.
     */
    protected function signLabDocument(User $signer, $model, string $stage, string $title): void
    {
        app(DigitalSignatureService::class)->sign(
            $signer,
            get_class($model),
            $model->id,
            $title,
            $model->control_no . '|' . $stage . '|' . $model->id,
            ['stage' => $stage, 'control_no' => $model->control_no],
        );
    }
}
