<?php

namespace App\Http\Controllers;

use App\Models\IctRemoteHelpRequest;
use App\Services\AtlasSentinelRemoteHelpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * MIS-facing remote-help queue: claim a waiting request, open the AnyDesk
 * connection panel (audit-logged, claimer-only), and end the session. The
 * AnyDesk ID is an address rather than a secret, but the connect action is
 * still claimer-gated and logged so every session is accountable.
 */
class AtlasSentinelRemoteHelpController extends Controller
{
    private const PERMISSION = 'atlas.sentinel.remote-help.manage';

    public function __construct(private AtlasSentinelRemoteHelpService $remoteHelp)
    {
    }

    public function index(Request $request): Response
    {
        $this->authorize(self::PERMISSION);

        return Inertia::render('AtlasSentinel/RemoteHelp/Index', [
            'enabled'  => $this->remoteHelp->enabled(),
            'requests' => $this->snapshot($request->input('status')),
            'counts'   => $this->counts(),
            'filters'  => $request->only(['status']),
            'pollUrl'  => route('atlas-sentinel.remote-help.poll'),
        ]);
    }

    /** JSON queue snapshot — the page polls this every ~10s while open. */
    public function poll(Request $request): JsonResponse
    {
        $this->authorize(self::PERMISSION);

        return response()->json([
            'requests' => $this->snapshot($request->input('status')),
            'counts'   => $this->counts(),
        ]);
    }

    public function claim(IctRemoteHelpRequest $helpRequest): JsonResponse
    {
        $this->authorize(self::PERMISSION);

        // Atomic claim: only the first responder wins the still-waiting row.
        $claimed = IctRemoteHelpRequest::where('id', $helpRequest->id)
            ->where('status', IctRemoteHelpRequest::STATUS_WAITING)
            ->update([
                'status'     => IctRemoteHelpRequest::STATUS_CLAIMED,
                'claimed_by' => Auth::id(),
                'claimed_at' => now(),
            ]);

        if (! $claimed) {
            return response()->json(['message' => 'This request was already claimed or is no longer waiting.'], 409);
        }

        $this->remoteHelp->logEvent($helpRequest->fresh(), 'claimed', Auth::id());

        return response()->json(['request' => $this->mapRow($this->load($helpRequest->id))]);
    }

    /**
     * Open the AnyDesk connection panel for the claiming technician — returns the
     * device's AnyDesk ID, marks the request in_session, and logs the connect
     * against the user for the audit trail. Claimer-only.
     */
    public function reveal(IctRemoteHelpRequest $helpRequest): JsonResponse
    {
        $this->authorize(self::PERMISSION);

        if ($helpRequest->claimed_by !== Auth::id()) {
            return response()->json(['message' => 'Only the technician who claimed this request can connect to it.'], 403);
        }

        if (! $helpRequest->session_ready_at || ! $helpRequest->anydesk_id) {
            return response()->json(['message' => 'The device has not reported its AnyDesk ID yet. Please wait a moment and try again.'], 409);
        }

        if (! in_array($helpRequest->status, [IctRemoteHelpRequest::STATUS_CLAIMED, IctRemoteHelpRequest::STATUS_IN_SESSION], true)) {
            return response()->json(['message' => 'This request is no longer active.'], 409);
        }

        if ($helpRequest->status !== IctRemoteHelpRequest::STATUS_IN_SESSION) {
            $helpRequest->update([
                'status'      => IctRemoteHelpRequest::STATUS_IN_SESSION,
                'revealed_at' => now(),
            ]);
        }

        $this->remoteHelp->logEvent($helpRequest, 'connected', Auth::id());

        return response()->json([
            'anydesk_id' => $helpRequest->anydesk_id,
        ]);
    }

    /**
     * End the session. Attended AnyDesk has no password to rotate, so this
     * finalizes the request directly.
     */
    public function end(IctRemoteHelpRequest $helpRequest): JsonResponse
    {
        $this->authorize(self::PERMISSION);

        if (! $helpRequest->isActive()) {
            return response()->json(['request' => $this->mapRow($this->load($helpRequest->id))]);
        }

        $helpRequest->update([
            'status'     => IctRemoteHelpRequest::STATUS_COMPLETED,
            'end_reason' => 'ended',
            'ended_at'   => now(),
        ]);

        $this->remoteHelp->logEvent($helpRequest, 'ended', Auth::id());

        return response()->json(['request' => $this->mapRow($this->load($helpRequest->id))]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function counts(): array
    {
        $rows = IctRemoteHelpRequest::select('status', DB::raw('COUNT(*) as c'))
            ->groupBy('status')
            ->pluck('c', 'status');

        return [
            'waiting'    => (int) ($rows[IctRemoteHelpRequest::STATUS_WAITING] ?? 0),
            'claimed'    => (int) ($rows[IctRemoteHelpRequest::STATUS_CLAIMED] ?? 0),
            'in_session' => (int) ($rows[IctRemoteHelpRequest::STATUS_IN_SESSION] ?? 0),
        ];
    }

    /**
     * Active requests first (waiting → claimed → in_session), then the most
     * recent finalized ones for context. Optional status filter.
     */
    private function snapshot(?string $status): array
    {
        $query = IctRemoteHelpRequest::with(['device:id,hostname', 'equipment:id,property_no,description,owner_id', 'equipment.owner:id,name', 'claimer:id,name', 'jobRequest:id,itjr_no,status'])
            ->orderByRaw("FIELD(status,'waiting','claimed','in_session','failed','timed_out','canceled','completed')")
            ->orderByDesc('id');

        if ($status) {
            $query->where('status', $status);
        } else {
            // Unfiltered view: everything live + a recent tail of finished rows.
            $query->where(function ($q) {
                $q->whereIn('status', IctRemoteHelpRequest::ACTIVE_STATUSES)
                  ->orWhere('created_at', '>=', now()->subDay());
            });
        }

        return $query->limit(100)->get()->map(fn ($r) => $this->mapRow($r))->all();
    }

    private function load(int $id): IctRemoteHelpRequest
    {
        return IctRemoteHelpRequest::with(['device:id,hostname', 'equipment:id,property_no,description,owner_id', 'equipment.owner:id,name', 'claimer:id,name', 'jobRequest:id,itjr_no,status'])
            ->findOrFail($id);
    }

    private function mapRow(IctRemoteHelpRequest $r): array
    {
        return [
            'id'                => $r->id,
            'status'            => $r->status,
            'note'              => $r->note,
            'hostname'          => $r->device?->hostname,
            'property_no'       => $r->equipment?->property_no,
            'description'       => $r->equipment?->description,
            'owner_name'        => $r->equipment?->owner?->name,
            'itjr_no'           => $r->jobRequest?->itjr_no,
            'has_ticket'        => (bool) $r->it_job_request_id,
            'claimed_by'        => $r->claimed_by,
            'claimer_name'      => $r->claimer?->name,
            'is_mine'           => $r->claimed_by === Auth::id(),
            'id_ready'          => (bool) $r->session_ready_at,
            'anydesk_id'        => $r->anydesk_id,
            'error_message'     => $r->error_message,
            'created_at'        => $r->created_at?->toIso8601String(),
            'claimed_at'        => $r->claimed_at?->toIso8601String(),
            'ended_at'          => $r->ended_at?->toIso8601String(),
        ];
    }
}
