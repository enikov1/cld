<?php

namespace App\Services;

use App\Models\Series;
use App\Support\SiteConfig;
use App\Support\SlugHelper;
use App\Support\TplCache;

class AllohaImportService
{
    public function __construct(
        private readonly AllohaClient $client,
        private readonly AllohaPlayerSync $playerSync,
        private readonly TaxonomyService $taxonomyService,
        private readonly PosterStorage $posterStorage,
    ) {
    }

    /**
     * @param array{
     *     category_slug?: string|null,
     *     download_poster?: bool,
     *     sync_players?: bool,
     *     sync_metadata?: bool,
     *     sync_ratings?: bool,
     *     sync_poster?: bool,
     *     sync_genres_countries?: bool,
     *     fill_empty_only?: bool,
     *     ratings_only?: bool,
     *     is_active?: bool|null,
     *     is_hidden?: bool|null,
     *     sync_people?: bool,
     *     sync_tmdb?: bool,
     *     imdb_id?: string|null,
     *     tmdb_id?: string|null
     * } $options
     * @return array{ok: bool, error?: string, series?: Series}
     */
    public function importByKpId(string $kpId, array $options = []): array
    {
        if (!$this->client->isConfigured()) {
            return ['ok' => false, 'error' => 'API-токен Alloha не настроен. Укажите его в Настройках.'];
        }

        $existing = Series::query()->withTrashed()->where('kp_id', $kpId)->first();
        $imdbId = AllohaClient::normalizeImdbId($options['imdb_id'] ?? $existing?->imdb_id);
        $tmdbId = trim((string) ($options['tmdb_id'] ?? $existing?->tmdb_id ?? ''));

        $response = $this->client->getMovieWithFallback($kpId, $imdbId, $tmdbId);
        if ($response === []) {
            return ['ok' => false, 'error' => 'Контент не найден в Alloha (KP, IMDb, TMDB)'];
        }

        $mapped = AllohaMapper::toSeriesAttributes($response);
        if ($mapped === []) {
            return ['ok' => false, 'error' => 'Не удалось разобрать ответ Alloha API'];
        }

        $genreNames = $mapped['_genre_names'] ?? [];
        $countryNames = $mapped['_country_names'] ?? [];
        $actorPeople = $mapped['_actor_people'] ?? [];
        $directorPeople = $mapped['_director_people'] ?? [];
        $translations = $mapped['_translations'] ?? [];
        $defaultIframe = $mapped['_default_iframe'] ?? null;
        $posterSourceUrl = $mapped['poster_source_url'] ?? null;

        unset(
            $mapped['_genre_names'],
            $mapped['_country_names'],
            $mapped['_actor_people'],
            $mapped['_director_people'],
            $mapped['_translations'],
            $mapped['_default_iframe'],
            $mapped['poster_source_url'],
        );

        $isNew = !$existing;

        $ratingsOnly = (bool)($options['ratings_only'] ?? false);
        $syncPlayers = $this->resolveSyncPlayers($options);
        $syncMetadata = array_key_exists('sync_metadata', $options) ? (bool)$options['sync_metadata'] : true;
        $syncPoster = (bool)($options['sync_poster'] ?? ($options['download_poster'] ?? false));
        $syncGenresCountries = array_key_exists('sync_genres_countries', $options)
            ? (bool)$options['sync_genres_countries']
            : $syncMetadata;
        $fillEmptyOnly = (bool)($options['fill_empty_only'] ?? false);
        $syncPeople = (bool)($options['sync_people'] ?? false);

        if ($ratingsOnly) {
            $syncRatings = true;
            $syncPlayers = false;
            $syncMetadata = false;
            $syncPoster = false;
            $syncGenresCountries = false;
        } else {
            $syncRatings = array_key_exists('sync_ratings', $options)
                ? (bool)$options['sync_ratings']
                : $syncMetadata;
        }

        $mapped = $this->filterMappedFields(
            $mapped,
            $syncRatings,
            $syncMetadata,
            $existing,
            $fillEmptyOnly,
        );

        if (!$syncGenresCountries) {
            $genreNames = [];
            $countryNames = [];
            $actorPeople = [];
            $directorPeople = [];
        }

        $slug = $existing?->slug;
        if (!$slug && !empty($mapped['title'])) {
            $slug = SlugHelper::make(null, $mapped['title']);
            if (Series::query()->withTrashed()->where('slug', $slug)->where('kp_id', '!=', $kpId)->exists()) {
                $slug = SlugHelper::makeUnique(null, $mapped['title'] . '-' . $kpId, function (string $candidate) use ($kpId) {
                    return Series::query()->withTrashed()->where('slug', $candidate)->where('kp_id', '!=', $kpId)->exists();
                });
            }
        }

        $posterUrl = $existing?->poster_url;
        if ($syncPoster && $posterSourceUrl) {
            if (!$fillEmptyOnly || !$posterUrl) {
                $stored = $this->posterStorage->storeFromUrl(
                    $posterSourceUrl,
                    PosterContext::forSeriesData($kpId, array_merge($mapped, ['slug' => $slug ?? ''])),
                );
                if ($stored) {
                    $posterUrl = $stored;
                } elseif (!$posterUrl) {
                    $posterUrl = $posterSourceUrl;
                }
            }
        }

        $attrs = array_merge($mapped, []);

        if ($isNew) {
            $attrs['is_active'] = array_key_exists('is_active', $options)
                ? (bool)$options['is_active']
                : false;
            $attrs['is_hidden'] = array_key_exists('is_hidden', $options)
                ? (bool)$options['is_hidden']
                : false;
        } elseif (!array_key_exists('is_active', $attrs)) {
            // keep existing visibility for updates
        } else {
            unset($attrs['is_active'], $attrs['is_hidden']);
        }

        if ($slug) {
            $attrs['slug'] = $slug;
        }
        if ($posterUrl && ($syncPoster || $isNew)) {
            $attrs['poster_url'] = $posterUrl;
        }

        $hasFieldUpdates = count($mapped) > 0 || $syncPoster;
        if (!$hasFieldUpdates && !$syncPlayers && !$syncGenresCountries) {
            return ['ok' => false, 'error' => 'Нет полей для обновления'];
        }

        $series = Series::query()->withTrashed()->updateOrCreate(
            ['kp_id' => $kpId],
            $attrs,
        );

        if ($series->trashed()) {
            $series->restore();
        }

        if ($syncGenresCountries) {
            $this->taxonomyService->syncSeriesFromNames($series, $genreNames, $countryNames);
        }

        $shouldSyncPeople = $syncPeople
            || ($syncGenresCountries && ($actorPeople !== [] || $directorPeople !== []));
        if ($shouldSyncPeople) {
            $syncActors = $actorPeople;
            $syncDirectors = $directorPeople;
            if (!$syncPeople && $fillEmptyOnly) {
                if ($series->actors()->count() > 0) {
                    $syncActors = [];
                }
                if ($series->directors()->count() > 0) {
                    $syncDirectors = [];
                }
            }
            if ($syncActors !== [] || $syncDirectors !== []) {
                $this->taxonomyService->syncSeriesPeople($series, $syncActors, $syncDirectors);
            }
        }

        if ($syncPlayers) {
            $this->playerSync->sync($series, $translations, $defaultIframe);
        }
        // When sync_players is false, leave existing player sources untouched.

        app(CdnVideoHubPlayerSync::class)->syncIfEnabled($series);

        $syncTmdb = (bool)($options['sync_tmdb'] ?? true);
        if ($syncTmdb && trim((string)$series->tmdb_id) !== '') {
            // Popularity, broadcast status, episode schedule and studios in one TMDB pass.
            app(TmdbPopularitySyncService::class)->syncSeries($series->fresh(), true, false);
        }

        TplCache::forgetSeries($series->id);

        return [
            'ok' => true,
            'series' => $series->fresh()->load(['genres', 'countries', 'actors', 'directors', 'studio']),
        ];
    }

    /**
     * @param array<string,mixed> $mapped
     * @return array<string,mixed>
     */
    private function filterMappedFields(
        array $mapped,
        bool $syncRatings,
        bool $syncMetadata,
        ?Series $existing,
        bool $fillEmptyOnly,
    ): array {
        $allowed = ['alloha_token'];
        if ($syncRatings) {
            $allowed[] = 'kp_rating';
            $allowed[] = 'imdb_rating';
        }
        if ($syncMetadata) {
            $allowed = array_merge($allowed, [
                'kp_id', 'imdb_id', 'tmdb_id', 'title', 'title_en', 'title_original', 'description',
                'short_description', 'slogan', 'year', 'duration_minutes', 'age_limit',
                'premiere_date', 'content_type', 'season_number', 'last_episode_number',
            ]);
        }

        $filtered = array_intersect_key($mapped, array_flip($allowed));

        if ($fillEmptyOnly && $existing) {
            $textFields = [
                'title', 'title_en', 'title_original', 'description', 'short_description', 'slogan',
                'imdb_id', 'tmdb_id', 'year', 'duration_minutes', 'age_limit', 'premiere_date', 'content_type',
                'season_number', 'last_episode_number',
            ];
            foreach ($textFields as $field) {
                if (array_key_exists($field, $filtered) && $existing->{$field}) {
                    unset($filtered[$field]);
                }
            }
        }

        return $filtered;
    }

    /**
     * @param array<string,mixed> $options
     */
    private function resolveSyncPlayers(array $options): bool
    {
        if (!SiteConfig::bool('player_alloha_sync_enabled')) {
            return false;
        }

        if (array_key_exists('sync_players', $options)) {
            return (bool)$options['sync_players'];
        }

        return true;
    }
}
