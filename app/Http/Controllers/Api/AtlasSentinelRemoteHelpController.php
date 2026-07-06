<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ITJobRequestController;
use App\Models\ICTEquipment;
use App\Models\ITJobCategory;
use App\Models\IctRemoteHelpRequest;
use App\Models\User;
use App\Services\AtlasSentinelRemoteHelpService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Agent-facing endpoints for the attended AnyDesk remote-help flow. Lives behind
 * the `ict-agent` Sanctum group, so $request->user() is always the calling
 * device (EnsureAtlasSentinelDevice) — every request row is guarded to it.
 *
 * The tray drives everything: it installs/launches AnyDesk, reads the AnyDesk
 * ID, and reports it here. The SYSTEM service is not involved.
 */
class AtlasSentinelRemoteHelpController extends Controller
{
    public function __construct(
        private AtlasSentinelRemoteHelpService $remoteHelp,
        private ITJobRequestController $jobRequests,
    ) {
    }

    /**
     * POST /api/ict-agent/remote-help
     * The tray "Ask for Remote Help" button. Creates the queued request, opens a
     * documentation ITJR ticket when the equipment has an owner (never blocks if
     * it doesn't), and alerts MIS. The AnyDesk ID may be supplied now or reported
     * a moment later via report-id once AnyDesk finishes installing.
     */
    public function request(Request $request): JsonResponse
    {
        if (! $this->remoteHelp->enabled()) {
            return response()->json(['message' => 'Remote help is not available.', 'enabled' => false], 404);
        }

        $validated = $request->validate([
            'note'       => ['nullable', 'string', 'max:500'],
            'anydesk_id' => ['nullable', 'string', 'max:64'],
        ]);

        $device = $request->user();

        $existing = IctRemoteHelpRequest::where('device_id', $device->id)
            ->whereIn('status', IctRemoteHelpRequest::ACTIVE_STATUSES)
            ->orderByDesc('id')
            ->first();

        if ($existing) {
            // Idempotent-ish: the tray just resumes polling its live request.
            return response()->json(['request' => $this->trayView($existing)], 409);
        }

        $equipment = ICTEquipment::find($device->equipment_id);
        $jobRequestId = $this->tryOpenTicket($device, $equipment, $validated['note'] ?? null);

        $helpRequest = IctRemoteHelpRequest::create([
            'device_id'         => $device->id,
            'equipment_id'      => $device->equipment_id,
            'note'              => $validated['note'] ?? null,
            'status'            => IctRemoteHelpRequest::STATUS_WAITING,
            'it_job_request_id' => $jobRequestId,
            'anydesk_id'        => $validated['anydesk_id'] ?? null,
            'session_ready_at'  => isset($validated['anydesk_id']) ? now() : null,
        ]);

        $this->remoteHelp->logEvent($helpRequest, 'created');
        $this->notifyManagers($device->hostname);

        return response()->json(['request' => $this->trayView($helpRequest)], 201);
    }

    /**
     * POST /api/ict-agent/remote-help/{helpRequest}/report-id
     * The tray reports the AnyDesk ID once AnyDesk is up (may lag the request if
     * the user was still installing).
     */
    public function reportId(Request $request, IctRemoteHelpRequest $helpRequest): JsonResponse
    {
        $this->assertOwned($request, $helpRequest);

        $validated = $request->validate([
            'anydesk_id' => ['required', 'string', 'max:64'],
        ]);

        if (! $helpRequest->isActive()) {
            return response()->json(['message' => 'This request is no longer active.'], 409);
        }

        $helpRequest->update([
            'anydesk_id'       => $validated['anydesk_id'],
            'session_ready_at' => $helpRequest->session_ready_at ?? now(),
        ]);

        $this->remoteHelp->logEvent($helpRequest, 'anydesk_id_reported');

        if ($helpRequest->claimed_by) {
            if ($claimer = User::find($helpRequest->claimed_by)) {
                NotificationService::notifyUser(
                    $claimer,
                    'Remote Help',
                    'RH-' . $helpRequest->id,
                    'AnyDesk ID ready to connect',
                    route('atlas-sentinel.remote-help.index'),
                );
            }
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * POST /api/ict-agent/remote-help/{helpRequest}/cancel
     * User backed out from the tray.
     */
    public function cancel(Request $request, IctRemoteHelpRequest $helpRequest): JsonResponse
    {
        $this->assertOwned($request, $helpRequest);

        if (! $helpRequest->isActive()) {
            return response()->json(['request' => $this->trayView($helpRequest)]);
        }

        $helpRequest->update([
            'status'     => IctRemoteHelpRequest::STATUS_CANCELED,
            'end_reason' => 'canceled',
            'ended_at'   => now(),
        ]);
        $this->remoteHelp->logEvent($helpRequest, 'canceled_by_user');

        return response()->json(['request' => $this->trayView($helpRequest->fresh())]);
    }

    /**
     * GET /api/ict-agent/remote-help/current
     * Tray polling endpoint — the device's live request (status + who claimed it).
     */
    public function current(Request $request): JsonResponse
    {
        $device = $request->user();

        $helpRequest = IctRemoteHelpRequest::where('device_id', $device->id)
            ->whereIn('status', IctRemoteHelpRequest::ACTIVE_STATUSES)
            ->orderByDesc('id')
            ->first();

        if (! $helpRequest) {
            return response()->json(['active' => false, 'request' => null]);
        }

        return response()->json([
            'active'  => true,
            'request' => $this->trayView($helpRequest),
        ]);
    }

    /**
     * POST /api/ict-agent/remote-help/{helpRequest}/session-ended
     * The tray reports the user closed AnyDesk / finished. Best-effort — MIS can
     * also end from the queue.
     */
    public function sessionEnded(Request $request, IctRemoteHelpRequest $helpRequest): JsonResponse
    {
        $this->assertOwned($request, $helpRequest);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'in:ended,canceled,failed'],
            'error'  => ['nullable', 'string', 'max:2000'],
        ]);

        if (! $helpRequest->isActive()) {
            return response()->json(['status' => 'ok']); // already finalized
        }

        $statusByReason = [
            'ended'    => IctRemoteHelpRequest::STATUS_COMPLETED,
            'canceled' => IctRemoteHelpRequest::STATUS_CANCELED,
            'failed'   => IctRemoteHelpRequest::STATUS_FAILED,
        ];

        $helpRequest->update([
            'status'        => $statusByReason[$validated['reason']],
            'end_reason'    => $validated['reason'],
            'ended_at'      => now(),
            'error_message' => $validated['error'] ?? $helpRequest->error_message,
        ]);

        $this->remoteHelp->logEvent($helpRequest, 'session_ended', null, $validated['reason']);

        return response()->json(['status' => 'ok']);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function assertOwned(Request $request, IctRemoteHelpRequest $helpRequest): void
    {
        if ($helpRequest->device_id !== $request->user()->id) {
            abort(403);
        }
    }

    private function trayView(IctRemoteHelpRequest $helpRequest): array
    {
        $queuePosition = null;
        if ($helpRequest->status === IctRemoteHelpRequest::STATUS_WAITING) {
            $queuePosition = IctRemoteHelpRequest::where('status', IctRemoteHelpRequest::STATUS_WAITING)
                ->where('id', '<=', $helpRequest->id)
                ->count();
        }

        return [
            'id'             => $helpRequest->id,
            'status'         => $helpRequest->status,
            'note'           => $helpRequest->note,
            'claimer_name'   => $helpRequest->claimed_by ? optional(User::find($helpRequest->claimed_by))->name : null,
            'queue_position' => $queuePosition,
            'created_at'     => $helpRequest->created_at?->toIso8601String(),
        ];
    }

    /**
     * Opens a documentation ITJR when the equipment has an owner. Best-effort:
     * a missing owner or a creation failure leaves it_job_request_id null and
     * the session proceeds regardless (shown as "no ticket" in the queue).
     */
    private function tryOpenTicket($device, ?ICTEquipment $equipment, ?string $note): ?int
    {
        $owner = $equipment?->owner_id ? User::find($equipment->owner_id) : null;
        if (! $owner) {
            return null;
        }

        try {
            $jobRequest = $this->jobRequests->createJobRequest([
                'category'         => $this->resolveTicketCategory(),
                'title'            => 'Remote help session: ' . ($equipment->description ?? $device->hostname),
                'description'      => trim(($note ? $note . ' ' : '') . "(Remote help requested via Atlas Sentinel on {$device->hostname})"),
                'priority'         => 'normal',
                'ict_equipment_id' => $equipment->id,
            ], $owner);

            return $jobRequest->id;
        } catch (\Throwable $e) {
            logger()->error('Atlas Sentinel: remote-help ticket creation failed', [
                'device_id' => $device->id,
                'error'     => $e->getMessage(),
            ]);
            return null;
        }
    }

    /** Prefer a load-balanced category; avoid "…on Events" (routes to one person). */
    private function resolveTicketCategory(): string
    {
        foreach (['Technical Assistance', 'Software', 'Hardware'] as $preferred) {
            if (ITJobCategory::where('name', $preferred)->exists()) {
                return $preferred;
            }
        }

        $fallback = ITJobCategory::orderBy('name')->value('name');
        if (! $fallback) {
            throw new \RuntimeException('No IT job categories configured.');
        }

        return $fallback;
    }

    private function notifyManagers(string $hostname): void
    {
        $managers = User::query()
            ->join('role_user', 'users.id', '=', 'role_user.user_id')
            ->join('permission_role', 'role_user.role_id', '=', 'permission_role.role_id')
            ->join('permissions', 'permission_role.permission_id', '=', 'permissions.id')
            ->where('permissions.name', 'atlas.sentinel.remote-help.manage')
            ->where('users.status', '<>', 'inactive')
            ->distinct()
            ->select('users.*')
            ->get();

        foreach ($managers as $manager) {
            NotificationService::notifyUser(
                $manager,
                'Remote Help',
                $hostname,
                'User is waiting for remote assistance',
                route('atlas-sentinel.remote-help.index'),
            );
        }
    }
}
