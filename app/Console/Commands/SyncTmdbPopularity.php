<?php

namespace App\Console\Commands;

use App\Services\TmdbAutoSyncSettings;
use App\Services\TmdbPopularitySyncService;
use Illuminate\Console\Command;

class SyncTmdbPopularity extends Command
{
    protected $signature = 'tmdb:sync-popularity
        {--force : Run even if auto-sync is disabled}
        {--only-missing : Update only rows without popularity}
        {--skip-schedule : Do not import episode schedules}';

    protected $description = 'Sync TMDB popularity, broadcast status and episode schedule for series with tmdb_id';

    public function handle(TmdbPopularitySyncService $service): int
    {
        if (!$this->option('force') && !TmdbAutoSyncSettings::isEnabled()) {
            $this->info('TMDB auto-sync is disabled. Use --force to run manually.');

            return self::SUCCESS;
        }

        $result = $service->syncAll(
            (bool)$this->option('only-missing'),
            !$this->option('skip-schedule'),
        );

        foreach ($result['log'] as $line) {
            $this->line($line);
        }

        TmdbAutoSyncSettings::markRun();

        return $result['failed'] > 0 && $result['updated'] === 0 ? self::FAILURE : self::SUCCESS;
    }
}
