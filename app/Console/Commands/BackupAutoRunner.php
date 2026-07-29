<?php

namespace App\Console\Commands;

use App\Services\BackupSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class BackupAutoRunner extends Command
{
    protected $signature = 'backup:auto';

    protected $description = 'Run scheduled backup when due';

    public function handle(): int
    {
        if (!BackupSettings::isEnabled()) {
            return self::SUCCESS;
        }

        if (!BackupSettings::isDue()) {
            return self::SUCCESS;
        }

        $exitCode = Artisan::call('backup:run', [
            '--trigger' => 'schedule',
        ]);

        return $exitCode === 0 ? self::SUCCESS : self::FAILURE;
    }
}
