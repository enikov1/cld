<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('alloha:auto')->everyMinute()->withoutOverlapping(1800);
Schedule::command('tmdb:auto')->everyMinute()->withoutOverlapping(1800);
Schedule::command('series:refresh-popularity-badges', ['--trigger' => 'schedule'])->hourly()->withoutOverlapping(30);
Schedule::command('sitemap:generate', ['--trigger' => 'schedule'])->everyThirtyMinutes()->withoutOverlapping(30);
Schedule::command('cache:prune-expired')->everyFiveMinutes()->withoutOverlapping(10);

