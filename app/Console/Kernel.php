<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        \App\Console\Commands\BackupDatabase::class,
    ];

    protected function schedule(Schedule $schedule)
    {
        // Run daily at 12:00 NN
        $schedule->command('db:backup')->dailyAt('12:00');

        // Run daily at 5:00 PM
        $schedule->command('db:backup')->dailyAt('17:00');
    }

    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
