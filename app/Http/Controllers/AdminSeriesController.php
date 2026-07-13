<?php

namespace App\Http\Controllers;

use App\Models\Series;
use App\Models\Studio;
use App\Models\StudioItem;
use App\Services\ImageOptimizer;
use App\Services\CdnVideoHubPlayerSync;
use App\Services\KinoPoiskClient;
use App\Services\KinoPoiskMapper;
use App\Services\KinoPoiskStaffMapper;
use App\Services\AllohaImportService;
use App\Services\PosterContext;
use App\Services\PosterStorage;
use App\Services\SeriesViewService;
use App\Services\TaxonomyService;
use App\Support\AdminSeriesFilter;
use App\Support\SiteConfig;
use App\Support\SlugHelper;
use App\Support\TplCache;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminSeriesController extends Controller
{
    public function index(Request $request)
    {
        $params = AdminSeriesFilter::params($request);

        $query = Series::query()->with(['genres', 'countries', 'actors', 'directors', 'studio']);
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

    public function upsert(Request $request)
    {
        $durationMinutes = $request->input('duration_minutes');
        if ($durationMinutes !== null && $durationMinutes !== '' && (int)$durationMinutes < 1) {
            $request->merge(['duration_minutes' => null]);
        }

        $data = $request->validate([
            'kp_id' => ['required', 'string'],
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
            'tmdb_id' => ['nullable', 'string'],
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
            'download_poster' => ['nullable', 'boolean'],
        ]);

        $slug = SlugHelper::make($data['slug'] ?? null, $data['title']);
        $kpId = (string)$data['kp_id'];

        $existing = Series::query()->where('kp_id', $kpId)->first();
        $isNew = !$existing;
        $oldSeason = $existing?->season_number;
        $oldEpisode = $existing?->last_episode_number;

        if (Series::query()->where('slug', $slug)->where('kp_id', '!=', $kpId)->exists()) {
            $slug = Str::slug($slug . '-' . $kpId);
        }

        $attrs = [
            'slug' => $slug,
            'title' => $data['title'],
        ];

        $nullableScalars = [
            'studio_id', 'meta_title', 'meta_description', 'title_en', 'title_original',
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
                PosterContext::forSeriesData($kpId, $data),
            );
            if ($stored) {
                $attrs['poster_url'] = $stored;
            }
        }

        $series = Series::query()->withTrashed()->updateOrCreate(
            ['kp_id' => $kpId],
            $attrs
        );

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

        if (array_key_exists('studio_id', $data)) {
            $this->syncStudioMembership($series, $existing?->studio_id, $data['studio_id']);
        }

        $series->refresh();
        \App\Services\EpisodeNotifier::fromSeriesProgress($series, $oldSeason, $oldEpisode);
        TplCache::forgetSeries($series->id);

        return response()->json([
            'ok' => true,
            'item' => $this->serializeSeries($series->fresh()->load(['genres', 'countries', 'actors', 'directors', 'studio'])),
        ]);
    }

    private function syncStudioMembership(Series $series, ?int $oldStudioId, ?int $newStudioId): void
    {
        $newStudioId = $newStudioId ? (int)$newStudioId : null;
        $oldStudioId = $oldStudioId ? (int)$oldStudioId : null;

        if ($oldStudioId && $oldStudioId !== $newStudioId) {
            StudioItem::query()
                ->where('studio_id', $oldStudioId)
                ->where('series_id', $series->id)
                ->delete();
        }

        if ($newStudioId) {
            StudioItem::query()->updateOrCreate(
                ['studio_id' => $newStudioId, 'series_id' => $series->id],
                ['rank_order' => 0]
            );
        }
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
        TplCache::forgetSeries($series->id);

        return response()->json([
            'ok' => true,
            'item' => $this->serializeSeries($series->fresh()->load(['genres', 'countries', 'actors', 'directors'])),
        ]);
    }

    public function importFromAlloha(Request $request, string $kp_id)
    {
        $data = $request->validate([
            'download_poster' => ['nullable', 'boolean'],
            'sync_players' => ['nullable', 'boolean'],
            'sync_metadata' => ['nullable', 'boolean'],
        ]);

        $result = app(AllohaImportService::class)->importByKpId($kp_id, [
            'download_poster' => (bool)($data['download_poster'] ?? true),
            'sync_players' => (bool)($data['sync_players'] ?? SiteConfig::bool('player_alloha_sync_enabled')),
            'sync_metadata' => (bool)($data['sync_metadata'] ?? true),
            'sync_genres_countries' => true,
            'sync_people' => true,
            'fill_empty_only' => false,
            'is_active' => false,
        ]);

        if ($result['ok'] && $result['series']) {
            $kpClient = app(KinoPoiskClient::class);
            if ($kpClient->isConfigured()) {
                $staff = $kpClient->getStaff($kp_id);
                $people = KinoPoiskStaffMapper::toPeopleLists($staff);
                if ($people['_actor_people'] !== [] || $people['_director_people'] !== []) {
                    app(TaxonomyService::class)->syncSeriesPeople(
                        $result['series'],
                        $people['_actor_people'],
                        $people['_director_people'],
                    );
                    $result['series'] = $result['series']->fresh()->load(['genres', 'countries', 'actors', 'directors']);
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

    public function uploadPoster(Request $request, string $kp_id)
    {
        $maxKb = (int)ceil(app(ImageOptimizer::class)->maxUploadBytes() / 1024);

        $request->validate([
            'poster' => ['required', 'file', 'image', 'max:' . $maxKb],
        ]);

        $series = Series::query()->where('kp_id', $kp_id)->firstOrFail();
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

        $series = Series::query()->where('kp_id', $kp_id)->firstOrFail();
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

        $series = Series::query()->where('kp_id', $kp_id)->firstOrFail();
        $series->is_active = $data['is_active'];
        $series->save();

        return response()->json(['ok' => true, 'item' => $this->serializeSeries($series)]);
    }

    public function destroy(string $kp_id)
    {
        $series = Series::query()->where('kp_id', $kp_id)->firstOrFail();
        $series->delete();

        return response()->json(['ok' => true]);
    }

    public function restore(string $kp_id)
    {
        $series = Series::query()->withTrashed()->where('kp_id', $kp_id)->firstOrFail();
        $series->restore();

        return response()->json([
            'ok' => true,
            'item' => $this->serializeSeries($series->load(['genres', 'countries', 'actors', 'directors'])),
        ]);
    }

    /**
     * @param array<int, int> $views3d
     * @param array<int, int> $views7d
     * @return array<string, mixed>
     */
    private function serializeSeries(Series $series, array $views3d = [], array $views7d = []): array
    {
        $series->loadMissing(['genres', 'countries', 'actors', 'directors', 'studio']);

        return array_merge($series->toArray(), [
            'genre_ids' => $series->genres->pluck('id')->values()->all(),
            'country_ids' => $series->countries->pluck('id')->values()->all(),
            'actor_ids' => $series->actors->pluck('id')->values()->all(),
            'director_ids' => $series->directors->pluck('id')->values()->all(),
            'views_3d' => $views3d[(int)$series->id] ?? 0,
            'views_7d' => $views7d[(int)$series->id] ?? 0,
            'genres' => $series->genres->map(fn ($g) => ['id' => $g->id, 'slug' => $g->slug, 'name' => $g->name])->values()->all(),
            'countries' => $series->countries->map(fn ($c) => ['id' => $c->id, 'slug' => $c->slug, 'name' => $c->name])->values()->all(),
            'actors' => $series->actors->map(fn ($p) => ['id' => $p->id, 'slug' => $p->slug, 'name' => $p->name])->values()->all(),
            'directors' => $series->directors->map(fn ($p) => ['id' => $p->id, 'slug' => $p->slug, 'name' => $p->name])->values()->all(),
            'studio' => $series->studio
                ? ['id' => $series->studio->id, 'slug' => $series->studio->slug, 'title' => $series->studio->title]
                : null,
        ]);
    }
}
