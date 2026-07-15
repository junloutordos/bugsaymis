<?php

namespace App\Console\Commands;

use App\Models\Pds;
use App\Services\NotificationService;
use Illuminate\Console\Command;

/**
 * CSC requires employees to keep their PDS current, ideally reviewed/
 * resubmitted annually. Nothing previously nudged employees whose PDS had
 * gone stale — this sweeps for PDS records last submitted over a year ago
 * and notifies the owner once per overdue period (a fresh submission
 * resets the clock and clears the "already reminded" flag).
 */
class NotifyPdsAnnualUpdate extends Command
{
    protected $signature = 'pds:notify-annual-update';
    protected $description = 'Notify employees whose Personal Data Sheet is overdue for its annual update';

    public function handle(): int
    {
        $overdue = Pds::with('user')
            ->whereNotNull('submitted_at')
            ->where('submitted_at', '<', now()->subYear())
            ->where(function ($query) {
                $query->whereNull('annual_reminder_sent_at')
                    ->orWhereColumn('annual_reminder_sent_at', '<', 'submitted_at');
            })
            ->get();

        if ($overdue->isEmpty()) {
            $this->info('No overdue PDS records to notify.');
            return self::SUCCESS;
        }

        foreach ($overdue as $pds) {
            if (! $pds->user) {
                continue;
            }

            NotificationService::notifyUser(
                user: $pds->user,
                requestType: 'Personal Data Sheet',
                referenceNo: 'PDS',
                newStatus: 'Annual update required',
                url: route('pds.my'),
                remarks: "Your PDS was last submitted on {$pds->submitted_at->format('M j, Y')}. Please review and resubmit it.",
            );

            $pds->update(['annual_reminder_sent_at' => now()]);
        }

        $this->info("Notified {$overdue->count()} employee(s) about their overdue PDS.");

        return self::SUCCESS;
    }
}
