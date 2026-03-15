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
Schedule::command('db:backup')->dailyAt('08:00'); // 8 AM
Schedule::command('db:backup')->dailyAt('12:00'); // 12 NN
Schedule::command('db:backup')->dailyAt('17:00'); // 5 PM

