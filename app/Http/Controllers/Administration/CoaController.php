<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use App\Http\Traits\SignsDocuments;
use App\Jobs\ProcessCoaIssue;
use App\Models\Administration\CoaCertificate;
use App\Models\Administration\CoaVisit;
use App\Services\CertificateOfAppearanceService;
use App\Services\DigitalSignatureService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class CoaController extends Controller
{
    use SignsDocuments;

    public function __construct(
        protected DigitalSignatureService        $sigService,
        private CertificateOfAppearanceService   $coaSvc,
    ) {}

    public function index(): Response
    {
        $visits = CoaVisit::with(['certificates', 'createdBy:id,name'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($v) => [
                'id'             => $v->id,
                'purpose'        => $v->purpose,
                'office_visited' => $v->office_visited,
                'date_from'      => $v->date_from?->format('Y-m-d'),
                'date_to'        => $v->date_to?->format('Y-m-d'),
                'remarks'        => $v->remarks,
                'status'         => $v->status,
                'issued_at'      => $v->issued_at?->toIso8601String(),
                'created_by'     => $v->createdBy?->name,
                'certificates'   => $v->certificates->map(fn ($c) => [
                    'id'             => $c->id,
                    'visitor_name'   => $c->visitor_name,
                    'organization'   => $c->organization,
                    'position'       => $c->position,
                    'email'          => $c->email,
                    'control_number' => $c->control_number,
                    'email_status'   => $c->email_status,
                    'emailed_at'     => $c->emailed_at?->toIso8601String(),
                ])->all(),
            ]);

        return Inertia::render('Administration/COA/Index', [
            'visits'      => $visits,
            'requiresPin' => ! empty(Auth::user()->signature_pin),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateVisit($request);

        DB::transaction(function () use ($data) {
            $visit = CoaVisit::create([
                ...$this->visitAttributes($data),
                'created_by' => Auth::id(),
            ]);

            $this->syncCertificates($visit, $data['visitors']);
        });

        return back()->with('success', 'Visit record created. Review it, then issue the certificates.');
    }

    public function update(Request $request, CoaVisit $visit): RedirectResponse
    {
        abort_if(! $visit->isEditable(), 422, 'Issued visits can no longer be edited.');

        $data = $this->validateVisit($request);

        DB::transaction(function () use ($visit, $data) {
            $visit->update($this->visitAttributes($data));
            $this->syncCertificates($visit, $data['visitors']);
        });

        return back()->with('success', 'Visit record updated.');
    }

    public function destroy(CoaVisit $visit): RedirectResponse
    {
        abort_if(! $visit->isEditable(), 422, 'Issued visits cannot be deleted.');

        $visit->delete(); // certificates cascade

        return back()->with('success', 'Visit record deleted.');
    }

    /**
     * Issue all certificates for a draft visit: assign control numbers,
     * freeze content hashes, PIN-sign each certificate, then queue PDF
     * generation + visitor emails.
     */
    public function issue(Request $request, CoaVisit $visit): RedirectResponse
    {
        abort_if(! $visit->isEditable(), 422, 'This visit has already been issued.');

        $visit->load('certificates');

        if ($visit->certificates->isEmpty()) {
            return back()->withErrors(['visitors' => 'Add at least one visitor before issuing.']);
        }

        // Verify the PIN once up front so we fail before any state changes
        $signer = $request->user();
        if (! empty($signer->signature_pin)) {
            $pin = $request->input('pin');
            if (! $pin || ! $this->sigService->verifyPin($signer, $pin)) {
                return back()->withErrors(['pin' => 'Invalid signing PIN.']);
            }
        }

        DB::transaction(function () use ($request, $visit) {
            $visit->update(['status' => 'issued', 'issued_at' => now()]);

            foreach ($visit->certificates as $cert) {
                $cert->update(['control_number' => $this->coaSvc->nextControlNumber()]);
                $cert->refresh()->setRelation('visit', $visit);
                $cert->update(['content_hash' => $this->coaSvc->computeHash($cert)]);
                $signaturePath = $this->coaSvc->snapshotSignature($request->user(), $cert);

                $signed = $this->performSign(
                    $request,
                    CoaCertificate::class,
                    $cert->id,
                    'issuance',
                    "Certificate of Appearance {$cert->control_number} — {$cert->visitor_name}",
                    $this->coaSvc->computeHash($cert),
                    ['signature_path' => $signaturePath],
                );

                if (! $signed) {
                    throw new \RuntimeException("Signing failed for certificate {$cert->control_number}.");
                }
            }
        });

        ProcessCoaIssue::dispatch($visit->id);

        return back()->with('success', 'Certificates issued. PDFs are being generated and emailed to the visitors.');
    }

    /** Re-send one certificate email (bounced or mistyped address fixed in draft is gone — resend as-is). */
    public function resend(CoaCertificate $certificate): RedirectResponse
    {
        abort_if($certificate->visit->status !== 'issued', 422, 'Certificate has not been issued yet.');

        try {
            $path = $this->coaSvc->pdfPath($certificate);
            if (! Storage::disk('s3')->exists($path)) {
                $this->coaSvc->generatePdf($certificate);
            }

            \Illuminate\Support\Facades\Mail::to($certificate->email)
                ->send(new \App\Mail\CoaIssuedMail($certificate));
            $certificate->update(['email_status' => 'sent', 'emailed_at' => now()]);

            return back()->with('success', "Certificate re-sent to {$certificate->email}.");
        } catch (\Throwable $e) {
            $certificate->update(['email_status' => 'failed']);
            logger()->warning('COA resend failed', ['certificate_id' => $certificate->id, 'error' => $e->getMessage()]);

            return back()->withErrors(['email' => 'Sending failed: ' . $e->getMessage()]);
        }
    }

    /** Staff-side PDF download (auth + permission via route middleware). */
    public function pdf(CoaCertificate $certificate)
    {
        abort_if($certificate->visit->status !== 'issued', 404);

        $path = $this->coaSvc->pdfPath($certificate);
        if (! Storage::disk('s3')->exists($path)) {
            $path = $this->coaSvc->generatePdf($certificate);
        }

        return response(Storage::disk('s3')->get($path), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $certificate->control_number . '.pdf"',
        ]);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function validateVisit(Request $request): array
    {
        return $request->validate([
            'purpose'                 => 'required|string|max:500',
            'office_visited'          => 'nullable|string|max:255',
            'date_from'               => 'required|date',
            'date_to'                 => 'nullable|date|after_or_equal:date_from',
            'remarks'                 => 'nullable|string|max:1000',
            'visitors'                => 'required|array|min:1',
            'visitors.*.visitor_name' => 'required|string|max:255',
            'visitors.*.organization' => 'nullable|string|max:255',
            'visitors.*.position'     => 'nullable|string|max:255',
            'visitors.*.email'        => 'required|email|max:255',
        ], [
            'visitors.required'                => 'Add at least one visitor.',
            'visitors.*.visitor_name.required' => 'Each visitor needs a name.',
            'visitors.*.email.required'        => 'Each visitor needs an email address.',
            'visitors.*.email.email'           => 'One of the visitor email addresses is invalid.',
        ]);
    }

    private function visitAttributes(array $data): array
    {
        return [
            'purpose'        => $data['purpose'],
            'office_visited' => $data['office_visited'] ?? null,
            'date_from'      => $data['date_from'],
            'date_to'        => $data['date_to'] ?? null,
            'remarks'        => $data['remarks'] ?? null,
        ];
    }

    /** Replace all certificate rows from the validated payload (draft visits only). */
    private function syncCertificates(CoaVisit $visit, array $visitors): void
    {
        $visit->certificates()->delete();

        foreach ($visitors as $v) {
            $visit->certificates()->create([
                'visitor_name' => $v['visitor_name'],
                'organization' => $v['organization'] ?? null,
                'position'     => $v['position'] ?? null,
                'email'        => $v['email'],
            ]);
        }
    }
}
