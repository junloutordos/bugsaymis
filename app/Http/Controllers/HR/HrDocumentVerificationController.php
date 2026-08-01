<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\HrIssuedDocument;
use Illuminate\Support\Facades\Storage;

class HrDocumentVerificationController extends Controller
{
    public function show(string $token)
    {
        $issued = HrIssuedDocument::with(['request.type', 'request.requester:id,name,position', 'issuer:id,name'])
            ->where('qr_token', $token)->first();

        return view('hr.document-requests.verify', [
            'valid' => (bool) $issued,
            'issued' => $issued,
            'fileExists' => $issued ? Storage::disk('s3')->exists($issued->s3_path) : false,
        ]);
    }
}
