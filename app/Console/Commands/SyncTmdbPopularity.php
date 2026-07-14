<?php

namespace App\Console\Commands;

use App\Services\TmdbAutoSyncSettings;
use App\Services\TmdbPopularitySyncService;
use App\Services\TmdbStudioSyncService;
use Illuminate\Console\Command;

class SyncTmdbPopularity extends Command
{
    protected $signature = 'tmdb:sync-popularity
        {--force : Run even if auto-sync is disabled}
        {--only-missing : Update only rows without popularity}
        {--skip-schedule : Do not import episode schedules}
        {--batch=25 : Series per chunk}
        {--fill-logos : Also refill missing studio logos from TMDB}';

    protected $description = 'Sync TMDB popularity, broadcast status, episode schedule and studios for series with tmdb_id (batched)';

    public function handle(TmdbPopularitySyncService $service, TmdbStudioSyncService $studioSync): int
    {
        if (!$this->option('force') && !TmdbAutoSyncSettings::isEnabled()) {
            $this->info('TMDB auto-sync is disabled. Use --force to run manually.');

            return self::SUCCESS;
        }

        $batch = max(1, min(100, (int)$this->option('batch')));

        $result = $service->syncAll(
            (bool)$this->option('only-missing'),
            !$this->option('skip-schedule'),
            $batch,
        );

        foreach ($result['log'] as $line) {
            $this->line($line);
        }

        if ($this->option('fill-logos')) {
            $logos = $studioSync->fillMissingLogos(500);
            $this->line(sprintf(
                'Логотипы: проверено %d, скачано %d, без лого %d',
                $logos['checked'],
                $logos['downloaded'],
                $logos['failed'],
            ));
        }

        TmdbAutoSyncSettings::markRun();

        return $result['failed'] > 0 && $result['updated'] === 0 ? self::FAILURE : self::SUCCESS;
    }
}
