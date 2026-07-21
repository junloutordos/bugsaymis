<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Tasks
|--------------------------------------------------------------------------
*/
//Schedule::command('db:backup')->everyMinute();
Schedule::command('db:backup')->dailyAt('06:00'); // 6 AM — early window, survives fresh deploys
Schedule::command('db:backup')->dailyAt('08:00'); // 8 AM
Schedule::command('db:backup')->dailyAt('12:00'); // 12 NN
Schedule::command('db:backup')->dailyAt('17:00'); // 5 PM

// ── HR: auto-generate DTR records for yesterday at 12:05 AM ──────────────
Schedule::command('hr:dtr:daily')->dailyAt('00:05')->withoutOverlapping();

// ── Payroll: accrue monthly leave credits on the 1st of each month ───────
Schedule::command('payroll:accrue-leave')->monthlyOn(1, '00:10')->withoutOverlapping();

// ── Log health check: disk, file sizes, error spikes ─────────────────────
Schedule::command('log:health')->dailyAt('06:00');

// ── Backup recency check: was a backup uploaded to Drive in the last 25h? ─
Schedule::command('backup:verify')->dailyAt('06:30')->withoutOverlapping();

// ── PDS: notify employees whose PDS is overdue for its annual update ─────
Schedule::command('pds:notify-annual-update')->dailyAt('07:00')->withoutOverlapping();

// ── Computer Laboratories: reconcile priority reservations with any class
//    schedule changes made through bulk imports/restores that bypass model events.
Schedule::command('computer-labs:sync')->everyThirtyMinutes()->withoutOverlapping();

// ── Atlas Sentinel: expire manual "Fix Now" requests stuck 'delivered' with
//    no result reported (device went offline/crashed mid-flight) ─────────
Schedule::command('atlas-sentinel:expire-stale-remediations')->everyFifteenMinutes()->withoutOverlapping();

// Atlas Sentinel stale-device bell notifications removed 2026-07-17 (offline /
// not-reporting devices were spamming IT staff). The command still exists for
// manual runs: php artisan atlas-sentinel:notify-stale-devices

// ── Atlas Sentinel: time out unclaimed/overlong remote-help requests and
//    force-finalize sessions a dark agent never acked (must run often so an
//    abandoned request rotates its one-time password promptly) ─────────────
Schedule::command('atlas-sentinel:expire-remote-help')->everyMinute()->withoutOverlapping();

// NOTE: no pulse:trim schedule — the command does not exist. Pulse trims old
// entries itself from the pulse:work daemon (per pulse.storage.trim.keep).
