<?php

namespace App\Http\Controllers;

use App\Http\Traits\SignsDocuments;
use App\Jobs\ProcessIssuanceRelease;
use App\Mail\IssuanceReleasedMail;
use App\Models\Issuance;
use App\Models\IssuanceRecipient;
use App\Models\Office;
use App\Models\User;
use App\Services\DigitalSignatureService;
use App\Services\IssuanceService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class IssuanceController extends Controller
{
    use SignsDocuments;

    public function __construct(
        private IssuanceService        $svc,
        private DigitalSignatureService $sigService,
    ) {}

    // ── Index ─────────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $user    = $request->user();
        $isAdmin = $user->hasAnyRole(['Administrator', 'OCD']);

        $query = Issuance::with(['creator:id,name,position'])
            ->withCount([
                'recipients',
                'recipients as acknowledged_count' => fn($q) => $q->whereNotNull('acknowledged_at'),
            ]);

        if (! $isAdmin) {
            // Staff: only see released issuances addressed to them
            $query->where('status', 'released')
                ->whereHas('recipients', fn($q) => $q->where('user_id', $user->id));
        }

        $issuances = $query->latest()->get()->map(fn($i) => [
            'id'               => $i->id,
            'type'             => $i->type,
            'type_label'       => $i->type_label,
            'control_number'   => $i->control_number,
            'title'            => $i->title,
            'status'           => $i->status,
            'recipient_type'   => $i->recipient_type,
            'recipients_count' => $i->recipients_count,
            'acknowledged_count' => $i->acknowledged_count,
            'released_at'      => $i->released_at?->toISOString(),
            'created_at'       => $i->created_at?->toISOString(),
            'creator'          => $i->creator?->only('id', 'name', 'position'),
            'my_acknowledged_at' => $isAdmin ? null : $i->recipients
                ->where('user_id', $user->id)->first()?->acknowledged_at?->toISOString(),
        ]);

        return Inertia::render('Issuances/Index', [
            'issuances'  => $issuances,
            'isAdmin'    => $isAdmin,
            'typeLabels' => Issuance::$typeLabels,
        ]);
    }

    // ── Create ────────────────────────────────────────────────────────────────

    public function create()
    {
        return Inertia::render('Issuances/Create', [
            'typeLabels' => Issuance::$typeLabels,
            'offices'    => Office::orderBy('name')->get(['id', 'name']),
            'users'      => User::where('status', '<>', 'inactive')
                ->orderBy('name')->get(['id', 'name', 'office_id', 'position']),
            'hasPin'     => ! empty(auth()->user()->signature_pin),
            'signatureUri' => $this->sigService->getSignatureDataUri(auth()->user()),
        ]);
    }

    // ── Store (save draft) ────────────────────────────────────────────────────

    public function store(Request $request)
    {
        $validated = $request->validate([
            'type'               => 'required|in:SO,TO,MEMO,OO,AO,CIRC,NOTICE',
            'title'              => 'required|string|max:500',
            'content'            => 'nullable|string',
            'scan_base64'        => 'nullable|string',
            'scan_filename'      => 'nullable|string|max:255',
            'scan_mime'          => 'nullable|string',
            'recipient_type'     => 'required|in:all,office,individual',
            'office_ids'         => 'nullable|array',
            'office_ids.*'       => 'exists:offices,id',
            'user_ids'           => 'nullable|array',
            'user_ids.*'         => 'exists:users,id',
            'should_release'     => 'nullable|boolean',
            'pin'                => 'nullable|string',
        ]);

        $year = now()->year;

        $issuance = DB::transaction(function () use ($validated, $year, $request) {
            [$controlNumber, $seriesNo] = $this->svc->nextControlNumber($validated['type'], $year);

            $attachmentPath = null;
            $attachmentFilename = null;
            $attachmentMime = null;

            if (! empty($validated['scan_base64'])) {
                $raw  = base64_decode(preg_replace('/^data:[^;]+;base64,/', '', $validated['scan_base64']));
                $ext  = str_contains($validated['scan_mime'] ?? '', 'pdf') ? 'pdf' : 'jpg';
                $attachmentPath     = 'issuances/scans/' . $controlNumber . '.' . $ext;
                $attachmentFilename = $validated['scan_filename'] ?? ($controlNumber . '.' . $ext);
                $attachmentMime     = $validated['scan_mime'] ?? 'application/pdf';
                Storage::disk('s3')->put($attachmentPath, $raw);
            }

            return Issuance::create([
                'type'               => $validated['type'],
                'control_number'     => $controlNumber,
                'series_no'          => $seriesNo,
                'year'               => $year,
                'title'              => $validated['title'],
                'content'            => $validated['content'] ?? null,
                'attachment_path'    => $attachmentPath,
                'attachment_filename'=> $attachmentFilename,
                'attachment_mime'    => $attachmentMime,
                'recipient_type'     => $validated['recipient_type'],
                'status'             => 'draft',
                'created_by'         => $request->user()->id,
            ]);
        });

        // If Sign & Release was requested, do it in the same request
        if ($validated['should_release'] ?? false) {
            $this->doRelease($request, $issuance, $validated);
            return redirect()->route('issuances.show', $issuance->id)
                ->with('success', "{$issuance->control_number} signed and released.");
        }

        return redirect()->route('issuances.show', $issuance->id)
            ->with('success', "Draft {$issuance->control_number} saved.");
    }

    private function doRelease(Request $request, Issuance $issuance, array $data): void
    {
        DB::transaction(function () use ($issuance, $data) {
            $issuance->content_hash   = $this->svc->computeHash($issuance);
            $issuance->recipient_type = $data['recipient_type'];
            $issuance->status         = 'released';
            $issuance->released_at    = now();
            $issuance->save();
            $this->svc->buildRecipients($issuance, $data);
            $issuance->recipients()->update(['notified_at' => now()]);
        });

        // Sign synchronously (fast — just a DB write)
        try {
            $this->performSign(
                $request,
                Issuance::class,
                $issuance->id,
                'release',
                "{$issuance->type_label}: {$issuance->title}",
                $issuance->content_hash,
            );
        } catch (\Throwable $e) {
            logger()->error('Issuance sign failed', ['id' => $issuance->id, 'error' => $e->getMessage()]);
        }

        // Dispatch PDF generation + email/notifications to the queue worker
        // (avoids 504 timeout — queue worker has 600s timeout vs 60s web timeout).
        // Pass primitive ID only — passing the Eloquent model would route through
        // SerializesModels and previously caused "__PHP_Incomplete_Class" errors
        // when an older worker picked up a payload referencing a not-yet-loaded class.
        try {
            ProcessIssuanceRelease::dispatch($issuance->id);
            logger()->info('Issuance release job dispatched', [
                'issuance_id'    => $issuance->id,
                'control_number' => $issuance->control_number,
                'queue'          => config('queue.default'),
            ]);
        } catch (\Throwable $e) {
            logger()->error('Issuance release job dispatch FAILED', [
                'issuance_id' => $issuance->id,
                'error'       => $e->getMessage(),
                'trace'       => $e->getTraceAsString(),
            ]);
        }
    }

    // ── Show ──────────────────────────────────────────────────────────────────

    public function show(Request $request, Issuance $issuance)
    {
        $user    = $request->user();
        $isAdmin = $user->hasAnyRole(['Administrator', 'OCD']);

        // Staff can only view released issuances addressed to them
        if (! $isAdmin) {
            $isRecipient = $issuance->recipients()->where('user_id', $user->id)->exists();
            abort_if(! $isRecipient || $issuance->status !== 'released', 403);
        }

        $issuance->load(['creator:id,name,position', 'signature.signer:id,name,position,electronic_signature']);

        $recipients = $issuance->recipients()
            ->with(['user:id,name,position,office_id', 'office:id,name'])
            ->get()
            ->map(fn($r) => [
                'id'              => $r->id,
                'user'            => $r->user?->only('id', 'name', 'position'),
                'office'          => $r->office?->only('id', 'name'),
                'acknowledged_at' => $r->acknowledged_at?->toISOString(),
                'notified_at'     => $r->notified_at?->toISOString(),
                'is_me'           => $r->user_id === $user->id,
            ]);

        $sig    = $issuance->signature;
        $sigUri = $sig?->signer ? $this->sigService->getSignatureDataUri($sig->signer) : null;
        $qrSvg  = $issuance->isReleased() ? $this->svc->generateQrSvg($issuance) : null;

        $myRecipient = $isAdmin ? null
            : $issuance->recipients()->where('user_id', $user->id)->first();

        return Inertia::render('Issuances/Show', [
            'issuance'   => [
                'id'               => $issuance->id,
                'type'             => $issuance->type,
                'type_label'       => $issuance->type_label,
                'control_number'   => $issuance->control_number,
                'title'            => $issuance->title,
                'content'          => $issuance->content,
                'attachment_path'  => $issuance->attachment_path,
                'has_attachment'   => (bool) $issuance->attachment_path,
                'attachment_filename' => $issuance->attachment_filename,
                'recipient_type'   => $issuance->recipient_type,
                'status'           => $issuance->status,
                'content_hash'     => $issuance->content_hash,
                'qr_token'         => $issuance->qr_token,
                'qr_svg'           => $qrSvg,
                'released_at'      => $issuance->released_at?->toISOString(),
                'created_at'       => $issuance->created_at?->toISOString(),
                'creator'          => $issuance->creator?->only('id', 'name', 'position'),
                'signature'        => $sig ? [
                    'signer'    => $sig->signer?->only('id', 'name', 'position'),
                    'signed_at' => $sig->signed_at?->toISOString(),
                    'sig_uri'   => $sigUri,
                ] : null,
            ],
            'recipients'  => $recipients,
            'isAdmin'     => $isAdmin,
            'hasPin'      => ! empty($user->signature_pin),
            'signatureUri'=> $isAdmin ? $this->sigService->getSignatureDataUri($user) : null,
            'myAcknowledgedAt' => $myRecipient?->acknowledged_at?->toISOString(),
            'verifyUrl'   => $issuance->isReleased()
                ? route('issuances.verify', $issuance->qr_token)
                : null,
        ]);
    }

    // ── Release (sign + publish) ──────────────────────────────────────────────

    public function release(Request $request, Issuance $issuance)
    {
        abort_if(! $request->user()->hasAnyRole(['Administrator', 'OCD']), 403);
        abort_if(! $issuance->isEditable(), 422, 'Only draft issuances can be released.');

        $validated = $request->validate([
            'recipient_type' => 'required|in:all,office,individual',
            'office_ids'     => 'nullable|array',
            'user_ids'       => 'nullable|array',
            'pin'            => 'nullable|string',
        ]);

        $this->doRelease($request, $issuance, $validated);

        return back()->with('success', "{$issuance->control_number} released to recipients.");
    }

    // ── Acknowledge ───────────────────────────────────────────────────────────

    public function acknowledge(Request $request, Issuance $issuance)
    {
        $uid       = $request->user()->id;
        $recipient = $issuance->recipients()->where('user_id', $uid)->firstOrFail();

        abort_if($recipient->acknowledged_at, 422, 'Already acknowledged.');

        $recipient->update(['acknowledged_at' => now()]);

        // Notify OCD that someone acknowledged
        $creator = $issuance->creator;
        if ($creator) {
            try {
                NotificationService::notifyUser(
                    $creator, 'Issuance', $issuance->control_number,
                    "Acknowledged by {$request->user()->name}",
                    route('issuances.show', $issuance->id),
                );
            } catch (\Throwable) {}
        }

        return back()->with('success', 'Receipt acknowledged.');
    }

    // ── Download PDF ──────────────────────────────────────────────────────────

    public function downloadPdf(Request $request, Issuance $issuance)
    {
        $user    = $request->user();
        $isAdmin = $user->hasAnyRole(['Administrator', 'OCD']);

        if (! $isAdmin) {
            $isRecipient = $issuance->recipients()->where('user_id', $user->id)->exists();
            abort_if(! $isRecipient || $issuance->status !== 'released', 403);
        }

        // Regenerate on demand if not on S3 yet
        $pdfPath = 'issuances/' . $issuance->control_number . '.pdf';
        if (! Storage::disk('s3')->exists($pdfPath)) {
            $pdfPath = $this->svc->generatePdf($issuance);
        }

        $url = Storage::disk('s3')->temporaryUrl($pdfPath, now()->addMinutes(5));
        return redirect($url);
    }

    // ── View scan (S3 proxy) ──────────────────────────────────────────────────

    public function viewScan(Request $request, Issuance $issuance)
    {
        $user    = $request->user();
        $isAdmin = $user->hasAnyRole(['Administrator', 'OCD']);

        if (! $isAdmin) {
            $isRecipient = $issuance->recipients()->where('user_id', $user->id)->exists();
            abort_if(! $isRecipient || $issuance->status !== 'released', 403);
        }

        abort_if(! $issuance->attachment_path, 404);

        $content = Storage::disk('s3')->get($issuance->attachment_path);
        return response($content, 200, [
            'Content-Type'        => $issuance->attachment_mime ?? 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $issuance->attachment_filename . '"',
            'Cache-Control'       => 'private, max-age=300',
        ]);
    }
}
