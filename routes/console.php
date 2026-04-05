<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Scheduled commands
Schedule::command('feedlink:expire-listings')
    ->everyFiveMinutes()
    ->withoutOverlapping();

Schedule::command('feedlink:expire-requests')
    ->everyFiveMinutes()
    ->withoutOverlapping();
