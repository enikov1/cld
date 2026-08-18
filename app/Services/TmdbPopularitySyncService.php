<?php

namespace App\Services;

use App\Models\CronRun;
use App\Models\Series;
use App\Support\ContentTypes;
use App\Support\TplCache;

class TmdbPopularitySyncService
{
    private const DETAILS_APPEND = ['content_ratings', 'release_dates'];

    public function __construct(
        private readonly TmdbClient $client,
        private readonly TmdbScheduleImportService $scheduleImport,
        private readonly TmdbStudioSyncService $studioSync,
    ) {
    }

    /**
     * @return array{
     *     updated: int,
     *     failed: int,
     *     skipped: int,
     *     status_changed: int,
     *     schedule_synced: int,
     *     schedule_failed: int,
     *     studios_linked: int,
     *     studio_logos: int,
     *     logos_filled: int,
     *     log: list<string>
     * }
     */
    public function syncAll(bool $onlyMissing = false, bool $syncSchedule = true, ?int $batchSize = null): array
    {
        $result = [
            'updated' => 0,
            'failed' => 0,
            'skipped' => 0,
            'status_changed' => 0,
            'schedule_synced' => 0,
            'schedule_failed' => 0,
            'studios_linked' => 0,
            'studio_logos' => 0,
            'logos_filled' => 0,
            'log' => [],
        ];

        if (!$this->client->isConfigured()) {
            $result['log'][] = 'API-ключ TMDB не настроен';

            return $result;
        }

        $batchSize = $batchSize ?? (int)config('tmdb.sync_batch_size', 25);
        $batchSize = max(1, min(100, $batchSize));
        $afterId = 0;

        $total = Series::query()
            ->whereNotNull('tmdb_id')
            ->where('tmdb_id', '!=', '')
            ->when($onlyMissing, fn ($q) => $q->whereNull('tmdb_popularity'))
            ->count();

        $result['log'][] = 'Сериалов с TMDB ID: ' . $total;

        while (true) {
            $batch = $this->syncBatch(
                afterId: $afterId,
                limit: $batchSize,
                onlyMissing: $onlyMissing,
                syncSchedule: $syncSchedule,
                rateLimit: true,
            );

            $result['updated'] += $batch['updated'];
            $result['failed'] += $batch['failed'];
            $result['skipped'] += $batch['skipped'];
            $result['status_changed'] += $batch['status_changed'];
            $result['schedule_synced'] += $batch['schedule_synced'];
            $result['schedule_failed'] += $batch['schedule_failed'];
            $result['studios_linked'] += $batch['studios_linked'];
            $result['studio_logos'] += $batch['studio_logos'];

            if ($batch['done'] || $batch['last_id'] <= $afterId) {
                break;
            }

            $afterId = $batch['last_id'];
        }

        $logos = $this->studioSync->fillMissingLogos(200);
        $result['logos_filled'] = $logos['downloaded'];
        if ($logos['checked'] > 0) {
            $result['log'][] = sprintf(
                'Дозагрузка логотипов студий: проверено %d, скачано %d, без лого %d',
                $logos['checked'],
                $logos['downloaded'],
                $logos['failed'],
            );
        }

        $result['log'][] = sprintf(
            'Готово: обновлено %d (статус изменён %d, расписание %d, студии %d, логотипы %d), пропущено %d, ошибок %d (расписание %d)',
            $result['updated'],
            $result['status_changed'],
            $result['schedule_synced'],
            $result['studios_linked'],
            $result['studio_logos'] + $result['logos_filled'],
            $result['skipped'],
            $result['failed'],
            $result['schedule_failed'],
        );

        return $result;
    }

    /**
     * Process one batch for progressive admin sync.
     *
     * @return array{
     *     updated: int,
     *     failed: int,
     *     skipped: int,
     *     status_changed: int,
     *     schedule_synced: int,
     *     schedule_failed: int,
     *     studios_linked: int,
     *     studio_logos: int,
     *     processed: int,
     *     last_id: int,
     *     done: bool,
     *     remaining: int
     * }
     */
    public function syncBatch(
        int $afterId = 0,
        int $limit = 25,
        bool $onlyMissing = false,
        bool $syncSchedule = true,
        bool $rateLimit = true,
    ): array {
        $limit = max(1, min(100, $limit));

        $query = Series::query()
            ->whereNotNull('tmdb_id')
            ->where('tmdb_id', '!=', '')
            ->where('id', '>', $afterId)
            ->orderBy('id')
            ->limit($limit);

        if ($onlyMissing) {
            $query->whereNull('tmdb_popularity');
        }

        $seriesList = $query->get();

        $out = [
            'updated' => 0,
            'failed' => 0,
            'skipped' => 0,
            'status_changed' => 0,
            'schedule_synced' => 0,
            'schedule_failed' => 0,
            'studios_linked' => 0,
            'studio_logos' => 0,
            'processed' => 0,
            'last_id' => $afterId,
            'done' => true,
            'remaining' => 0,
        ];

        foreach ($seriesList as $series) {
            $outcome = $this->syncSeries($series, $syncSchedule, $rateLimit);
            $out['updated'] += $outcome['updated'] ? 1 : 0;
            $out['failed'] += $outcome['failed'] ? 1 : 0;
            $out['skipped'] += $outcome['skipped'] ? 1 : 0;
            $out['status_changed'] += $outcome['status_changed'] ? 1 : 0;
            $out['schedule_synced'] += $outcome['schedule_synced'] ? 1 : 0;
            $out['schedule_failed'] += $outcome['schedule_failed'] ? 1 : 0;
            $out['studios_linked'] += $outcome['studios_linked'];
            $out['studio_logos'] += $outcome['studio_logos'];
            $out['processed']++;
            $out['last_id'] = (int)$series->id;
        }

        $out['remaining'] = Series::query()
            ->whereNotNull('tmdb_id')
            ->where('tmdb_id', '!=', '')
            ->where('id', '>', $out['last_id'])
            ->when($onlyMissing, fn ($q) => $q->whereNull('tmdb_popularity'))
            ->count();

        $out['done'] = $out['remaining'] === 0;

        return $out;
    }

    /**
     * Start or continue progressive sync; returns updated progress snapshot.
     *
     * @return array<string, mixed>
     */
    public function runProgressiveBatch(bool $restart = false, bool $syncSchedule = true): array
    {
        if (!$this->client->isConfigured()) {
            return array_merge(TmdbSyncProgress::get(), [
                'status' => 'failed',
                'message' => 'API-ключ TMDB не настроен',
            ]);
        }

        @set_time_limit(120);

        $progress = TmdbSyncProgress::get();
        $batchSize = max(1, min(100, (int)config('tmdb.sync_batch_size', 25)));

        if ($restart || $progress['status'] !== 'running') {
            $total = Series::query()
                ->whereNotNull('tmdb_id')
                ->where('tmdb_id', '!=', '')
                ->count();

            $cronRun = CronRunLogger::start(
                CronRunLogger::JOB_TMDB_POPULARITY,
                'tmdb:sync-popularity',
                CronRun::TRIGGER_ADMIN,
                ['mode' => 'progressive', 'total' => $total],
                'Прогрессивная синхронизация TMDB',
            );

            $progress = TmdbSyncProgress::normalize([
                'status' => 'running',
                'after_id' => 0,
                'total' => $total,
                'processed' => 0,
                'updated' => 0,
                'failed' => 0,
                'status_changed' => 0,
                'schedule_synced' => 0,
                'studios_linked' => 0,
                'studio_logos' => 0,
                'message' => 'Синхронизация запущена',
                'started_at' => time(),
                'finished_at' => null,
                'cron_run_id' => $cronRun->id,
            ]);
            TmdbSyncProgress::save($progress);
        }

        $batch = $this->syncBatch(
            afterId: (int)$progress['after_id'],
            limit: $batchSize,
            onlyMissing: false,
            syncSchedule: $syncSchedule,
            rateLimit: true,
        );

        $progress['after_id'] = $batch['last_id'];
        $progress['processed'] += $batch['processed'];
        $progress['updated'] += $batch['updated'];
        $progress['failed'] += $batch['failed'];
        $progress['status_changed'] += $batch['status_changed'];
        $progress['schedule_synced'] += $batch['schedule_synced'];
        $progress['studios_linked'] += $batch['studios_linked'];
        $progress['studio_logos'] += $batch['studio_logos'];
        $progress['message'] = sprintf(
            'Обработано %d из %d',
            $progress['processed'],
            max($progress['total'], $progress['processed']),
        );

        if ($batch['done']) {
            $logos = $this->studioSync->fillMissingLogos(200);
            $progress['studio_logos'] += $logos['downloaded'];
            $progress['status'] = 'done';
            $progress['finished_at'] = time();
            $progress['message'] = sprintf(
                'Готово: обновлено %d, ошибок %d, логотипов %d',
                $progress['updated'],
                $progress['failed'],
                $progress['studio_logos'],
            );
            if (((int) $progress['failed'] === 0) || ((int) $progress['updated'] > 0)) {
                TmdbAutoSyncSettings::markRun();
            }

            if (!empty($progress['cron_run_id'])) {
                $cronRun = CronRun::query()->find((int)$progress['cron_run_id']);
                if ($cronRun && $cronRun->status === CronRun::STATUS_RUNNING) {
                    CronRunLogger::finish(
                        $cronRun,
                        ((int)$progress['failed'] > 0 && (int)$progress['updated'] === 0)
                            ? CronRun::STATUS_FAILED
                            : CronRun::STATUS_SUCCESS,
                        [
                            'processed' => (int)$progress['processed'],
                            'total' => (int)$progress['total'],
                            'updated' => (int)$progress['updated'],
                            'failed' => (int)$progress['failed'],
                            'status_changed' => (int)$progress['status_changed'],
                            'schedule_synced' => (int)$progress['schedule_synced'],
                            'studios_linked' => (int)$progress['studios_linked'],
                            'studio_logos' => (int)$progress['studio_logos'],
                        ],
                        (string)$progress['message'],
                    );
                }
            }
        } else {
            $progress['status'] = 'running';
        }

        TmdbSyncProgress::save($progress);

        return $progress;
    }

    /**
     * @return array{
     *     updated: bool,
     *     failed: bool,
     *     skipped: bool,
     *     status_changed: bool,
     *     schedule_synced: bool,
     *     schedule_failed: bool,
     *     studios_linked: int,
     *     studio_logos: int
     * }
     */
    public function syncSeries(Series $series, bool $syncSchedule = true, bool $rateLimit = true): array
    {
        $empty = [
            'updated' => false,
            'failed' => false,
            'skipped' => false,
            'status_changed' => false,
            'schedule_synced' => false,
            'schedule_failed' => false,
            'studios_linked' => 0,
            'studio_logos' => 0,
        ];

        $tmdbId = trim((string)$series->tmdb_id);
        if ($tmdbId === '') {
            $empty['skipped'] = true;

            return $empty;
        }

        if (!$this->client->isConfigured()) {
            $empty['skipped'] = true;

            return $empty;
        }

        $preferTv = ContentTypes::isSerialLike($series->content_type);
        $details = $preferTv
            ? $this->client->getTvDetails($tmdbId, self::DETAILS_APPEND)
            : $this->client->getMovieDetails($tmdbId, self::DETAILS_APPEND);

        $usedTvEndpoint = $preferTv;

        if ($details === [] || !isset($details['popularity'])) {
            $details = $preferTv
                ? $this->client->getMovieDetails($tmdbId, self::DETAILS_APPEND)
                : $this->client->getTvDetails($tmdbId, self::DETAILS_APPEND);
            $usedTvEndpoint = !$preferTv;
        }

        if ($details === [] || !isset($details['popularity'])) {
            $empty['failed'] = true;

            return $empty;
        }

        $oldStatus = $series->broadcast_status;

        $series->tmdb_popularity = round((float)$details['popularity'], 4);
        $series->tmdb_popularity_refreshed_at = now();

        $mappedStatus = TmdbBroadcastStatusMapper::fromDetails(
            $details,
            $usedTvEndpoint ? 'series' : 'film',
        );
        $nextStatus = TmdbBroadcastStatusMapper::resolve($series->broadcast_status, $mappedStatus);
        if ($nextStatus !== null) {
            $series->broadcast_status = $nextStatus;
        }

        TmdbMapper::applyAirMetadata($series, $details, $usedTvEndpoint);
        $series->save();

        $statusChanged = $oldStatus !== $series->broadcast_status;
        $scheduleSynced = false;
        $scheduleFailed = false;

        if ($syncSchedule && $usedTvEndpoint) {
            $schedule = $this->scheduleImport->syncMergedToDatabase($series->fresh(), $details);
            if ($schedule['ok']) {
                $scheduleSynced = true;
                $series->refresh();
                TmdbMapper::applyAirMetadata($series, $details, true, [
                    'first_air_date' => $schedule['first_air_date'] ?? null,
                    'last_air_date' => $schedule['last_air_date'] ?? null,
                    'total_runtime' => $schedule['total_runtime'] ?? null,
                ]);
                $series->save();
            } else {
                $scheduleFailed = true;
            }
        }

        $studioResult = $this->studioSync->syncFromDetails($series->fresh(), $details, $usedTvEndpoint);

        TplCache::forgetSeries($series->id);

        if ($rateLimit) {
            usleep(250000);
        }

        return [
            'updated' => true,
            'failed' => false,
            'skipped' => false,
            'status_changed' => $statusChanged,
            'schedule_synced' => $scheduleSynced,
            'schedule_failed' => $scheduleFailed,
            'studios_linked' => $studioResult['linked'],
            'studio_logos' => $studioResult['logos'],
        ];
    }
}
