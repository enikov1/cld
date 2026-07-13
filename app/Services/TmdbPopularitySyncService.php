<?php

namespace App\Services;

use App\Models\Series;
use App\Support\TplCache;

class TmdbPopularitySyncService
{
    public function __construct(
        private readonly TmdbClient $client,
    ) {
    }

    /**
     * @return array{updated: int, failed: int, skipped: int, log: list<string>}
     */
    public function syncAll(bool $onlyMissing = false): array
    {
        $result = [
            'updated' => 0,
            'failed' => 0,
            'skipped' => 0,
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
            $outcome = $this->syncSeries($series);
            if ($outcome === 'updated') {
                $result['updated']++;
            } elseif ($outcome === 'skipped') {
                $result['skipped']++;
            } else {
                $result['failed']++;
            }
        }

        $result['log'][] = sprintf(
            'Готово: обновлено %d, пропущено %d, ошибок %d',
            $result['updated'],
            $result['skipped'],
            $result['failed'],
        );

        return $result;
    }

    public function syncSeries(Series $series): string
    {
        $tmdbId = trim((string)$series->tmdb_id);
        if ($tmdbId === '') {
            return 'skipped';
        }

        $details = $series->content_type === 'film'
            ? $this->client->getMovieDetails($tmdbId)
            : $this->client->getTvDetails($tmdbId);

        if ($details === [] || !isset($details['popularity'])) {
            if ($series->content_type === 'film') {
                $details = $this->client->getTvDetails($tmdbId);
            } else {
                $details = $this->client->getMovieDetails($tmdbId);
            }
        }

        if ($details === [] || !isset($details['popularity'])) {
            return 'failed';
        }

        $series->tmdb_popularity = round((float)$details['popularity'], 4);
        $series->tmdb_popularity_refreshed_at = now();
        $series->save();

        TplCache::forgetSeries($series->id);

        usleep(300000);

        return 'updated';
    }
}
