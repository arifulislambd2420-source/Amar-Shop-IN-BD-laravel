<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Shared hosting has no long-running worker: one cron entry runs
// `php artisan schedule:run` every minute (see DEPLOY.md), and this drains the
// queue (customer SMS, courier calls) on each tick, then exits.
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')
    ->everyMinute()
    ->withoutOverlapping(5)
    ->runInBackground();

// Keep the failed-jobs table from growing forever.
Schedule::command('queue:prune-failed --hours=720')->daily();