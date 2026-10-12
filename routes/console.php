<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('subscriptions:expire')->hourly()->withoutOverlapping();
Schedule::command('subscriptions:remind')->dailyAt('09:00')->withoutOverlapping();
Schedule::command('backup:run')->dailyAt('03:00')->withoutOverlapping();
