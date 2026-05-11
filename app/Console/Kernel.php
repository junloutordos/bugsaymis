<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        \App\Console\Commands\BackupDatabase::class,
        \App\Console\Commands\SyncRuijieCloud::class,
    ];

    protected function schedule(Schedule $schedule)
    {
        // Database backups
        $schedule->command('db:backup')->dailyAt('12:00');
        $schedule->command('db:backup')->dailyAt('17:00');

        // Ruijie Cloud wireless client sync — every 10 minutes
        $schedule->command('network:sync-ruijie-cloud')->everyTenMinutes();
    }

    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
