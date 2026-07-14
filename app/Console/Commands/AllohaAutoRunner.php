<?php

namespace App\Console\Commands;

use App\Services\AllohaAutoSyncSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class AllohaAutoRunner extends Command
{
    protected $signature = 'alloha:auto';

    protected $description = 'Run Alloha auto-sync when due (latest + optional existing sync)';

    public function handle(): int
    {
        if (!AllohaAutoSyncSettings::isEnabled()) {
            return self::SUCCESS;
        }

        if (!AllohaAutoSyncSettings::isDue()) {
            return self::SUCCESS;
        }

        $exitCode = Artisan::call('alloha:latest', [
            '--force' => true,
            '--trigger' => 'schedule',
        ]);

        return $exitCode === 0 ? self::SUCCESS : self::FAILURE;
    }
}
