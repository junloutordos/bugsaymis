<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Http\Requests\HR\OnlineTimePunchRequest;
use App\Models\HR\FaceEnrollment;
use App\Models\HR\OnlinePunchGeofenceZone;
use App\Models\HR\OnlinePunchNetworkRule;
use App\Models\HR\OnlineTimePunch;
use App\Services\HR\DTRService;
use App\Services\HR\FaceRecognitionService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class OnlineTimePunchController extends Controller
{
    public function __construct(
        private readonly FaceRecognitionService $faceService,
        private readonly DTRService $dtrService,
    ) {}

    // ─── Inertia Page ─────────────────────────────────────────────────────────

    public function index()
    {
        $user  = Auth::user();
        $today = Carbon::today()->toDateString();

        $enrollment = FaceEnrollment::where('user_id', $user->id)
            ->where('is_active', true)
            ->latest()
            ->first();

        $todayPunches = OnlineTimePunch::where('user_id', $user->id)
            ->where('work_date', $today)
            ->orderBy('punched_at')
            ->get();

        return Inertia::render('HumanResource/OnlinePunch/Dashboard', [
            'today'            => $today,
            'enrollmentStatus' => $enrollment?->status,
            'todayPunches'     => $todayPunches,
        ]);
    }

    // ─── API: Start Liveness Challenge ────────────────────────────────────────

    public function challengeStart()
    {
        return response()->json($this->faceService->issueChallenge(Auth::user()));
    }

    // ─── API: Location Pre-check ──────────────────────────────────────────────

    /**
     * Fail-fast location gate, called right after the browser resolves
     * geolocation so an off-campus or coarse-location user is told before the
     * camera/liveness flow runs (and before burning a punch attempt). The same
     * gate is re-enforced server-side on the actual punch submission.
     */
    public function locationCheck(Request $request)
    {
        $data = $request->validate([
            'latitude'  => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy'  => ['nullable', 'numeric', 'min:0'],
        ]);

        $gate = $this->faceService->resolveLocationGate(
            lat:      (float) $data['latitude'],
            lng:      (float) $data['longitude'],
            accuracy: isset($data['accuracy']) ? (float) $data['accuracy'] : null,
            ip:       $this->clientIp($request),
        );

        return response()->json([
            'status'          => $gate['status'],
            'distance_meters' => $gate['geofence']['distanceMeters'],
            'network_status'  => $gate['networkStatus'],
        ]);
    }

    // ─── API: Submit Punch ────────────────────────────────────────────────────

    public function punch(OnlineTimePunchRequest $request)
    {
        $user = Auth::user();

        $punch = $this->faceService->verifyPunch(
            user:           $user,
            frames:         $request->validated('frames'),
            challengeToken: $request->validated('challenge_token'),
            punchType:      $request->validated('punch_type'),
            ip:             $this->clientIp($request),
            lat:            $request->validated('latitude'),
            lng:            $request->validated('longitude'),
            accuracy:       $request->validated('accuracy'),
            userAgent:      $request->userAgent(),
        );

        $messages = [
            'verified'      => 'Punch recorded and verified successfully.',
            'manual_review' => 'Punch recorded but flagged for HR review — confidence was low.',
            'rejected'      => $this->rejectionMessage($punch),
        ];

        $attemptsUsed = OnlineTimePunch::where('user_id', $user->id)
            ->where('work_date', $punch->work_date)
            ->where('punch_type', $punch->punch_type)
            ->where('match_status', 'rejected')
            ->count();

        $dtrSummary = $punch->match_status === 'verified'
            ? $this->dtrService->summaryForPunch($user->id, $punch->work_date->toDateString(), $punch->punch_type)
            : null;

        return response()->json([
            'message'       => $messages[$punch->match_status] ?? 'Punch recorded.',
            'punch'         => $punch,
            'attempts_used' => $attemptsUsed,
            'max_attempts'  => FaceRecognitionService::MAX_PUNCH_ATTEMPTS,
            'dtr_summary'   => $dtrSummary,
        ]);
    }

    /**
     * Real client IP. Laravel's trustProxies('*') only trusts the immediate
     * peer (the ALB), so $request->ip() resolves to the Cloudflare edge IP —
     * useless for campus-network matching. CF-Connecting-IP carries the real
     * client and is trustworthy here because the ALB only accepts traffic
     * from Cloudflare.
     */
    private function clientIp(Request $request): ?string
    {
        return $request->header('CF-Connecting-IP') ?: $request->ip();
    }

    /** A location rejection must read as a location rejection, not a face-match failure. */
    private function rejectionMessage(OnlineTimePunch $punch): string
    {
        return match ($punch->failure_reason) {
            'outside_geofence' => 'Punch rejected — you appear to be '
                . $this->formatDistance($punch->distance_meters)
                . ' from campus. Online punches are only allowed on campus.',
            'no_location_permission' => 'Punch rejected — location access is required to punch.',
            'location_too_coarse' => 'Punch rejected — your device could not provide a precise enough location. Use a phone with GPS, or punch from the campus network.',
            default => 'Punch was rejected — face match failed. Please try again or use another method.',
        };
    }

    private function formatDistance(?float $meters): string
    {
        if ($meters === null) {
            return 'too far';
        }

        return $meters >= 1000
            ? number_format($meters / 1000, 1) . ' km'
            : round($meters) . ' m';
    }

    // ─── API: My Punches ──────────────────────────────────────────────────────

    public function myPunches(Request $request)
    {
        $query = OnlineTimePunch::where('user_id', Auth::id())
            ->orderByDesc('work_date');

        if ($request->filled('month')) {
            [$year, $mon] = explode('-', $request->query('month'));
            $query->whereYear('work_date', $year)->whereMonth('work_date', $mon);
        }

        return response()->json($query->paginate(30));
    }

    // ─── Inertia: Monitoring Page ─────────────────────────────────────────────

    public function monitorPage()
    {
        $this->authorizeMonitor(Auth::user());

        return Inertia::render('HumanResource/OnlinePunch/Monitoring');
    }

    // ─── API: Monitoring ──────────────────────────────────────────────────────

    public function monitor(Request $request)
    {
        $user = Auth::user();
        $user->loadMissing('roles');

        $query = OnlineTimePunch::with('user:id,name,position,division_id,office_id')
            ->orderByDesc('work_date');

        if (! ($user->isSuperAdmin() || $user->hasRole('HR'))) {
            if ($user->hasRole('DivisionChief')) {
                $query->whereHas('user', fn ($q) => $q->where('division_id', $user->division_id));
            } elseif ($user->hasRole('OCD')) {
                $query->whereHas('user', fn ($q) => $q->where('office_id', $user->office_id));
            }
        }

        if ($request->filled('month')) {
            [$year, $mon] = explode('-', $request->query('month'));
            $query->whereYear('work_date', $year)->whereMonth('work_date', $mon);
        }

        if ($request->filled('status')) {
            $query->where('match_status', $request->query('status'));
        }

        if ($request->filled('search')) {
            $query->whereHas('user', fn ($q) => $q->where('name', 'like', '%' . $request->query('search') . '%'));
        }

        return response()->json($query->paginate(30));
    }

    // ─── Image Proxy ──────────────────────────────────────────────────────────

    public function photo(string $fileId)
    {
        if (! preg_match('/^[a-zA-Z0-9_.=-]+$/', $fileId)) {
            abort(400);
        }

        $s3Key = $this->faceService->decodeS3Key($fileId);
        if (! $s3Key || ! Storage::disk('s3')->exists($s3Key)) {
            abort(404);
        }

        $contents = Storage::disk('s3')->get($s3Key);
        if (! $contents) {
            abort(404);
        }

        return response($contents, 200)
            ->header('Content-Type', 'image/jpeg')
            ->header('Cache-Control', 'private, max-age=3600');
    }

    // ─── API: Geofence Zones (management) ─────────────────────────────────────

    public function geofenceZones()
    {
        return response()->json(OnlinePunchGeofenceZone::orderByDesc('is_active')->orderBy('label')->get());
    }

    public function storeGeofenceZone(Request $request)
    {
        $data = $request->validate([
            'label'         => ['required', 'string', 'max:255'],
            'latitude'      => ['required', 'numeric', 'between:-90,90'],
            'longitude'     => ['required', 'numeric', 'between:-180,180'],
            'radius_meters' => ['required', 'integer', 'min:10', 'max:5000'],
            'is_active'     => ['boolean'],
        ]);

        $zone = OnlinePunchGeofenceZone::create(array_merge($data, ['created_by' => Auth::id()]));

        return response()->json(['message' => "Geofence zone \"{$zone->label}\" created.", 'zone' => $zone]);
    }

    public function updateGeofenceZone(Request $request, OnlinePunchGeofenceZone $zone)
    {
        $data = $request->validate([
            'label'         => ['required', 'string', 'max:255'],
            'latitude'      => ['required', 'numeric', 'between:-90,90'],
            'longitude'     => ['required', 'numeric', 'between:-180,180'],
            'radius_meters' => ['required', 'integer', 'min:10', 'max:5000'],
            'is_active'     => ['boolean'],
        ]);

        $zone->update($data);

        return response()->json(['message' => "Geofence zone \"{$zone->label}\" updated.", 'zone' => $zone]);
    }

    public function destroyGeofenceZone(OnlinePunchGeofenceZone $zone)
    {
        $zone->delete();

        return response()->json(['message' => 'Geofence zone deleted.']);
    }

    // ─── API: Trusted Networks (management) ───────────────────────────────────

    public function networkRules()
    {
        return response()->json(OnlinePunchNetworkRule::orderByDesc('is_active')->orderBy('label')->get());
    }

    public function storeNetworkRule(Request $request)
    {
        $data = $request->validate([
            'label'     => ['required', 'string', 'max:255'],
            'cidr'      => ['required', 'string', 'max:64', 'regex:/^\d{1,3}(\.\d{1,3}){3}(\/\d{1,2})?$/'],
            'is_active' => ['boolean'],
        ]);

        $rule = OnlinePunchNetworkRule::create(array_merge($data, ['created_by' => Auth::id()]));

        return response()->json(['message' => "Network rule \"{$rule->label}\" created.", 'rule' => $rule]);
    }

    public function updateNetworkRule(Request $request, OnlinePunchNetworkRule $rule)
    {
        $data = $request->validate([
            'label'     => ['required', 'string', 'max:255'],
            'cidr'      => ['required', 'string', 'max:64', 'regex:/^\d{1,3}(\.\d{1,3}){3}(\/\d{1,2})?$/'],
            'is_active' => ['boolean'],
        ]);

        $rule->update($data);

        return response()->json(['message' => "Network rule \"{$rule->label}\" updated.", 'rule' => $rule]);
    }

    public function destroyNetworkRule(OnlinePunchNetworkRule $rule)
    {
        $rule->delete();

        return response()->json(['message' => 'Network rule deleted.']);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function authorizeMonitor($user): void
    {
        if (! $user->hasAnyRole(['DivisionChief', 'OCD', 'HR', 'Administrator'])) {
            abort(403, 'Unauthorized to monitor Online Time Punches.');
        }
    }
}
