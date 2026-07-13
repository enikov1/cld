<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('alloha:auto')->everyMinute();
Schedule::command('tmdb:auto')->everyMinute();
Schedule::command('series:refresh-popularity-badges')->hourly();
Schedule::command('sitemap:generate')->everyThirtyMinutes();
