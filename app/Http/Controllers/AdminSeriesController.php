<?php

namespace App\Http\Controllers;

use App\Models\Series;
use App\Services\ImageOptimizer;
use App\Services\CdnVideoHubPlayerSync;
use App\Services\KinoPoiskClient;
use App\Services\KinoPoiskMapper;
use App\Services\KinoPoiskStaffMapper;
use App\Services\AllohaClient;
use App\Services\AllohaImportService;
use App\Services\PosterContext;
use App\Services\PosterStorage;
use App\Services\SeriesLookupService;
use App\Services\SeriesViewService;
use App\Services\TaxonomyService;
use App\Services\TmdbImportService;
use App\Services\TmdbPopularitySyncService;
use App\Support\AdminSeriesFilter;
use App\Support\AdminSeriesResolver;
use App\Support\SlugHelper;
use App\Support\TplCache;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;

class AdminSeriesController extends Controller
{
    public function index(Request $request)
    {
        $params = AdminSeriesFilter::params($request);

        $query = Series::query()->with(['genres', 'countries', 'actors', 'directors', 'studio', 'studios']);
        if ($params['with_trashed']) {
            $query->withTrashed();
        }

        AdminSeriesFilter::apply($query, $params);
        AdminSeriesFilter::applySort($query, $params);

        $paginator = $query->paginate(
            (int)$params['per_page'],
            ['*'],
            'page',
            (int)$params['page'],
        );

        $ids = collect($paginator->items())->pluck('id')->map(fn ($id) => (int)$id)->all();
        $views3d = SeriesViewService::viewsSumForSeriesIds($ids, 3);
        $views7d = SeriesViewService::viewsSumForSeriesIds($ids, 7);

        return response()->json([
            'items' => collect($paginator->items())->map(fn (Series $s) => $this->serializeSeries($s, $views3d, $views7d))->values()->all(),
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'last_page' => $paginator->lastPage(),
        ]);
    }

    public function lookup(Request $request, SeriesLookupService $lookupService)
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:200'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        $result = $lookupService->search(
            (string)$data['q'],
            (int)($data['limit'] ?? 10),
        );

        return response()->json([
            'ok' => true,
            'results' => $result['results'],
            'warnings' => $result['warnings'],
        ]);
    }

    public function parseKpFromUrl(Request $request)
    {
        $data = $request->validate([
            'url' => ['required', 'url', 'max:2048'],
        ]);

        $url = trim((string)$data['url']);
        $host = (string)parse_url($url, PHP_URL_HOST);
        $host = Str::lower(preg_replace('/^www\./i', '', $host) ?? '');

        if (!in_array($host, ['lordserials.fan', 'lordserial.net'], true)) {
            return response()->json([
                'ok' => false,
                'error' => 'Разрешены только ссылки lordserials.fan / lordserial.net',
            ], 422);
        }

        $response = Http::timeout(12)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml',
            ])
            ->get($url);

        if (!$response->ok()) {
            return response()->json([
                'ok' => false,
                'error' => 'Не удалось загрузить страницу',
            ], 422);
        }

        $html = (string)$response->body();
        preg_match_all('/<video-player\b[^>]*>/i', $html, $matches);
        $tags = $matches[0] ?? [];

        foreach ($tags as $tag) {
            $isKp = (bool)preg_match('/\bdata-aggregator\s*=\s*([\'"])kp\1/i', $tag);
            if (!$isKp) {
                continue;
            }

            if (preg_match('/\bdata-title-id\s*=\s*([\'"])(\d+)\1/i', $tag, $idMatch)) {
                return response()->json([
                    'ok' => true,
                    'kp_id' => $idMatch[2],
                ]);
            }
        }

        return response()->json([
            'ok' => false,
            'error' => 'Не найден data-title-id в <video-player data-aggregator="kp">',
        ], 404);
    }

    public function checkKp(Request $request)
    {
        return $this->checkSeriesIdentifier($request, 'kp_id');
    }

    public function checkImdb(Request $request)
    {
        return $this->checkSeriesIdentifier($request, 'imdb_id');
    }

    public function checkTmdb(Request $request)
    {
        return $this->checkSeriesIdentifier($request, 'tmdb_id');
    }

    private function checkSeriesIdentifier(Request $request, string $field)
    {
        $data = $request->validate([
            $field => ['required', 'string'],
            'except_id' => ['nullable', 'integer'],
        ]);

        $value = trim((string)$data[$field]);
        $exceptId = isset($data['except_id']) ? (int)$data['except_id'] : null;

        $query = Series::query()->withTrashed()->where($field, $value);
        if ($exceptId) {
            $query->where('id', '!=', $exceptId);
        }

        $existing = $query->first(['id', $field, 'title']);

        return response()->json([
            'ok' => true,
            'exists' => (bool)$existing,
            'item' => $existing ? [
                'id' => $existing->id,
                $field => $existing->{$field},
                'title' => $existing->title,
            ] : null,
        ]);
    }

    public function upsert(Request $request)
    {
        $durationMinutes = $request->input('duration_minutes');
        if ($durationMinutes !== null && $durationMinutes !== '' && (int)$durationMinutes < 1) {
            $request->merge(['duration_minutes' => null]);
        }

        $data = $request->validate([
            'kp_id' => ['nullable', 'string', 'required_without:tmdb_id'],
            'original_kp_id' => ['nullable', 'string'],
            'id' => ['nullable', 'integer'],
            'title' => ['required', 'string'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:65535'],
            'slug' => ['nullable', 'string'],
            'title_en' => ['nullable', 'string'],
            'title_original' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'short_description' => ['nullable', 'string'],
            'slogan' => ['nullable', 'string'],
            'poster_url' => ['nullable', 'string'],
            'player_url' => ['nullable', 'string'],
            'year' => ['nullable', 'integer'],
            'start_year' => ['nullable', 'integer'],
            'end_year' => ['nullable', 'integer'],
            'duration_minutes' => ['nullable', 'integer', 'min:1'],
            'kp_rating' => ['nullable', 'numeric'],
            'imdb_rating' => ['nullable', 'numeric'],
            'kp_votes_count' => ['nullable', 'integer', 'min:0'],
            'imdb_votes_count' => ['nullable', 'integer', 'min:0'],
            'imdb_id' => ['nullable', 'string'],
            'tmdb_id' => ['nullable', 'string', 'required_without:kp_id'],
            'content_type' => ['nullable', 'in:film,series'],
            'broadcast_status' => ['nullable', 'in:ongoing,paused,completed'],
            'season_number' => ['nullable', 'integer', 'min:1', 'max:999'],
            'last_episode_number' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'premiere_date' => ['nullable', 'date'],
            'translation' => ['nullable', 'string', 'max:200'],
            'channel_name' => ['nullable', 'string', 'max:120'],
            'channel_url' => ['nullable', 'string'],
            'channel_logo_url' => ['nullable', 'string'],
            'genre_ids' => ['nullable', 'array'],
            'genre_ids.*' => ['integer', 'exists:genres,id'],
            'country_ids' => ['nullable', 'array'],
            'country_ids.*' => ['integer', 'exists:countries,id'],
            'actor_ids' => ['nullable', 'array'],
            'actor_ids.*' => ['integer', 'exists:people,id'],
            'director_ids' => ['nullable', 'array'],
            'director_ids.*' => ['integer', 'exists:people,id'],
            'age_limit' => ['nullable', 'string'],
            'kp_web_url' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'is_hidden' => ['nullable', 'boolean'],
            'noindex' => ['nullable', 'boolean'],
            'is_pinned' => ['nullable', 'boolean'],
            'is_coming_soon' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
            'studio_id' => ['nullable', 'integer', 'exists:studios,id'],
            'studio_ids' => ['nullable', 'array'],
            'studio_ids.*' => ['integer', 'exists:studios,id'],
            'collection_ids' => ['nullable', 'array'],
            'collection_ids.*' => ['integer', 'exists:collections,id'],
            'download_poster' => ['nullable', 'boolean'],
        ]);

        $slug = SlugHelper::make($data['slug'] ?? null, $data['title']);
        $kpId = trim((string)($data['kp_id'] ?? ''));
        $kpId = $kpId !== '' ? $kpId : null;
        $imdbId = trim((string)($data['imdb_id'] ?? ''));
        $imdbId = $imdbId !== '' ? $imdbId : null;
        $tmdbId = trim((string)($data['tmdb_id'] ?? ''));
        $tmdbId = $tmdbId !== '' ? $tmdbId : null;
        $originalKpId = trim((string)($data['original_kp_id'] ?? ''));
        $originalKpId = $originalKpId !== '' ? $originalKpId : null;
        $seriesId = isset($data['id']) ? (int)$data['id'] : 0;

        if ($seriesId > 0) {
            $existing = Series::query()->withTrashed()->where('id', $seriesId)->first();
            if (!$existing) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Сериал не найден',
                ], 404);
            }
            $isNew = false;
        } elseif ($originalKpId !== null) {
            $existing = Series::query()->withTrashed()->where('kp_id', $originalKpId)->first();
            if (!$existing) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Исходный сериал с KP ID ' . $originalKpId . ' не найден',
                ], 404);
            }
            $isNew = false;
        } elseif ($tmdbId !== null) {
            $existing = Series::query()->withTrashed()->where('tmdb_id', $tmdbId)->first();
            $isNew = !$existing;
        } else {
            $existing = null;
            $isNew = true;
        }

        if ($existing) {
            if ($kpId !== null) {
                $conflict = Series::query()
                    ->withTrashed()
                    ->where('kp_id', $kpId)
                    ->where('id', '!=', $existing->id)
                    ->first(['id', 'title']);

                if ($conflict) {
                    return response()->json([
                        'ok' => false,
                        'message' => 'KP ID ' . $kpId . ' уже занят: ' . $conflict->title,
                    ], 422);
                }
            }

            if ($imdbId !== null) {
                $conflict = Series::query()
                    ->withTrashed()
                    ->where('imdb_id', $imdbId)
                    ->where('id', '!=', $existing->id)
                    ->first(['id', 'title']);

                if ($conflict) {
                    return response()->json([
                        'ok' => false,
                        'message' => 'IMDb ID ' . $imdbId . ' уже занят: ' . $conflict->title,
                    ], 422);
                }
            }

            if ($tmdbId !== null) {
                $conflict = Series::query()
                    ->withTrashed()
                    ->where('tmdb_id', $tmdbId)
                    ->where('id', '!=', $existing->id)
                    ->first(['id', 'title']);

                if ($conflict) {
                    return response()->json([
                        'ok' => false,
                        'message' => 'TMDB ID ' . $tmdbId . ' уже занят: ' . $conflict->title,
                    ], 422);
                }
            }
        } else {
            if ($kpId !== null) {
                $taken = Series::query()->withTrashed()->where('kp_id', $kpId)->first(['id', 'title']);
                if ($taken) {
                    return response()->json([
                        'ok' => false,
                        'message' => 'KP ID ' . $kpId . ' уже занят: ' . $taken->title,
                    ], 422);
                }
            }

            if ($imdbId !== null) {
                $taken = Series::query()->withTrashed()->where('imdb_id', $imdbId)->first(['id', 'title']);
                if ($taken) {
                    return response()->json([
                        'ok' => false,
                        'message' => 'IMDb ID ' . $imdbId . ' уже занят: ' . $taken->title,
                    ], 422);
                }
            }

            if ($tmdbId !== null) {
                $taken = Series::query()->withTrashed()->where('tmdb_id', $tmdbId)->first(['id', 'title']);
                if ($taken) {
                    return response()->json([
                        'ok' => false,
                        'message' => 'TMDB ID ' . $tmdbId . ' уже занят: ' . $taken->title,
                    ], 422);
                }
            }
        }

        $oldSeason = $existing?->season_number;
        $oldEpisode = $existing?->last_episode_number;

        $slugConflictQuery = Series::query()->where('slug', $slug);
        if ($existing) {
            $slugConflictQuery->where('id', '!=', $existing->id);
        }
        if ($slugConflictQuery->exists()) {
            $suffix = $kpId ?? ($tmdbId ? 'tmdb-' . $tmdbId : (string)($existing?->id ?? Str::random(6)));
            $slug = Str::slug($slug . '-' . $suffix);
        }

        $attrs = [
            'slug' => $slug,
            'title' => $data['title'],
            'kp_id' => $kpId,
        ];

        $nullableScalars = [
            'meta_title', 'meta_description', 'title_en', 'title_original',
            'description', 'short_description', 'slogan', 'year', 'start_year', 'end_year',
            'duration_minutes', 'kp_rating', 'imdb_rating', 'kp_votes_count', 'imdb_votes_count',
            'imdb_id', 'tmdb_id', 'content_type', 'broadcast_status', 'season_number',
            'last_episode_number', 'premiere_date', 'translation', 'channel_name', 'channel_url',
            'channel_logo_url', 'age_limit', 'kp_web_url',
        ];

        foreach ($nullableScalars as $key) {
            if (array_key_exists($key, $data)) {
                $attrs[$key] = $data[$key];
            } elseif ($isNew) {
                $attrs[$key] = null;
            }
        }

        if (array_key_exists('imdb_id', $data)) {
            $attrs['imdb_id'] = $imdbId;
        }
        if (array_key_exists('tmdb_id', $data)) {
            $attrs['tmdb_id'] = $tmdbId;
        }

        foreach (['is_active', 'is_hidden', 'noindex', 'is_coming_soon'] as $boolKey) {
            if (array_key_exists($boolKey, $data)) {
                $attrs[$boolKey] = (bool)$data[$boolKey];
            } elseif ($isNew) {
                $attrs[$boolKey] = false;
            }
        }

        if (array_key_exists('is_pinned', $data)) {
            $isPinned = (bool)$data['is_pinned'];
            $attrs['is_pinned'] = $isPinned;
            $attrs['pinned_at'] = $isPinned ? now() : null;
        } elseif ($isNew) {
            $attrs['is_pinned'] = false;
            $attrs['pinned_at'] = null;
        }

        if (array_key_exists('sort_order', $data)) {
            $attrs['sort_order'] = (int)($data['sort_order'] ?? 0);
        } elseif ($isNew) {
            $attrs['sort_order'] = 0;
        }

        if (array_key_exists('player_url', $data)) {
            $attrs['player_url'] = trim((string)$data['player_url']) ?: null;
        }

        if (array_key_exists('poster_url', $data)) {
            $attrs['poster_url'] = trim((string)$data['poster_url']) ?: null;
        }

        if (!empty($data['download_poster']) && !empty($data['poster_url'])) {
            $stored = app(PosterStorage::class)->storeFromUrl(
                $data['poster_url'],
                PosterContext::forSeriesData($kpId ?? ('tmdb-' . ($tmdbId ?? 'new')), $data),
            );
            if ($stored) {
                $attrs['poster_url'] = $stored;
            }
        }

        if ($existing) {
            $existing->fill($attrs);
            $existing->save();
            $series = $existing;
        } else {
            $series = Series::query()->create($attrs);
        }

        if ($series->trashed()) {
            $series->restore();
        }

        $relations = [];
        foreach (['genre_ids', 'country_ids', 'actor_ids', 'director_ids'] as $relationKey) {
            if (array_key_exists($relationKey, $data)) {
                $relations[$relationKey] = $data[$relationKey];
            }
        }
        if ($relations !== []) {
            app(TaxonomyService::class)->syncSeriesRelations($series, $relations);
        }

        if (array_key_exists('studio_ids', $data)) {
            $this->syncStudios($series, $data['studio_ids'] ?? []);
        } elseif (array_key_exists('studio_id', $data)) {
            $this->syncStudios($series, $data['studio_id'] ? [(int)$data['studio_id']] : []);
        }

        if (array_key_exists('collection_ids', $data)) {
            app(\App\Services\CollectionAutoMatcher::class)->syncSeriesCollections(
                $series,
                $data['collection_ids'] ?? [],
            );
        }

        app(\App\Services\CollectionAutoMatcher::class)->syncSeriesToAutoCollections(
            $series->fresh()->load(['genres']),
        );

        $series->refresh();
        \App\Services\EpisodeNotifier::fromSeriesProgress($series, $oldSeason, $oldEpisode);
        TplCache::forgetSeries($series->id);

        return response()->json([
            'ok' => true,
            'item' => $this->serializeSeries($series->fresh()->load(['genres', 'countries', 'actors', 'directors', 'studio', 'studios', 'collections'])),
        ]);
    }

    /**
     * @param  list<int|string>|null  $studioIds
     */
    private function syncStudios(Series $series, ?array $studioIds): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $studioIds ?? []))));

        $sync = [];
        foreach ($ids as $rank => $studioId) {
            $sync[$studioId] = ['rank_order' => $rank];
        }

        $series->studios()->sync($sync);

        $series->studio_id = $ids[0] ?? null;
        $series->save();
    }

    public function importFromKp(Request $request, string $kp_id)
    {
        $data = $request->validate([
            'download_poster' => ['nullable', 'boolean'],
        ]);

        /** @var KinoPoiskClient $client */
        $client = app(KinoPoiskClient::class);
        if (!$client->isConfigured()) {
            return response()->json(['ok' => false, 'error' => 'API-ключ KinoPoisk не настроен. Укажите его в Настройках.'], 422);
        }

        $details = $client->getFilm($kp_id);
        if ($details === []) {
            return response()->json(['ok' => false, 'error' => 'Фильм не найден в KinoPoisk'], 404);
        }

        $mapped = KinoPoiskMapper::toSeriesAttributes(
            $details,
            null,
            $client->getDistributions($kp_id),
        );
        if ($mapped === []) {
            return response()->json(['ok' => false, 'error' => 'Не удалось разобрать ответ API'], 422);
        }

        $genreNames = $mapped['_genre_names'] ?? [];
        $countryNames = $mapped['_country_names'] ?? [];
        unset($mapped['_genre_names'], $mapped['_country_names']);

        $slug = SlugHelper::make(null, $mapped['title']);
        if (Series::query()->where('slug', $slug)->where('kp_id', '!=', $kp_id)->exists()) {
            $slug = Str::slug($slug . '-' . $kp_id);
        }

        $posterUrl = null;
        if (!empty($data['download_poster']) && !empty($mapped['poster_source_url'])) {
            $posterUrl = app(PosterStorage::class)->storeFromUrl(
                $mapped['poster_source_url'],
                PosterContext::forSeriesData((string)$kp_id, array_merge($mapped, ['slug' => $slug])),
            );
        }
        if (!$posterUrl && !empty($mapped['poster_source_url'])) {
            $posterUrl = $mapped['poster_source_url'];
        }

        unset($mapped['poster_source_url']);

        $existing = Series::query()->withTrashed()->where('kp_id', (string)$kp_id)->first();

        $series = Series::query()->withTrashed()->updateOrCreate(
            ['kp_id' => (string)$kp_id],
            array_merge($mapped, [
                'slug' => $slug,
                'poster_url' => $posterUrl,
            ], $existing ? [] : [
                'is_active' => false,
            ])
        );

        if ($series->trashed()) {
            $series->restore();
        }

        app(TaxonomyService::class)->syncSeriesFromNames($series, $genreNames, $countryNames);

        $staff = $client->getStaff($kp_id);
        $people = KinoPoiskStaffMapper::toPeopleLists($staff);
        app(TaxonomyService::class)->syncSeriesPeople(
            $series,
            $people['_actor_people'],
            $people['_director_people'],
        );

        app(CdnVideoHubPlayerSync::class)->syncIfEnabled($series);

        if (trim((string)$series->tmdb_id) !== '') {
            app(TmdbPopularitySyncService::class)->syncSeries($series->fresh(), true, false);
        }

        TplCache::forgetSeries($series->id);

        return response()->json([
            'ok' => true,
            'item' => $this->serializeSeries($series->fresh()->load(['genres', 'countries', 'actors', 'directors', 'studio', 'studios', 'collections'])),
        ]);
    }

    public function importFromAlloha(Request $request)
    {
        $data = $request->validate([
            'kp_id' => ['nullable', 'string', 'max:32'],
            'download_poster' => ['nullable', 'boolean'],
            'sync_players' => ['nullable', 'boolean'],
            'sync_metadata' => ['nullable', 'boolean'],
            'imdb_id' => ['nullable', 'string', 'max:32'],
            'tmdb_id' => ['nullable', 'string', 'max:32'],
        ]);

        return $this->runAllohaImport($data);
    }

    public function importFromAllohaByKey(Request $request, string $kp_id)
    {
        $data = $request->validate([
            'download_poster' => ['nullable', 'boolean'],
            'sync_players' => ['nullable', 'boolean'],
            'sync_metadata' => ['nullable', 'boolean'],
            'imdb_id' => ['nullable', 'string', 'max:32'],
            'tmdb_id' => ['nullable', 'string', 'max:32'],
        ]);
        $data['kp_id'] = $kp_id;

        return $this->runAllohaImport($data);
    }

    /**
     * @param array<string,mixed> $data
     */
    private function runAllohaImport(array $data)
    {
        $kpId = trim((string)($data['kp_id'] ?? ''));
        $imdbId = AllohaClient::normalizeImdbId($data['imdb_id'] ?? null);
        $tmdbId = trim((string)($data['tmdb_id'] ?? ''));

        if ($kpId === '' && $imdbId === '' && $tmdbId === '') {
            return response()->json(['ok' => false, 'error' => 'Укажите KP ID, IMDb ID или TMDB ID'], 422);
        }

        $result = app(AllohaImportService::class)->importByKpId($kpId, [
            'download_poster' => (bool)($data['download_poster'] ?? true),
            'sync_metadata' => (bool)($data['sync_metadata'] ?? true),
            'sync_genres_countries' => true,
            'sync_people' => true,
            'fill_empty_only' => false,
            'is_active' => false,
            'imdb_id' => $imdbId !== '' ? $imdbId : null,
            'tmdb_id' => $tmdbId !== '' ? $tmdbId : null,
        ]);

        if ($result['ok'] && $result['series']) {
            $staffKpId = trim((string)$result['series']->kp_id);
            $kpClient = app(KinoPoiskClient::class);
            if ($staffKpId !== '' && $kpClient->isConfigured()) {
                $staff = $kpClient->getStaff($staffKpId);
                $people = KinoPoiskStaffMapper::toPeopleLists($staff);
                if ($people['_actor_people'] !== [] || $people['_director_people'] !== []) {
                    app(TaxonomyService::class)->syncSeriesPeople(
                        $result['series'],
                        $people['_actor_people'],
                        $people['_director_people'],
                    );
                    $result['series'] = $result['series']->fresh()->load(['genres', 'countries', 'actors', 'directors', 'studio', 'studios', 'collections']);
                }
            }
        }

        if (!$result['ok']) {
            $status = str_contains($result['error'] ?? '', 'не настроен') ? 422 : 404;

            return response()->json(['ok' => false, 'error' => $result['error']], $status);
        }

        return response()->json([
            'ok' => true,
            'item' => $this->serializeSeries($result['series']),
        ]);
    }

    public function importFromTmdb(Request $request)
    {
        $data = $request->validate([
            'tmdb_id' => ['required', 'string'],
            'kp_id' => ['nullable', 'string'],
            'download_poster' => ['nullable', 'boolean'],
            'sync_schedule' => ['nullable', 'boolean'],
        ]);

        $kpId = trim((string)($data['kp_id'] ?? ''));
        $result = app(TmdbImportService::class)->import(
            (string)$data['tmdb_id'],
            $kpId !== '' ? $kpId : null,
            [
                'download_poster' => (bool)($data['download_poster'] ?? true),
                'sync_schedule' => (bool)($data['sync_schedule'] ?? true),
            ],
        );

        if (!$result['ok']) {
            $status = str_contains($result['error'] ?? '', 'не настроен') ? 422 : 404;

            return response()->json(['ok' => false, 'error' => $result['error']], $status);
        }

        return response()->json([
            'ok' => true,
            'item' => $this->serializeSeries($result['series']),
        ]);
    }

    public function uploadPoster(Request $request, string $kp_id)
    {
        $maxKb = (int)ceil(app(ImageOptimizer::class)->maxUploadBytes() / 1024);

        $request->validate([
            'poster' => ['required', 'file', 'image', 'max:' . $maxKb],
        ]);

        $series = AdminSeriesResolver::byKey($kp_id);
        $url = app(PosterStorage::class)->storeFromUpload(
            $request->file('poster'),
            PosterContext::forSeries($series),
        );
        $series->poster_url = $url;
        $series->save();

        return response()->json(['ok' => true, 'poster_url' => $url, 'item' => $this->serializeSeries($series)]);
    }

    public function pin(Request $request, string $kp_id)
    {
        $data = $request->validate([
            'pinned' => ['required', 'boolean'],
        ]);

        $series = AdminSeriesResolver::byKey($kp_id);
        $series->is_pinned = $data['pinned'];
        $series->pinned_at = $data['pinned'] ? now() : null;
        $series->save();

        return response()->json(['ok' => true, 'item' => $this->serializeSeries($series)]);
    }

    public function visibility(Request $request, string $kp_id)
    {
        $data = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $series = AdminSeriesResolver::byKey($kp_id);
        $series->is_active = $data['is_active'];
        $series->save();

        return response()->json(['ok' => true, 'item' => $this->serializeSeries($series)]);
    }

    public function destroy(string $kp_id)
    {
        $series = AdminSeriesResolver::byKey($kp_id);
        $series->delete();

        return response()->json(['ok' => true]);
    }

    public function restore(string $kp_id)
    {
        $series = AdminSeriesResolver::byKey($kp_id, true);
        $series->restore();

        return response()->json([
            'ok' => true,
            'item' => $this->serializeSeries($series->load(['genres', 'countries', 'actors', 'directors', 'studio', 'studios', 'collections'])),
        ]);
    }

    /**
     * @param array<int, int> $views3d
     * @param array<int, int> $views7d
     * @return array<string, mixed>
     */
    private function serializeSeries(Series $series, array $views3d = [], array $views7d = []): array
    {
        $series->loadMissing(['genres', 'countries', 'actors', 'directors', 'studio', 'studios', 'collections']);

        $studios = collect();
        if ($series->studio) {
            $studios->put($series->studio->id, $series->studio);
        }
        foreach ($series->studios as $studio) {
            $studios->put($studio->id, $studio);
        }
        $studiosList = $studios->values()->map(fn ($s) => [
            'id' => $s->id,
            'slug' => $s->slug,
            'title' => $s->title,
            'logo_url' => $s->logo_url ?? null,
        ])->values()->all();

        return array_merge($series->toArray(), [
            'genre_ids' => $series->genres->pluck('id')->values()->all(),
            'country_ids' => $series->countries->pluck('id')->values()->all(),
            'actor_ids' => $series->actors->pluck('id')->values()->all(),
            'director_ids' => $series->directors->pluck('id')->values()->all(),
            'studio_ids' => array_map(fn ($s) => (int)$s['id'], $studiosList),
            'collection_ids' => $series->collections
                ->filter(fn ($c) => !($c->pivot->is_auto ?? false))
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all(),
            'collections' => $series->collections->map(fn ($c) => [
                'id' => $c->id,
                'slug' => $c->slug,
                'title' => $c->title,
                'is_auto' => (bool) ($c->pivot->is_auto ?? false),
            ])->values()->all(),
            'views_3d' => $views3d[(int)$series->id] ?? 0,
            'views_7d' => $views7d[(int)$series->id] ?? 0,
            'genres' => $series->genres->map(fn ($g) => ['id' => $g->id, 'slug' => $g->slug, 'name' => $g->name])->values()->all(),
            'countries' => $series->countries->map(fn ($c) => ['id' => $c->id, 'slug' => $c->slug, 'name' => $c->name])->values()->all(),
            'actors' => $series->actors->map(fn ($p) => ['id' => $p->id, 'slug' => $p->slug, 'name' => $p->name])->values()->all(),
            'directors' => $series->directors->map(fn ($p) => ['id' => $p->id, 'slug' => $p->slug, 'name' => $p->name])->values()->all(),
            'studios' => $studiosList,
            'studio' => $series->studio
                ? ['id' => $series->studio->id, 'slug' => $series->studio->slug, 'title' => $series->studio->title]
                : ($studiosList[0] ?? null),
        ]);
    }
}