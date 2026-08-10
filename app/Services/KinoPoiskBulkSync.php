<?php

namespace App\Services;

use App\Models\Series;
use Illuminate\Support\Str;

class KinoPoiskBulkSync
{
    public function __construct(
        private readonly KinoPoiskClient $client,
        private readonly PosterStorage $posterStorage,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function runProgressiveBatch(
        bool $restart,
        string $keyword,
        int $limit,
        float $sleep,
        bool $downloadPoster,
        int $batchSize = 5,
    ): array {
        if (!$this->client->isConfigured()) {
            return array_merge(KinoPoiskBulkProgress::get(), [
                'status' => 'failed',
                'message' => 'API-ключ KinoPoisk не настроен. Укажите его в админке: Настройки → KinoPoisk API.',
            ]);
        }

        @set_time_limit(120);

        $keyword = trim($keyword);
        $limit = max(1, min(250, $limit));
        $sleep = max(0, min(30, $sleep));
        $batchSize = max(1, min(50, $batchSize));

        $progress = KinoPoiskBulkProgress::get();

        if (!$restart && in_array($progress['status'], ['paused', 'stopped'], true)) {
            return $progress;
        }

        if ($restart || !in_array($progress['status'], ['running', 'paused'], true)) {
            if ($keyword === '') {
                return array_merge($progress, [
                    'status' => 'failed',
                    'message' => 'Укажите ключевое слово для поиска.',
                ]);
            }

            $films = $this->client->searchByKeyword($keyword, $limit);
            $filmIds = [];
            foreach ($films as $film) {
                if (!is_array($film)) {
                    continue;
                }
                $filmId = $film['filmId'] ?? $film['kinopoiskId'] ?? $film['id'] ?? null;
                if ($filmId === null || (string) $filmId === '') {
                    continue;
                }
                $filmIds[] = (string) $filmId;
            }

            $progress = KinoPoiskBulkProgress::normalize([
                'status' => 'running',
                'after_index' => 0,
                'total' => count($filmIds),
                'processed' => 0,
                'synced' => 0,
                'skipped' => 0,
                'failed' => 0,
                'keyword' => $keyword,
                'limit' => $limit,
                'sleep' => $sleep,
                'download_poster' => $downloadPoster,
                'batch_size' => $batchSize,
                'film_ids' => $filmIds,
                'message' => count($filmIds) > 0
                    ? 'Импорт KinoPoisk запущен'
                    : 'Поиск не вернул результатов',
                'started_at' => time(),
                'finished_at' => null,
            ]);

            if ($progress['total'] === 0) {
                $progress['status'] = 'done';
                $progress['finished_at'] = time();
                $progress['message'] = 'Готово: фильмы не найдены';
                KinoPoiskBulkProgress::save($progress);

                return $progress;
            }

            KinoPoiskBulkProgress::save($progress);
        } else {
            $progress['status'] = 'running';
            $keyword = $progress['keyword'];
            $limit = $progress['limit'];
            $sleep = $progress['sleep'];
            $downloadPoster = $progress['download_poster'];
            $batchSize = $progress['batch_size'];
            KinoPoiskBulkProgress::save($progress);
        }

        $filmIds = $progress['film_ids'];
        $afterIndex = (int) $progress['after_index'];
        $batch = array_slice($filmIds, $afterIndex, $batchSize);

        $out = [
            'after_index' => $afterIndex,
            'processed' => 0,
            'synced' => 0,
            'skipped' => 0,
            'failed' => 0,
        ];

        foreach ($batch as $offset => $filmId) {
            $control = KinoPoiskBulkProgress::get();
            if (in_array($control['status'], ['paused', 'stopped'], true)) {
                break;
            }

            $out['after_index'] = $afterIndex + $offset + 1;
            $out['processed']++;

            try {
                $result = $this->syncFilmId((string) $filmId, $downloadPoster);
                if ($result === 'synced') {
                    $out['synced']++;
                } else {
                    $out['skipped']++;
                }
            } catch (\Throwable) {
                $out['failed']++;
            }

            if ($sleep > 0) {
                usleep((int) ($sleep * 1_000_000));
            }
        }

        // Re-read in case pause/stop was requested mid-batch.
        $latest = KinoPoiskBulkProgress::get();
        if (in_array($latest['status'], ['paused', 'stopped'], true)) {
            $latest['after_index'] = $out['after_index'];
            $latest['processed'] = (int) $progress['processed'] + $out['processed'];
            $latest['synced'] = (int) $progress['synced'] + $out['synced'];
            $latest['skipped'] = (int) $progress['skipped'] + $out['skipped'];
            $latest['failed'] = (int) $progress['failed'] + $out['failed'];
            if ($latest['status'] === 'paused') {
                $latest['message'] = sprintf(
                    'Пауза: обработано %d из %d',
                    $latest['processed'],
                    max($latest['total'], $latest['processed']),
                );
            } else {
                $latest['finished_at'] = time();
                $latest['message'] = sprintf(
                    'Остановлено: синхронизировано %d, пропущено %d, ошибок %d',
                    $latest['synced'],
                    $latest['skipped'],
                    $latest['failed'],
                );
            }
            KinoPoiskBulkProgress::save($latest);

            return $latest;
        }

        $progress['after_index'] = $out['after_index'];
        $progress['processed'] += $out['processed'];
        $progress['synced'] += $out['synced'];
        $progress['skipped'] += $out['skipped'];
        $progress['failed'] += $out['failed'];
        $progress['message'] = sprintf(
            'Обработано %d из %d',
            $progress['processed'],
            max($progress['total'], $progress['processed']),
        );

        $done = $progress['after_index'] >= $progress['total'];
        if ($done) {
            if ($progress['synced'] > 0) {
                app(SitemapService::class)->markDirty();
            }

            $progress['status'] = 'done';
            $progress['finished_at'] = time();
            $progress['message'] = sprintf(
                'Готово: синхронизировано %d, пропущено %d, ошибок %d',
                $progress['synced'],
                $progress['skipped'],
                $progress['failed'],
            );
        } else {
            $progress['status'] = 'running';
        }

        KinoPoiskBulkProgress::save($progress);

        return $progress;
    }

    public function pause(): array
    {
        $progress = KinoPoiskBulkProgress::get();
        if ($progress['status'] !== 'running') {
            return $progress;
        }

        $progress['status'] = 'paused';
        $progress['message'] = sprintf(
            'Пауза: обработано %d из %d',
            $progress['processed'],
            max($progress['total'], $progress['processed']),
        );
        KinoPoiskBulkProgress::save($progress);

        return $progress;
    }

    public function resume(): array
    {
        $progress = KinoPoiskBulkProgress::get();
        if ($progress['status'] !== 'paused') {
            return $progress;
        }

        $progress['status'] = 'running';
        $progress['message'] = sprintf(
            'Продолжение: обработано %d из %d',
            $progress['processed'],
            max($progress['total'], $progress['processed']),
        );
        KinoPoiskBulkProgress::save($progress);

        return $progress;
    }

    public function stop(): array
    {
        $progress = KinoPoiskBulkProgress::get();
        if (!in_array($progress['status'], ['running', 'paused'], true)) {
            return $progress;
        }

        $progress['status'] = 'stopped';
        $progress['finished_at'] = time();
        $progress['message'] = sprintf(
            'Остановлено: синхронизировано %d, пропущено %d, ошибок %d',
            $progress['synced'],
            $progress['skipped'],
            $progress['failed'],
        );
        KinoPoiskBulkProgress::save($progress);

        return $progress;
    }

    /**
     * Import one film by Kinopoisk ID (logic from SyncKinoPoisk command).
     *
     * @return 'synced'|'skipped'
     */
    private function syncFilmId(string $filmId, bool $downloadPoster): string
    {
        $details = $this->client->getFilm($filmId);
        if ($details === []) {
            return 'skipped';
        }

        $mapped = KinoPoiskMapper::toSeriesAttributes(
            $details,
            ['filmId' => $filmId],
            $this->client->getDistributions($filmId),
        );
        if ($mapped === []) {
            return 'skipped';
        }

        $kpId = (string) $mapped['kp_id'];
        $existing = Series::query()->withTrashed()->where('kp_id', $kpId)->first();

        $baseSlug = Str::slug($mapped['title']);
        $slug = Series::query()->where('slug', $baseSlug)->where('kp_id', '!=', $kpId)->exists()
            ? $baseSlug . '-' . $kpId
            : $baseSlug;

        $posterUrl = null;
        if ($downloadPoster && !empty($mapped['poster_source_url'])) {
            $posterUrl = $this->posterStorage->storeFromUrl(
                $mapped['poster_source_url'],
                PosterContext::forSeriesData($kpId, array_merge($mapped, ['slug' => $existing?->slug ?: $slug])),
            );
        }
        if (!$posterUrl && !empty($mapped['poster_source_url'])) {
            $posterUrl = $mapped['poster_source_url'];
        }

        $genreNames = $mapped['_genre_names'] ?? [];
        $countryNames = $mapped['_country_names'] ?? [];
        unset($mapped['poster_source_url'], $mapped['_genre_names'], $mapped['_country_names']);

        $attrs = $mapped;
        if ($posterUrl) {
            $attrs['poster_url'] = $posterUrl;
        }
        if (!$existing) {
            $attrs['slug'] = $slug;
            $attrs['is_active'] = true;
        }

        $series = Series::query()->withTrashed()->updateOrCreate(
            ['kp_id' => $kpId],
            $attrs
        );

        if ($series->trashed()) {
            $series->restore();
        }

        app(TaxonomyService::class)->syncSeriesFromNames($series, $genreNames, $countryNames);

        $staff = $this->client->getStaff($filmId);
        $people = KinoPoiskStaffMapper::toPeopleLists($staff);
        app(TaxonomyService::class)->syncSeriesPeople(
            $series,
            $people['_actor_people'],
            $people['_director_people'],
        );

        app(CdnVideoHubPlayerSync::class)->syncIfEnabled($series);

        if (trim((string) $series->tmdb_id) !== '') {
            app(TmdbPopularitySyncService::class)->syncSeries($series->fresh(), true, false);
        }

        return 'synced';
    }
}
