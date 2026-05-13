<?php

namespace App\Http\Controllers;

use App\Services\DigitalSignatureService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class UserSignatureController extends Controller
{
    public function __construct(private DigitalSignatureService $svc) {}

    /**
     * Show the signature setup page.
     */
    public function show(Request $request)
    {
        $user = $request->user();

        return Inertia::render('Profile/Signature', [
            'hasSignature'  => ! empty($user->electronic_signature),
            'hasPin'        => ! empty($user->signature_pin),
            'signatureUri'  => $this->svc->getSignatureDataUri($user),
        ]);
    }

    /**
     * Save a base64 signature image to S3.
     */
    public function saveSignature(Request $request)
    {
        $request->validate([
            'signature_base64' => ['required', 'string', 'starts_with:data:image/png;base64,'],
        ]);

        $this->svc->saveSignatureImage($request->user(), $request->signature_base64);

        return back()->with('success', 'Signature saved.');
    }

    /**
     * Set or update the signature PIN.
     */
    public function setPin(Request $request)
    {
        $request->validate([
            'pin'     => ['required', 'digits:6', 'confirmed'],
            'pin_confirmation' => ['required'],
        ]);

        $this->svc->setPin($request->user(), $request->pin);

        return back()->with('success', 'Signature PIN set.');
    }

    /**
     * Verify that the submitted PIN matches (used by signing flows via axios).
     */
    public function verifyPin(Request $request)
    {
        $request->validate(['pin' => ['required', 'digits:6']]);

        $valid = $this->svc->verifyPin($request->user(), $request->pin);

        return response()->json(['valid' => $valid]);
    }
}
