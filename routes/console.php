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

// ── Atlas Sentinel: expire manual "Fix Now" requests stuck 'delivered' with
//    no result reported (device went offline/crashed mid-flight) ─────────
Schedule::command('atlas-sentinel:expire-stale-remediations')->everyFifteenMinutes()->withoutOverlapping();

// ── Atlas Sentinel: proactively notify IT staff when a device stops
//    checking in, instead of relying on someone noticing the dashboard badge
Schedule::command('atlas-sentinel:notify-stale-devices')->everyFifteenMinutes()->withoutOverlapping();

