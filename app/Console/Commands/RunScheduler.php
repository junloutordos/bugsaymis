<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;

class RunScheduler extends Command
{
    protected $signature = 'scheduler:work';
    protected $description = 'Run Laravel scheduler continuously for Windows without Task Scheduler';

    public function handle()
    {
        $this->info('Scheduler started. Press Ctrl+C to stop.');

        while (true) {
            try {
                // Run all scheduled tasks
                Artisan::call('schedule:run');
                $this->info('Checked scheduler at: ' . now());

            } catch (\Exception $e) {
                $this->error('Scheduler error: ' . $e->getMessage());
                Log::error('Scheduler error: ' . $e->getMessage());
            }

            // Sleep for 60 seconds
            sleep(60);
        }
    }
}
