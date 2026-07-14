<?php

namespace App\Console\Commands;

use App\Models\CronRun;
use App\Services\CronRunLogger;
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
        {--fill-logos : Also refill missing studio logos from TMDB}
        {--trigger= : schedule|admin|cli}';

    protected $description = 'Sync TMDB popularity, broadcast status, episode schedule and studios for series with tmdb_id (batched)';

    public function handle(TmdbPopularitySyncService $service, TmdbStudioSyncService $studioSync): int
    {
        if (!$this->option('force') && !TmdbAutoSyncSettings::isEnabled()) {
            $this->info('TMDB auto-sync is disabled. Use --force to run manually.');

            return self::SUCCESS;
        }

        $batch = max(1, min(100, (int)$this->option('batch')));
        $trigger = CronRunLogger::detectTrigger($this->option('trigger'));
        if ($trigger === CronRun::TRIGGER_CLI && $this->option('force') && !$this->input->isInteractive()) {
            $trigger = CronRun::TRIGGER_SCHEDULE;
        }

        $run = CronRunLogger::run(
            CronRunLogger::JOB_TMDB_POPULARITY,
            'tmdb:sync-popularity',
            $trigger,
            function () use ($service, $studioSync, $batch) {
                $result = $service->syncAll(
                    (bool)$this->option('only-missing'),
                    !$this->option('skip-schedule'),
                    $batch,
                );

                foreach ($result['log'] as $line) {
                    $this->line($line);
                }

                $logoCounts = null;
                if ($this->option('fill-logos')) {
                    $logos = $studioSync->fillMissingLogos(500);
                    $logoCounts = $logos;
                    $this->line(sprintf(
                        'Логотипы: проверено %d, скачано %d, без лого %d',
                        $logos['checked'],
                        $logos['downloaded'],
                        $logos['failed'],
                    ));
                }

                TmdbAutoSyncSettings::markRun();

                $counts = [
                    'updated' => $result['updated'] ?? 0,
                    'failed' => $result['failed'] ?? 0,
                    'skipped' => $result['skipped'] ?? 0,
                    'status_changed' => $result['status_changed'] ?? 0,
                    'schedule_synced' => $result['schedule_synced'] ?? 0,
                    'schedule_failed' => $result['schedule_failed'] ?? 0,
                    'studios_linked' => $result['studios_linked'] ?? 0,
                    'studio_logos' => ($result['studio_logos'] ?? 0) + ($result['logos_filled'] ?? 0),
                ];
                if ($logoCounts) {
                    $counts['logos_checked'] = $logoCounts['checked'];
                    $counts['logos_downloaded'] = $logoCounts['downloaded'];
                    $counts['logos_failed'] = $logoCounts['failed'];
                }

                $failed = (int)($counts['failed'] ?? 0);
                $updated = (int)($counts['updated'] ?? 0);
                $message = sprintf(
                    'Обновлено: %d, пропущено: %d, ошибок: %d, статусов: %d, расписаний: %d',
                    $updated,
                    (int)($counts['skipped'] ?? 0),
                    $failed,
                    (int)($counts['status_changed'] ?? 0),
                    (int)($counts['schedule_synced'] ?? 0),
                );

                return [
                    'status' => ($failed > 0 && $updated === 0) ? CronRun::STATUS_FAILED : CronRun::STATUS_SUCCESS,
                    'counts' => $counts,
                    'message' => $message,
                    'log' => $result['log'] ?? [],
                ];
            },
            [
                'batch' => $batch,
                'only_missing' => (bool)$this->option('only-missing'),
                'skip_schedule' => (bool)$this->option('skip-schedule'),
                'fill_logos' => (bool)$this->option('fill-logos'),
            ],
            'Синхронизация TMDB',
        );

        $this->info((string)$run->message);

        return $run->status === CronRun::STATUS_FAILED ? self::FAILURE : self::SUCCESS;
    }
}
