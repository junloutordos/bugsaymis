<?php

namespace App\Console\Commands;

use App\Models\IctEquipmentDevice;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Console\Command;

/**
 * A device going silent (crashed, hung, powered off) is currently only
 * visible if someone happens to open the Agent Specs modal / health
 * dashboard and notices the "Stale"/"Offline?" badge. This sweeps for
 * devices past the stale threshold and notifies IT staff proactively,
 * same delivery channels (bell + real-time + web push) already used for
 * request-status updates elsewhere in the app.
 */
class AtlasSentinelNotifyStaleDevices extends Command
{
    protected $signature = 'atlas-sentinel:notify-stale-devices';
    protected $description = 'Notify IT staff when an enrolled device has not checked in past the stale threshold';

    // Matches the "Stale" badge threshold already used in the Agent Specs UI.
    const STALE_AFTER_MINUTES = 40;

    // Once notified, don't nag again for the same still-down device until
    // this much time has passed.
    const RENOTIFY_AFTER_HOURS = 6;

    public function handle(): int
    {
        $staleDevices = IctEquipmentDevice::with('equipment')
            ->where('last_checkin_at', '<', now()->subMinutes(self::STALE_AFTER_MINUTES))
            ->where(function ($query) {
                $query->whereNull('stale_notified_at')
                    ->orWhere('stale_notified_at', '<', now()->subHours(self::RENOTIFY_AFTER_HOURS));
            })
            ->get();

        if ($staleDevices->isEmpty()) {
            $this->info('No stale devices to notify.');
            return self::SUCCESS;
        }

        $recipients = User::with('roles.permissions')
            ->where('status', '<>', 'inactive')
            ->get()
            ->filter(fn (User $user) => $user->hasPermission('it.equipment.manage'));

        foreach ($staleDevices as $device) {
            $label = $device->equipment?->description ?? $device->hostname ?? "Device #{$device->id}";
            $minutesSince = $device->last_checkin_at ? now()->diffInMinutes($device->last_checkin_at) : null;

            foreach ($recipients as $user) {
                NotificationService::notifyUser(
                    user: $user,
                    requestType: 'Atlas Sentinel Device',
                    referenceNo: $label,
                    newStatus: $minutesSince !== null
                        ? "No checkin for {$minutesSince} min (last: {$device->last_checkin_at->format('M j, g:i A')})"
                        : 'No checkin recorded',
                    url: route('atlas-sentinel.health-dashboard'),
                );
            }

            $device->update(['stale_notified_at' => now()]);
        }

        $this->info("Notified {$recipients->count()} recipient(s) about {$staleDevices->count()} stale device(s).");

        return self::SUCCESS;
    }
}
