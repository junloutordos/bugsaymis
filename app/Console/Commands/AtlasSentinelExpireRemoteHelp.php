<?php

namespace App\Console\Commands;

use App\Models\IctRemoteHelpRequest;
use App\Services\AtlasSentinelRemoteHelpService;
use Illuminate\Console\Command;

/**
 * Queue hygiene for the attended AnyDesk remote-help flow. Two sweeps, both
 * finalizing directly (there is no password to rotate and no agent ack to wait
 * for):
 *
 *  1. Waiting too long → timed_out (nobody picked it up).
 *  2. Claimed/in-session past the hard ceiling → timed_out (session left open).
 */
class AtlasSentinelExpireRemoteHelp extends Command
{
    protected $signature = 'atlas-sentinel:expire-remote-help';
    protected $description = 'Time out unclaimed or overlong remote-help requests';

    public function handle(AtlasSentinelRemoteHelpService $remoteHelp): int
    {
        $waitingTimeout = (int) config('atlas_sentinel.remote_help.waiting_timeout_minutes', 15);
        $sessionMax     = (int) config('atlas_sentinel.remote_help.session_max_minutes', 120);

        $staleWaiting = IctRemoteHelpRequest::where('status', IctRemoteHelpRequest::STATUS_WAITING)
            ->where('created_at', '<', now()->subMinutes($waitingTimeout))
            ->get();

        foreach ($staleWaiting as $req) {
            $req->update([
                'status'     => IctRemoteHelpRequest::STATUS_TIMED_OUT,
                'end_reason' => 'timed_out',
                'ended_at'   => now(),
            ]);
            $remoteHelp->logEvent($req, 'timed_out_waiting');
        }

        $overlong = IctRemoteHelpRequest::whereIn('status', [IctRemoteHelpRequest::STATUS_CLAIMED, IctRemoteHelpRequest::STATUS_IN_SESSION])
            ->where('created_at', '<', now()->subMinutes($sessionMax))
            ->get();

        foreach ($overlong as $req) {
            $req->update([
                'status'     => IctRemoteHelpRequest::STATUS_TIMED_OUT,
                'end_reason' => 'timed_out',
                'ended_at'   => now(),
            ]);
            $remoteHelp->logEvent($req, 'session_ceiling_reached');
        }

        $this->info("Remote-help sweep: {$staleWaiting->count()} timed out (waiting), {$overlong->count()} ceiling-hit.");

        return self::SUCCESS;
    }
}
