<?php

namespace App\Services;

use App\Models\IctRemoteHelpEvent;
use App\Models\IctRemoteHelpRequest;

/**
 * Shared helpers for the attended "Ask for Remote Help" flow. With AnyDesk the
 * session is entirely tray-driven — the tray reports the AnyDesk ID and the
 * user accepts the incoming connection — so there is no checkin command to
 * dispatch to the SYSTEM service. This just centralizes the feature flag and
 * the audit log.
 */
class AtlasSentinelRemoteHelpService
{
    public function enabled(): bool
    {
        return (bool) config('atlas_sentinel.remote_help.enabled', false);
    }

    public function logEvent(IctRemoteHelpRequest $request, string $event, ?int $userId = null, ?string $details = null): void
    {
        IctRemoteHelpEvent::create([
            'request_id' => $request->id,
            'user_id'    => $userId,
            'event'      => $event,
            'details'    => $details,
        ]);
    }
}
