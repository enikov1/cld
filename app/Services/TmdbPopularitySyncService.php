<?php

namespace App\Services;

use App\Models\Series;
use App\Support\TplCache;

class TmdbPopularitySyncService
{
    public function __construct(
        private readonly TmdbClient $client,
        private readonly TmdbScheduleImportService $scheduleImport,
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
     *     log: list<string>
     * }
     */
    public function syncAll(bool $onlyMissing = false, bool $syncSchedule = true): array
    {
        $result = [
            'updated' => 0,
            'failed' => 0,
            'skipped' => 0,
            'status_changed' => 0,
            'schedule_synced' => 0,
            'schedule_failed' => 0,
            'log' => [],
        ];

        if (!$this->client->isConfigured()) {
            $result['log'][] = 'API-ключ TMDB не настроен';

            return $result;
        }

        $query = Series::query()
            ->whereNotNull('tmdb_id')
            ->where('tmdb_id', '!=', '');

        if ($onlyMissing) {
            $query->whereNull('tmdb_popularity');
        }

        $seriesList = $query->orderBy('id')->get();
        $result['log'][] = 'Сериалов с TMDB ID: ' . $seriesList->count();

        foreach ($seriesList as $series) {
            $outcome = $this->syncSeries($series, $syncSchedule);
            $result['updated'] += $outcome['updated'] ? 1 : 0;
            $result['failed'] += $outcome['failed'] ? 1 : 0;
            $result['skipped'] += $outcome['skipped'] ? 1 : 0;
            $result['status_changed'] += $outcome['status_changed'] ? 1 : 0;
            $result['schedule_synced'] += $outcome['schedule_synced'] ? 1 : 0;
            $result['schedule_failed'] += $outcome['schedule_failed'] ? 1 : 0;
        }

        $result['log'][] = sprintf(
            'Готово: обновлено %d (статус изменён %d, расписание %d), пропущено %d, ошибок %d (расписание %d)',
            $result['updated'],
            $result['status_changed'],
            $result['schedule_synced'],
            $result['skipped'],
            $result['failed'],
            $result['schedule_failed'],
        );

        return $result;
    }

    /**
     * @return array{
     *     updated: bool,
     *     failed: bool,
     *     skipped: bool,
     *     status_changed: bool,
     *     schedule_synced: bool,
     *     schedule_failed: bool
     * }
     */
    public function syncSeries(Series $series, bool $syncSchedule = true): array
    {
        $empty = [
            'updated' => false,
            'failed' => false,
            'skipped' => false,
            'status_changed' => false,
            'schedule_synced' => false,
            'schedule_failed' => false,
        ];

        $tmdbId = trim((string)$series->tmdb_id);
        if ($tmdbId === '') {
            $empty['skipped'] = true;

            return $empty;
        }

        $preferTv = $series->content_type !== 'film';
        $details = $preferTv
            ? $this->client->getTvDetails($tmdbId)
            : $this->client->getMovieDetails($tmdbId);

        $usedTvEndpoint = $preferTv;

        if ($details === [] || !isset($details['popularity'])) {
            $details = $preferTv
                ? $this->client->getMovieDetails($tmdbId)
                : $this->client->getTvDetails($tmdbId);
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

        $series->save();

        $statusChanged = $oldStatus !== $series->broadcast_status;
        $scheduleSynced = false;
        $scheduleFailed = false;

        if ($syncSchedule && $usedTvEndpoint) {
            $schedule = $this->scheduleImport->syncMergedToDatabase($series->fresh(), $details);
            if ($schedule['ok']) {
                $scheduleSynced = true;
            } else {
                $scheduleFailed = true;
            }
        }

        TplCache::forgetSeries($series->id);

        usleep(300000);

        return [
            'updated' => true,
            'failed' => false,
            'skipped' => false,
            'status_changed' => $statusChanged,
            'schedule_synced' => $scheduleSynced,
            'schedule_failed' => $scheduleFailed,
        ];
    }
}
