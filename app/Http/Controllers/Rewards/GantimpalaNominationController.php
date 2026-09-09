<?php

namespace App\Http\Controllers\Rewards;

use App\Http\Controllers\Controller;
use App\Models\GantimpalaLog;
use App\Models\GantimpalaNomination;
use App\Models\User;
use App\Services\DigitalSignatureService;
use App\Services\Rewards\GantimpalaPdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class GantimpalaNominationController extends Controller
{
    public function __construct(
        private GantimpalaPdfService $pdf,
        private DigitalSignatureService $sigService,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('rewards.gantimpala.view');

        $query = GantimpalaNomination::with(['nominee', 'nominator', 'endorser', 'decider'])
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('source')) {
            $query->where('source', $request->source);
        }
        if ($request->filled('search')) {
            $term = $request->search;
            $query->where(function ($q) use ($term) {
                $q->where('nominee_name', 'like', "%{$term}%")
                  ->orWhere('nominator_name', 'like', "%{$term}%")
                  ->orWhere('reference_no', 'like', "%{$term}%");
            });
        }

        $nominations = $query->paginate(20)->withQueryString();

        $stats = [
            'total'         => GantimpalaNomination::count(),
            'pending'       => GantimpalaNomination::where('status', 'pending')->count(),
            'under_review'  => GantimpalaNomination::where('status', 'under_review')->count(),
            'endorsed'      => GantimpalaNomination::where('status', 'endorsed')->count(),
            'approved'      => GantimpalaNomination::where('status', 'approved')->count(),
            'kiosk_count'   => GantimpalaNomination::where('source', 'kiosk')->count(),
        ];

        return Inertia::render('Rewards/Gantimpala/Index', [
            'nominations' => $nominations,
            'stats'       => $stats,
            'filters'     => $request->only(['status', 'source', 'search']),
        ]);
    }

    public function create()
    {
        $this->authorize('rewards.nominate');

        $user = auth()->user();

        return Inertia::render('Rewards/Gantimpala/Create', [
            'employees'    => User::employees()->where('status', '<>', 'inactive')
                ->orderBy('name')->get(['id', 'name', 'email']),
            'hasPin'       => ! empty($user->signature_pin),
            'signatureUri' => $this->sigService->getSignatureDataUri($user),
        ]);
    }

    public function show(GantimpalaNomination $gantimpalaNomination)
    {
        $this->authorize('rewards.gantimpala.view');

        $gantimpalaNomination->load(['nominee', 'nominator', 'endorser', 'decider', 'logs.actor']);

        return Inertia::render('Rewards/Gantimpala/Show', [
            'nomination' => $gantimpalaNomination,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('rewards.nominate');

        $data = $this->validateNomination($request);

        $this->sigService->assertSigningPin(auth()->user(), $data['pin'] ?? null);

        $nomination = $this->persist($data, 'atlas', $request);

        return redirect()->route('rewards.gantimpala.show', $nomination)
            ->with('success', 'Gantimpala Agad nomination submitted. Reference No: ' . $nomination->reference_no);
    }

    public function endorse(Request $request, GantimpalaNomination $gantimpalaNomination)
    {
        $this->authorize('rewards.gantimpala.manage');

        $data = $request->validate([
            'supervisor_name'            => 'required|string|max:255',
            'supervisor_signature_path'  => 'nullable|string', // base64 data URI
            'remarks'                    => 'nullable|string',
        ]);

        DB::transaction(function () use ($data, $gantimpalaNomination) {
            $sigPath = $gantimpalaNomination->supervisor_signature_path;
            if (! empty($data['supervisor_signature_path'])) {
                $sigPath = $this->storeSignature($data['supervisor_signature_path'], 'supervisor');
            }

            $gantimpalaNomination->update([
                'supervisor_name'           => $data['supervisor_name'],
                'supervisor_signature_path' => $sigPath,
                'endorsed_at'               => now(),
                'endorsed_by'               => auth()->id(),
                'status'                    => 'endorsed',
                'remarks'                   => $data['remarks'] ?? $gantimpalaNomination->remarks,
            ]);

            GantimpalaLog::create([
                'nomination_id'   => $gantimpalaNomination->id,
                'actor_id'        => auth()->id(),
                'action'          => 'endorsed',
                'notes'           => $data['remarks'] ?? null,
                'status_snapshot' => 'endorsed',
                'acted_at'        => now(),
            ]);
        });

        return back()->with('success', 'Nomination endorsed.');
    }

    public function decide(Request $request, GantimpalaNomination $gantimpalaNomination)
    {
        $this->authorize('rewards.gantimpala.manage');

        $data = $request->validate([
            'decision' => 'required|in:approved,rejected',
            'remarks'  => 'nullable|string',
        ]);

        DB::transaction(function () use ($data, $gantimpalaNomination) {
            $gantimpalaNomination->update([
                'status'     => $data['decision'],
                'remarks'    => $data['remarks'] ?? $gantimpalaNomination->remarks,
                'decided_by' => auth()->id(),
                'decided_at' => now(),
            ]);

            GantimpalaLog::create([
                'nomination_id'   => $gantimpalaNomination->id,
                'actor_id'        => auth()->id(),
                'action'          => $data['decision'],
                'notes'           => $data['remarks'] ?? null,
                'status_snapshot' => $data['decision'],
                'acted_at'        => now(),
            ]);
        });

        return back()->with('success', 'Decision recorded.');
    }

    public function markUnderReview(GantimpalaNomination $gantimpalaNomination)
    {
        $this->authorize('rewards.gantimpala.manage');

        if ($gantimpalaNomination->status !== 'pending') {
            return back()->withErrors(['status' => 'Only pending nominations can be moved to review.']);
        }

        DB::transaction(function () use ($gantimpalaNomination) {
            $gantimpalaNomination->update(['status' => 'under_review']);

            GantimpalaLog::create([
                'nomination_id'   => $gantimpalaNomination->id,
                'actor_id'        => auth()->id(),
                'action'          => 'under_review',
                'status_snapshot' => 'under_review',
                'acted_at'        => now(),
            ]);
        });

        return back()->with('success', 'Nomination is now under review.');
    }

    public function archive(GantimpalaNomination $gantimpalaNomination)
    {
        $this->authorize('rewards.gantimpala.manage');

        DB::transaction(function () use ($gantimpalaNomination) {
            $gantimpalaNomination->update(['status' => 'archived']);

            GantimpalaLog::create([
                'nomination_id'   => $gantimpalaNomination->id,
                'actor_id'        => auth()->id(),
                'action'          => 'archived',
                'status_snapshot' => 'archived',
                'acted_at'        => now(),
            ]);
        });

        return back()->with('success', 'Nomination archived.');
    }

    public function downloadPdf(GantimpalaNomination $gantimpalaNomination)
    {
        $this->authorize('rewards.gantimpala.view');

        $path = $this->pdf->generate($gantimpalaNomination);

        GantimpalaLog::create([
            'nomination_id'   => $gantimpalaNomination->id,
            'actor_id'        => auth()->id(),
            'action'          => 'pdf_generated',
            'status_snapshot' => $gantimpalaNomination->status,
            'acted_at'        => now(),
        ]);

        $url = Storage::disk('s3')->temporaryUrl($path, now()->addMinutes(5));

        return redirect($url);
    }

    // ── Shared helpers (also used by the public kiosk controller) ─────────────

    public function validateNomination(Request $request): array
    {
        return $request->validate([
            'nominee_user_id'           => 'nullable|exists:users,id',
            'nominee_name'              => 'required|string|max:255',
            'nominee_title'             => 'nullable|string|max:255',
            'nominee_department'       => 'nullable|string|max:255',
            'nominee_address'           => 'nullable|string|max:255',
            'nominee_contact_number'    => 'nullable|string|max:50',
            'date_submitted'            => 'nullable|date',

            'nominator_name'            => 'required|string|max:255',
            'nominator_address'         => 'nullable|string|max:255',
            'nominator_contact_number'  => 'nullable|string|max:50',
            'nominator_email'           => 'nullable|email|max:255',

            'activity_conducted'        => 'required|string|max:255',
            'activity_date'             => 'nullable|date',
            'venue_location'            => 'nullable|string|max:255',
            'other_information'         => 'nullable|string',

            'nominator_signature_path'  => 'nullable|string', // base64 data URI (kiosk / freehand only)
            'pin'                       => 'nullable|string', // signing PIN (in-app Atlas nomination only)
        ]);
    }

    public function persist(array $data, string $source, Request $request): GantimpalaNomination
    {
        return DB::transaction(function () use ($data, $source, $request) {
            $sigPath = null;
            if (! empty($data['nominator_signature_path'])) {
                // Freehand capture — kiosk, or an in-app user with no signature on file.
                $sigPath = $this->storeSignature($data['nominator_signature_path'], 'nominator');
            } elseif ($source === 'atlas') {
                // Atlas in-app nomination — reuse the nominator's existing Digital
                // Signature on file (Profile → Digital Signature) instead of asking
                // them to redraw it every time.
                $sigPath = $this->copyOnFileSignature(auth()->user(), 'nominator');
            }

            $nomination = GantimpalaNomination::create([
                'source'                   => $source,
                'nominee_user_id'          => $data['nominee_user_id'] ?? null,
                'nominee_name'             => $data['nominee_name'],
                'nominee_title'            => $data['nominee_title'] ?? null,
                'nominee_department'       => $data['nominee_department'] ?? null,
                'nominee_address'          => $data['nominee_address'] ?? null,
                'nominee_contact_number'   => $data['nominee_contact_number'] ?? null,
                'date_submitted'           => $data['date_submitted'] ?? now()->toDateString(),
                'nominator_user_id'        => $source === 'atlas' ? auth()->id() : null,
                'nominator_name'           => $data['nominator_name'],
                'nominator_address'        => $data['nominator_address'] ?? null,
                'nominator_contact_number' => $data['nominator_contact_number'] ?? null,
                'nominator_email'          => $data['nominator_email'] ?? null,
                'activity_conducted'       => $data['activity_conducted'],
                'activity_date'            => $data['activity_date'] ?? null,
                'venue_location'           => $data['venue_location'] ?? null,
                'other_information'        => $data['other_information'] ?? null,
                'nominator_signature_path' => $sigPath,
                'status'                   => 'pending',
                'submitted_ip'             => $request->ip(),
                'kiosk_device'             => $source === 'kiosk' ? $request->userAgent() : null,
            ]);

            $nomination->update(['reference_no' => $this->generateReferenceNo($nomination)]);

            GantimpalaLog::create([
                'nomination_id'   => $nomination->id,
                'actor_id'        => $source === 'atlas' ? auth()->id() : null,
                'action'          => 'submitted',
                'notes'           => $source === 'kiosk' ? 'Submitted via public kiosk.' : null,
                'status_snapshot' => 'pending',
                'acted_at'        => now(),
            ]);

            return $nomination;
        });
    }

    private function storeSignature(string $dataUri, string $who): string
    {
        if (! preg_match('/^data:image\/(png|jpeg);base64,(.+)$/', $dataUri, $m)) {
            throw ValidationException::withMessages(['signature' => 'Invalid signature image format.']);
        }

        $ext = $m[1] === 'jpeg' ? 'jpg' : 'png';
        $binary = base64_decode($m[2], true);
        if ($binary === false) {
            throw ValidationException::withMessages(['signature' => 'Invalid signature data.']);
        }

        $path = "rewards/gantimpala/signatures/{$who}_" . uniqid() . ".{$ext}";
        Storage::disk('s3')->put($path, $binary);

        return $path;
    }

    /**
     * Copy a user's existing Digital Signature (users.electronic_signature)
     * into this module's own signatures/ prefix, so the nomination's stored
     * path is self-contained and unaffected if the user later replaces their
     * signature on file. Returns null if the user has no signature on file.
     */
    private function copyOnFileSignature(User $user, string $who): ?string
    {
        $dataUri = $this->sigService->getSignatureDataUri($user);
        if (! $dataUri) {
            return null;
        }

        if (! preg_match('/^data:image\/(png|jpeg);base64,(.+)$/', $dataUri, $m)) {
            return null;
        }

        $ext = $m[1] === 'jpeg' ? 'jpg' : 'png';
        $binary = base64_decode($m[2], true);
        if ($binary === false) {
            return null;
        }

        $path = "rewards/gantimpala/signatures/{$who}_" . uniqid() . ".{$ext}";
        Storage::disk('s3')->put($path, $binary);

        return $path;
    }

    private function generateReferenceNo(GantimpalaNomination $nomination): string
    {
        return 'GA-' . now()->format('Y') . '-' . str_pad((string) $nomination->id, 5, '0', STR_PAD_LEFT);
    }
}
