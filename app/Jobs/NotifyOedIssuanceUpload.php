<?php

namespace App\Jobs;

use App\Models\OedIssuance;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class NotifyOedIssuanceUpload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(public int $oedIssuanceId) {}

    public function handle(): void
    {
        $issuance = OedIssuance::with(['category', 'recipients'])->find($this->oedIssuanceId);

        if (! $issuance) {
            logger()->error('NotifyOedIssuanceUpload: issuance not found', [
                'oed_issuance_id' => $this->oedIssuanceId,
            ]);
            return;
        }

        $users = $issuance->recipient_type === 'all'
            ? User::where('status', '<>', 'inactive')->get()
            : User::where('status', '<>', 'inactive')
                ->whereIn('id', $issuance->recipients->pluck('user_id'))
                ->get();

        $sent = 0;
        foreach ($users as $user) {
            try {
                NotificationService::notifyUser(
                    $user,
                    'Knowledge Management',
                    $issuance->reference_no ?? ('OED-' . $issuance->id),
                    "New {$issuance->category?->label}: {$issuance->title}",
                    route('km.show', $issuance->id),
                );
                $sent++;
            } catch (\Throwable $e) {
                logger()->warning('NotifyOedIssuanceUpload: notify failed', [
                    'oed_issuance_id' => $issuance->id,
                    'user_id'         => $user->id,
                    'error'           => $e->getMessage(),
                ]);
            }
        }

        logger()->info('NotifyOedIssuanceUpload: complete', [
            'oed_issuance_id' => $issuance->id,
            'recipients'      => $users->count(),
            'sent'            => $sent,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        logger()->error('NotifyOedIssuanceUpload: job FAILED', [
            'oed_issuance_id' => $this->oedIssuanceId,
            'error'           => $e->getMessage(),
            'trace'           => $e->getTraceAsString(),
        ]);
    }
}
