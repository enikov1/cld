<?php

namespace App\Console\Commands;

use App\Services\TmdbAutoSyncSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class TmdbAutoRunner extends Command
{
    protected $signature = 'tmdb:auto';

    protected $description = 'Run TMDB popularity + broadcast status sync once per day when enabled';

    public function handle(): int
    {
        if (!TmdbAutoSyncSettings::isEnabled()) {
            return self::SUCCESS;
        }

        if (!TmdbAutoSyncSettings::isDue()) {
            return self::SUCCESS;
        }

        $exitCode = Artisan::call('tmdb:sync-popularity', [
            '--force' => true,
            '--trigger' => 'schedule',
        ]);

        return $exitCode === 0 ? self::SUCCESS : self::FAILURE;
    }
}
