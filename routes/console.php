<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Shared hosting has no Supervisor: cron runs schedule:run every minute and drains the queue.
Schedule::command('queue:work --stop-when-empty --max-time=55 --tries=3 --timeout=50')
    ->everyMinute()
    ->withoutOverlapping(5);
