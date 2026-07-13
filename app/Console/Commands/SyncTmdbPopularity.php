<?php

namespace App\Console\Commands;

use App\Services\TmdbAutoSyncSettings;
use App\Services\TmdbPopularitySyncService;
use Illuminate\Console\Command;

class SyncTmdbPopularity extends Command
{
    protected $signature = 'tmdb:sync-popularity {--force : Run even if auto-sync is disabled} {--only-missing : Update only rows without popularity}';

    protected $description = 'Sync TMDB popularity for series with tmdb_id';

    public function handle(TmdbPopularitySyncService $service): int
    {
        if (!$this->option('force') && !TmdbAutoSyncSettings::isEnabled()) {
            $this->info('TMDB auto-sync is disabled. Use --force to run manually.');

            return self::SUCCESS;
        }

        $result = $service->syncAll((bool)$this->option('only-missing'));

        foreach ($result['log'] as $line) {
            $this->line($line);
        }

        TmdbAutoSyncSettings::markRun();

        return $result['failed'] > 0 && $result['updated'] === 0 ? self::FAILURE : self::SUCCESS;
    }
}
