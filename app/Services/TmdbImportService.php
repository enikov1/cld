<?php

namespace App\Services;

use App\Models\Series;
use App\Support\TplCache;

class TmdbImportService
{
    private const DETAILS_APPEND = ['credits', 'external_ids', 'content_ratings'];

    public function __construct(
        private readonly TmdbClient $client,
        private readonly TaxonomyService $taxonomyService,
        private readonly PosterStorage $posterStorage,
    ) {
    }

    /**
     * @param  array{download_poster?: bool, sync_schedule?: bool}  $options
     * @return array{ok: bool, error?: string, series?: Series}
     */
    public function import(string $tmdbId, ?string $kpId = null, array $options = []): array
    {
        $tmdbId = trim($tmdbId);
        if ($tmdbId === '') {
            return ['ok' => false, 'error' => 'Укажите TMDB ID'];
        }

        if (!$this->client->isConfigured()) {
            return ['ok' => false, 'error' => 'API-ключ TMDB не настроен. Укажите его в Настройках.'];
        }

        $kpId = $kpId !== null ? trim($kpId) : '';
        if ($kpId === '') {
            $kpId = null;
        }

        [$details, $isTv] = $this->fetchDetails($tmdbId);
        if ($details === []) {
            return ['ok' => false, 'error' => 'Контент не найден в TMDB'];
        }

        $mapped = TmdbMapper::toSeriesAttributes($details, $isTv);
        if ($mapped === []) {
            return ['ok' => false, 'error' => 'Не удалось разобрать ответ TMDB API'];
        }

        $people = TmdbCreditsMapper::toPeopleLists($details);
        $genreNames = $mapped['_genre_names'] ?? [];
        $countryNames = $mapped['_country_names'] ?? [];
        $posterSourceUrl = $mapped['poster_source_url'] ?? null;

        unset(
            $mapped['_genre_names'],
            $mapped['_country_names'],
            $mapped['poster_source_url'],
        );

        if ($kpId !== null) {
            $mapped['kp_id'] = $kpId;
        }

        $existing = $this->findExisting($kpId, $tmdbId);
        $isNew = !$existing;

        $slug = $existing?->slug;
        if (!$slug && !empty($mapped['title'])) {
            $slug = TmdbMapper::makeSlug($mapped['title'], $kpId, $tmdbId);
        }

        $posterUrl = $existing?->poster_url;
        if (!empty($options['download_poster']) && $posterSourceUrl) {
            $stored = $this->posterStorage->storeFromUrl(
                $posterSourceUrl,
                PosterContext::forSeriesData($kpId ?? ('tmdb-' . $tmdbId), array_merge($mapped, ['slug' => $slug ?? ''])),
            );
            if ($stored) {
                $posterUrl = $stored;
            } elseif (!$posterUrl) {
                $posterUrl = $posterSourceUrl;
            }
        }

        $attrs = $mapped;
        if ($existing && trim((string) $existing->description) !== '') {
            unset($attrs['description']);
        }
        if ($slug) {
            $attrs['slug'] = $slug;
        }
        if ($posterUrl) {
            $attrs['poster_url'] = $posterUrl;
        }
        if ($isNew) {
            $attrs['is_active'] = false;
        }

        if ($existing) {
            $existing->fill($attrs);
            $existing->save();
            $series = $existing;
        } else {
            $matchKey = $kpId !== null
                ? ['kp_id' => $kpId]
                : ['tmdb_id' => $tmdbId];
            $series = Series::query()->withTrashed()->updateOrCreate($matchKey, $attrs);
        }

        if ($series->trashed()) {
            $series->restore();
        }

        $this->taxonomyService->syncSeriesFromNames($series, $genreNames, $countryNames);
        $this->taxonomyService->syncSeriesPeople(
            $series,
            $people['_actor_people'],
            $people['_director_people'],
        );

        app(CdnVideoHubPlayerSync::class)->syncIfEnabled($series);

        $syncSchedule = (bool)($options['sync_schedule'] ?? true);
        app(TmdbPopularitySyncService::class)->syncSeries($series->fresh(), $syncSchedule, false);

        TplCache::forgetSeries($series->id);

        return [
            'ok' => true,
            'series' => $series->fresh()->load(['genres', 'countries', 'actors', 'directors', 'studio', 'studios']),
        ];
    }

    /**
     * @return array{0: array<string, mixed>, 1: bool}
     */
    private function fetchDetails(string $tmdbId): array
    {
        $tvDetails = $this->client->getTvDetails($tmdbId, self::DETAILS_APPEND);
        if ($tvDetails !== [] && isset($tvDetails['id'])) {
            return [$tvDetails, true];
        }

        $movieDetails = $this->client->getMovieDetails($tmdbId, self::DETAILS_APPEND);
        if ($movieDetails !== [] && isset($movieDetails['id'])) {
            return [$movieDetails, false];
        }

        return [[], false];
    }

    private function findExisting(?string $kpId, string $tmdbId): ?Series
    {
        if ($kpId !== null) {
            $byKp = Series::query()->withTrashed()->where('kp_id', $kpId)->first();
            if ($byKp) {
                return $byKp;
            }
        }

        return Series::query()->withTrashed()->where('tmdb_id', $tmdbId)->first();
    }
}
