<?php

use App\Http\Controllers\AdminNavController;
use App\Http\Controllers\AdminTemplateController;
use App\Http\Controllers\AdminHomeSectionController;
use App\Http\Controllers\AdminReactionController;
use App\Http\Controllers\AdminPlayersController;
use App\Http\Controllers\AdminScheduleController;
use App\Http\Controllers\AdminSearchController;
use App\Http\Controllers\AdminSeriesController;
use App\Http\Controllers\AdminTaxonomyController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AdminCacheController;
use App\Http\Controllers\AdminSystemController;
use App\Models\Collection;
use App\Models\CollectionItem;
use App\Models\CronRun;
use App\Models\Series;
use App\Models\Studio;
use App\Models\StudioItem;
use App\Services\KinoPoiskConfig;
use App\Services\AllohaConfig;
use App\Services\AllohaAutoSyncSettings;
use App\Services\AllohaLatestSyncService;
use App\Services\CronRunLogger;
use App\Services\TmdbConfig;
use App\Services\TmdbAutoSyncSettings;
use App\Services\TmdbPopularitySyncService;
use App\Services\TmdbStudioSyncService;
use App\Services\TmdbSyncProgress;
use App\Support\AdminAccess;
use App\Support\AdminPath;
use App\Support\CommentBody;
use App\Support\CommentModeration;
use App\Support\RobotsTxt;
use App\Support\SiteConfig;
use App\Support\SlugHelper;
use App\Support\ThemeManager;
use App\Support\TplCache;
use App\Support\Utf8;
use App\Services\ImageOptimizer;
use App\Services\PosterContext;
use App\Services\PosterStorage;
use App\Services\BrandingStorage;
use App\Services\BackupSettings;
use App\Services\BackupService;
use App\Services\BackupRemoteStorage;
use App\Services\SitemapService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;

Route::get('/site/admin-path', function () {
    // Avoid public disclosure of custom admin path in production.
    if (!app()->environment('local') && !\App\Support\AdminAccess::hasValidToken(request())) {
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    return response()->json([
        'path' => AdminPath::path(),
        'base' => AdminPath::base(),
    ]);
});

Route::middleware('admin.token')->prefix('admin')->group(function () {
    Route::post('/site-access', function () {
        $cookie = AdminAccess::makeCookie();
        $response = response()->json(['ok' => true]);

        return $cookie ? $response->withCookie($cookie) : $response;
    });

    Route::delete('/site-access', function () {
        return response()->json(['ok' => true])->withCookie(AdminAccess::forgetCookie());
    });

    Route::get('/stats', function () {
        return response()->json([
            'series_total' => Series::query()->count(),
            'series_active' => Series::query()->where('is_active', true)->count(),
            'collections' => Collection::query()->count(),
            'collections_active' => Collection::query()->where('is_active', true)->count(),
            'studios' => Studio::query()->count(),
            'studios_active' => Studio::query()->where('is_active', true)->count(),
            'comments_total' => \App\Models\Comment::query()->count(),
            'comments_pending' => \App\Models\Comment::query()->where('status', 'pending')->count(),
            'player_reports_total' => \App\Models\PlayerReport::query()->count(),
            'player_reports_today' => \App\Models\PlayerReport::query()->where('created_at', '>=', now()->startOfDay())->count(),
            'users_total' => \App\Models\User::query()->count(),
            'users_blocked' => \App\Models\User::query()->where('is_blocked', true)->count(),
            'series_with_player' => Series::query()
                ->whereNotNull('player_url')
                ->where('player_url', '!=', '')
                ->count(),
            'active_theme' => ThemeManager::activeName(),
        ]);
    });

    Route::get('/cache', [AdminCacheController::class, 'info']);
    Route::post('/cache/clear', [AdminCacheController::class, 'clear']);
    Route::get('/system', [AdminSystemController::class, 'info']);

    Route::get('/cron-runs', function (Request $request) {
        $perPage = min(100, max(10, (int)$request->query('per_page', 50)));
        $page = max(1, (int)$request->query('page', 1));
        $jobKey = trim((string)$request->query('job_key', ''));
        $status = trim((string)$request->query('status', ''));
        $trigger = trim((string)$request->query('trigger', ''));

        $query = CronRun::query()->orderByDesc('id');
        if ($jobKey !== '') {
            $query->where('job_key', $jobKey);
        }
        if ($status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }
        if ($trigger !== '' && $trigger !== 'all') {
            $query->where('trigger', $trigger);
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $items = collect($paginator->items())->map(function (CronRun $run) {
            return [
                'id' => $run->id,
                'job_key' => $run->job_key,
                'job_label' => CronRunLogger::jobLabel($run->job_key),
                'command' => $run->command,
                'trigger' => $run->trigger,
                'status' => $run->status,
                'started_at' => optional($run->started_at)?->toIso8601String(),
                'finished_at' => optional($run->finished_at)?->toIso8601String(),
                'duration_ms' => $run->duration_ms,
                'counts' => $run->counts,
                'message' => $run->message,
                'error' => $run->error,
                'has_log' => $run->log !== null && $run->log !== '',
                'meta' => $run->meta,
                'created_at' => optional($run->created_at)?->toIso8601String(),
            ];
        })->values()->all();

        return response()->json([
            'items' => $items,
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'last_page' => $paginator->lastPage(),
            'job_options' => [
                ['value' => CronRunLogger::JOB_ALLOHA_LATEST, 'label' => CronRunLogger::jobLabel(CronRunLogger::JOB_ALLOHA_LATEST)],
                ['value' => CronRunLogger::JOB_TMDB_POPULARITY, 'label' => CronRunLogger::jobLabel(CronRunLogger::JOB_TMDB_POPULARITY)],
                ['value' => CronRunLogger::JOB_POPULAR_BADGES, 'label' => CronRunLogger::jobLabel(CronRunLogger::JOB_POPULAR_BADGES)],
                ['value' => CronRunLogger::JOB_SITEMAP, 'label' => CronRunLogger::jobLabel(CronRunLogger::JOB_SITEMAP)],
                ['value' => CronRunLogger::JOB_KP_SYNC, 'label' => CronRunLogger::jobLabel(CronRunLogger::JOB_KP_SYNC)],
                ['value' => CronRunLogger::JOB_ALLOHA_SYNC, 'label' => CronRunLogger::jobLabel(CronRunLogger::JOB_ALLOHA_SYNC)],
                ['value' => CronRunLogger::JOB_ALLOHA_IMPORT, 'label' => CronRunLogger::jobLabel(CronRunLogger::JOB_ALLOHA_IMPORT)],
                ['value' => CronRunLogger::JOB_TMDB_STUDIO_LOGOS, 'label' => CronRunLogger::jobLabel(CronRunLogger::JOB_TMDB_STUDIO_LOGOS)],
                ['value' => CronRunLogger::JOB_BACKUP, 'label' => CronRunLogger::jobLabel(CronRunLogger::JOB_BACKUP)],
                ['value' => CronRunLogger::JOB_BACKUP_RESTORE, 'label' => CronRunLogger::jobLabel(CronRunLogger::JOB_BACKUP_RESTORE)],
            ],
        ]);
    });

    Route::get('/cron-runs/{id}', function (int $id) {
        $run = CronRun::query()->findOrFail($id);

        return response()->json([
            'item' => [
                'id' => $run->id,
                'job_key' => $run->job_key,
                'job_label' => CronRunLogger::jobLabel($run->job_key),
                'command' => $run->command,
                'trigger' => $run->trigger,
                'status' => $run->status,
                'started_at' => optional($run->started_at)?->toIso8601String(),
                'finished_at' => optional($run->finished_at)?->toIso8601String(),
                'duration_ms' => $run->duration_ms,
                'counts' => $run->counts,
                'message' => $run->message,
                'error' => $run->error,
                'log' => $run->log,
                'meta' => $run->meta,
                'created_at' => optional($run->created_at)?->toIso8601String(),
            ],
        ]);
    });

    Route::delete('/cron-runs/{id}', function (int $id) {
        CronRun::query()->where('id', $id)->delete();

        return response()->json(['ok' => true]);
    });

    Route::get('/series', [AdminSeriesController::class, 'index']);
    Route::get('/series/lookup', [AdminSeriesController::class, 'lookup']);
    Route::get('/series/parse-kp-from-url', [AdminSeriesController::class, 'parseKpFromUrl']);
    Route::get('/series/check-kp', [AdminSeriesController::class, 'checkKp']);
    Route::get('/series/check-imdb', [AdminSeriesController::class, 'checkImdb']);
    Route::get('/series/check-tmdb', [AdminSeriesController::class, 'checkTmdb']);
    Route::post('/series/upsert', [AdminSeriesController::class, 'upsert']);
    Route::post('/series/import-tmdb', [AdminSeriesController::class, 'importFromTmdb']);
    Route::post('/series/import-alloha', [AdminSeriesController::class, 'importFromAlloha']);
    Route::post('/series/{kp_id}/import-kp', [AdminSeriesController::class, 'importFromKp']);
    Route::post('/series/{kp_id}/import-alloha', [AdminSeriesController::class, 'importFromAllohaByKey']);
    Route::post('/series/{kp_id}/poster', [AdminSeriesController::class, 'uploadPoster']);
    Route::post('/series/{kp_id}/pin', [AdminSeriesController::class, 'pin']);
    Route::post('/series/{kp_id}/visibility', [AdminSeriesController::class, 'visibility']);
    Route::delete('/series/{kp_id}', [AdminSeriesController::class, 'destroy']);
    Route::post('/series/{kp_id}/restore', [AdminSeriesController::class, 'restore']);
    Route::get('/series/{kp_id}/schedule', [AdminScheduleController::class, 'show']);
    Route::post('/series/{kp_id}/schedule', [AdminScheduleController::class, 'save']);
    Route::post('/series/{kp_id}/schedule/import-tmdb', [AdminScheduleController::class, 'importFromTmdb']);
    Route::get('/series/{kp_id}/players', [AdminPlayersController::class, 'show']);
    Route::post('/series/{kp_id}/players/add-alloha', [AdminPlayersController::class, 'addAllohaPlayer']);
    Route::post('/series/{kp_id}/players', [AdminPlayersController::class, 'save']);
    Route::post('/players/alloha/sync-all', [AdminPlayersController::class, 'syncAllohaAll']);
    Route::get('/players/alloha/sync-progress', [AdminPlayersController::class, 'allohaSyncProgress']);
    Route::post('/players/cdnvideohub/sync-all', [AdminPlayersController::class, 'syncCdnVideoHubAll']);

    Route::get('/users', [AdminUserController::class, 'index']);
    Route::post('/users', [AdminUserController::class, 'store']);
    Route::post('/users/{id}', [AdminUserController::class, 'update']);
    Route::delete('/users/{id}', [AdminUserController::class, 'destroy']);

    Route::get('/search-stats', [AdminSearchController::class, 'index']);
    Route::post('/search-stats/clear', [AdminSearchController::class, 'clear']);
    Route::delete('/search-stats/logs/{id}', [AdminSearchController::class, 'destroyLog']);
    Route::delete('/search-stats/{id}', [AdminSearchController::class, 'destroy']);

    Route::get('/taxonomies/options', [AdminTaxonomyController::class, 'options']);
    Route::get('/taxonomies/{type}', [AdminTaxonomyController::class, 'index']);
    Route::post('/taxonomies/{type}/upsert', [AdminTaxonomyController::class, 'upsert']);
    Route::post('/taxonomies/people/{id}/photo', [AdminTaxonomyController::class, 'uploadPhoto']);
    Route::delete('/taxonomies/{type}/{id}', [AdminTaxonomyController::class, 'destroy']);

    Route::get('/home-sections', [AdminHomeSectionController::class, 'index']);
    Route::post('/home-sections/upsert', [AdminHomeSectionController::class, 'upsert']);
    Route::post('/home-sections/preview', [AdminHomeSectionController::class, 'preview']);
    Route::post('/home-sections/reorder', [AdminHomeSectionController::class, 'reorder']);
    Route::delete('/home-sections/{id}', [AdminHomeSectionController::class, 'destroy']);

    Route::get('/nav', [AdminNavController::class, 'index']);
    Route::post('/nav/items/upsert', [AdminNavController::class, 'upsertItem']);
    Route::post('/nav/items/reorder', [AdminNavController::class, 'reorderItems']);
    Route::delete('/nav/items/{id}', [AdminNavController::class, 'destroyItem']);
    Route::post('/nav/mega-buttons/upsert', [AdminNavController::class, 'upsertMegaButton']);
    Route::post('/nav/mega-buttons/reorder', [AdminNavController::class, 'reorderMegaButtons']);
    Route::delete('/nav/mega-buttons/{id}', [AdminNavController::class, 'destroyMegaButton']);
    Route::post('/nav/mega-sections/upsert', [AdminNavController::class, 'upsertMegaSection']);
    Route::post('/nav/mega-sections/reorder', [AdminNavController::class, 'reorderMegaSections']);
    Route::delete('/nav/mega-sections/{id}', [AdminNavController::class, 'destroyMegaSection']);
    Route::post('/nav/mega-links/upsert', [AdminNavController::class, 'upsertMegaLink']);
    Route::post('/nav/mega-links/reorder', [AdminNavController::class, 'reorderMegaLinks']);
    Route::delete('/nav/mega-links/{id}', [AdminNavController::class, 'destroyMegaLink']);

    Route::get('/reactions', [AdminReactionController::class, 'index']);
    Route::post('/reactions/settings', [AdminReactionController::class, 'saveSettings']);
    Route::post('/reactions/upsert', [AdminReactionController::class, 'upsert']);
    Route::post('/reactions/reorder', [AdminReactionController::class, 'reorder']);
    Route::delete('/reactions/{id}', [AdminReactionController::class, 'destroy']);

    Route::get('/templates/themes', [AdminTemplateController::class, 'themes']);
    Route::get('/templates/tree', [AdminTemplateController::class, 'tree']);
    Route::get('/templates/file', [AdminTemplateController::class, 'show']);
    Route::post('/templates/file', [AdminTemplateController::class, 'save']);
    Route::post('/templates/file/create', [AdminTemplateController::class, 'create']);
    Route::post('/templates/directory/create', [AdminTemplateController::class, 'createDirectory']);
    Route::post('/templates/rename', [AdminTemplateController::class, 'rename']);
    Route::post('/templates/file/upload', [AdminTemplateController::class, 'upload']);
    Route::delete('/templates/file', [AdminTemplateController::class, 'destroy']);
    Route::get('/templates/docs', [AdminTemplateController::class, 'docs']);

    Route::get('/collections', function () {
        return response()->json([
            'items' => Collection::query()->catalogOrder()->limit(100)->get(),
        ]);
    });

    Route::get('/collections/ai-prompt', function () {
        $result = app(\App\Services\CollectionAiPromptService::class)->build();

        return response()->json(['ok' => true, ...$result]);
    });

    Route::post('/collections/ai-import', function (Request $request) {
        $data = $request->validate([
            'payload' => ['required', 'string', 'max:500000'],
            'dry_run' => ['nullable', 'boolean'],
        ]);

        $result = app(\App\Services\CollectionAiImportService::class)->import(
            (string) $data['payload'],
            (bool) ($data['dry_run'] ?? true),
        );

        $status = $result['ok'] || ($result['items'] !== [] || $result['skipped'] !== []) ? 200 : 422;

        return response()->json($result, $status);
    });

    Route::post('/collections/upsert', function (Request $request) {
        $data = $request->validate([
            'id' => ['nullable', 'integer', 'exists:collections,id'],
            'slug' => ['nullable', 'string', 'regex:/^[a-z0-9\-]*$/'],
            'title' => ['required', 'string'],
            'studio_id' => ['nullable', 'integer', 'exists:studios,id'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'meta_description' => ['nullable', 'string'],
            'seo_html' => ['nullable', 'string', 'max:65535'],
            'cover_url' => ['nullable', 'string'],
            'home_banner_url' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer'],
            'is_pinned' => ['nullable', 'boolean'],
            'show_on_home' => ['nullable', 'boolean'],
            'source_updated_at' => ['nullable', 'date'],
            'is_active' => ['nullable', 'boolean'],
            'is_hidden' => ['nullable', 'boolean'],
            'noindex' => ['nullable', 'boolean'],
            'auto_add_enabled' => ['nullable', 'boolean'],
            'auto_keywords' => ['nullable'],
            'series_ids' => ['nullable', 'array'],
            'series_ids.*' => ['integer', 'exists:series,id'],
        ]);

        $matcher = app(\App\Services\CollectionAutoMatcher::class);
        $keywordsChanged = false;

        $manual = trim((string)($data['slug'] ?? ''));
        $collection = !empty($data['id'])
            ? Collection::query()->findOrFail($data['id'])
            : null;

        if ($collection) {
            $slug = $collection->slug;
            $oldKeywords = $matcher->parseKeywords($collection->auto_keywords);
            $newKeywords = array_key_exists('auto_keywords', $data)
                ? $matcher->parseKeywords($data['auto_keywords'])
                : $oldKeywords;
            $keywordsChanged = $oldKeywords !== $newKeywords
                || (array_key_exists('auto_add_enabled', $data) && (bool) $data['auto_add_enabled'] !== (bool) $collection->auto_add_enabled);
        } elseif ($manual !== '') {
            $slug = \Illuminate\Support\Str::slug($manual);
        } else {
            $slug = SlugHelper::makeUnique(
                null,
                $data['title'],
                fn (string $candidate) => Collection::query()->where('slug', $candidate)->exists()
            );
        }

        if (!$collection && Collection::query()->where('slug', $slug)->exists()) {
            return response()->json(['ok' => false, 'error' => 'Slug already taken'], 422);
        }

        $attrs = [
            'title' => $data['title'],
            'studio_id' => $data['studio_id'] ?? null,
            'meta_title' => $data['meta_title'] ?? null,
            'description' => $data['description'] ?? null,
            'meta_description' => $data['meta_description'] ?? null,
            'seo_html' => isset($data['seo_html']) ? str_replace("\r\n", "\n", (string)$data['seo_html']) : null,
            'cover_url' => $data['cover_url'] ?? null,
            'home_banner_url' => $data['home_banner_url'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
            'is_pinned' => $data['is_pinned'] ?? false,
            'show_on_home' => $data['show_on_home'] ?? false,
            'is_active' => $data['is_active'] ?? true,
            'is_hidden' => $data['is_hidden'] ?? false,
            'noindex' => $data['noindex'] ?? false,
        ];

        if (array_key_exists('auto_add_enabled', $data)) {
            $attrs['auto_add_enabled'] = (bool) $data['auto_add_enabled'];
        } elseif (!$collection) {
            $attrs['auto_add_enabled'] = false;
        }

        if (array_key_exists('auto_keywords', $data)) {
            $parsed = $matcher->parseKeywords($data['auto_keywords']);
            $attrs['auto_keywords'] = $parsed !== [] ? $parsed : null;
        }

        if ($collection) {
            $collection->update($attrs);
        } else {
            $collection = Collection::query()->create(array_merge($attrs, [
                'slug' => $slug,
                'source_updated_at' => $data['source_updated_at'] ?? now(),
            ]));
            $keywordsChanged = !empty($attrs['auto_add_enabled']) && !empty($attrs['auto_keywords']);
        }

        if (array_key_exists('series_ids', $data)) {
            $matcher->syncExplicitMembership($collection, $data['series_ids'] ?? []);
        }

        $autoSync = ['added' => 0, 'removed' => 0];
        if ($keywordsChanged) {
            $autoSync = $matcher->refreshAutoItems($collection->fresh());
        }

        \App\Support\TplCache::bumpGlobalVersion();

        $seriesIds = CollectionItem::query()
            ->where('collection_id', $collection->id)
            ->orderBy('rank_order')
            ->pluck('series_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        return response()->json([
            'ok' => true,
            'item' => $collection->fresh(),
            'series_ids' => $seriesIds,
            'auto_sync' => $autoSync,
        ]);
    });

    Route::post('/collections/{collection_slug}/auto-sync', function (string $collection_slug) {
        $collection = Collection::query()->where('slug', $collection_slug)->firstOrFail();
        $result = app(\App\Services\CollectionAutoMatcher::class)->refreshAutoItems($collection);

        \App\Support\TplCache::bumpGlobalVersion();

        return response()->json(['ok' => true, ...$result]);
    });

    Route::post('/collections/{slug}/cover', function (Request $request, string $slug) {
        $maxKb = (int)ceil(app(ImageOptimizer::class)->maxUploadBytes() / 1024);

        $request->validate([
            'cover' => ['required', 'file', 'image', 'max:' . $maxKb],
            'title' => ['nullable', 'string', 'max:255'],
            'variant' => ['nullable', 'string', 'in:cover,banner'],
        ]);

        $variant = $request->input('variant', 'cover');
        $isBanner = $variant === 'banner';
        $slugInput = in_array($slug, ['_draft', 'new'], true) ? '' : $slug;
        $normalizedSlug = SlugHelper::make(
            $slugInput,
            trim((string) $request->input('title', '')),
        );

        $collection = Collection::query()->where('slug', $normalizedSlug)->first();
        $contextVariant = $isBanner ? 'banner' : null;
        $url = app(PosterStorage::class)->storeFromUpload(
            $request->file('cover'),
            $collection
                ? PosterContext::forCollection($collection, $contextVariant)
                : PosterContext::forCollectionSlug($normalizedSlug, $contextVariant),
        );

        if ($collection) {
            if ($isBanner) {
                $collection->home_banner_url = $url;
            } else {
                $collection->cover_url = $url;
            }
            $collection->save();
        }

        return response()->json([
            'ok' => true,
            'cover_url' => $isBanner ? null : $url,
            'home_banner_url' => $isBanner ? $url : null,
            'slug' => $normalizedSlug,
            'item' => $collection,
        ]);
    });

    Route::get('/collections/{collection_slug}/items', function (string $collection_slug) {
        $collection = Collection::query()->where('slug', $collection_slug)->firstOrFail();

        return response()->json([
            'items' => CollectionItem::query()
                ->where('collection_id', $collection->id)
                ->with('series:id,kp_id,tmdb_id,title,slug,year')
                ->orderBy('rank_order')
                ->get()
                ->map(fn (CollectionItem $item) => [
                    'id' => $item->id,
                    'collection_id' => $item->collection_id,
                    'series_id' => $item->series_id,
                    'rank_order' => $item->rank_order,
                    'is_auto' => (bool) $item->is_auto,
                    'series' => $item->series,
                ]),
            'series_ids' => CollectionItem::query()
                ->where('collection_id', $collection->id)
                ->orderBy('rank_order')
                ->pluck('series_id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all(),
            'manual_series_ids' => CollectionItem::query()
                ->where('collection_id', $collection->id)
                ->where('is_auto', false)
                ->orderBy('rank_order')
                ->pluck('series_id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all(),
        ]);
    });

    Route::post('/collections/{collection_slug}/items', function (Request $request, string $collection_slug) {
        $data = $request->validate([
            'items' => ['required', 'array'],
            'items.*.series_id' => ['nullable', 'integer'],
            'items.*.kp_id' => ['nullable', 'string'],
            'items.*.tmdb_id' => ['nullable', 'string'],
            'items.*.rank_order' => ['nullable', 'integer'],
        ]);

        $collection = Collection::query()->where('slug', $collection_slug)->firstOrFail();
        $added = 0;
        $skipped = 0;

        foreach ($data['items'] as $idx => $item) {
            $series = \App\Support\SeriesItemResolver::fromItem($item);
            if (!$series) {
                $skipped++;
                continue;
            }

            CollectionItem::query()->updateOrCreate(
                ['collection_id' => $collection->id, 'series_id' => $series->id],
                ['rank_order' => $item['rank_order'] ?? (int) $idx, 'is_auto' => false]
            );
            $added++;
        }

        return response()->json(['ok' => true, 'added' => $added, 'skipped' => $skipped]);
    });

    Route::delete('/collections/{collection_slug}/items/{seriesKey}', function (string $collection_slug, string $seriesKey) {
        $collection = Collection::query()->where('slug', $collection_slug)->firstOrFail();
        $series = \App\Support\AdminSeriesResolver::byKey($seriesKey);

        CollectionItem::query()
            ->where('collection_id', $collection->id)
            ->where('series_id', $series->id)
            ->delete();

        return response()->json(['ok' => true]);
    });

    Route::delete('/collections/{collection_slug}', function (string $collection_slug) {
        $collection = Collection::query()->where('slug', $collection_slug)->firstOrFail();
        $collection->delete();

        \App\Support\TplCache::bumpGlobalVersion();

        return response()->json(['ok' => true]);
    });

    Route::get('/studios', function () {
        return response()->json([
            'items' => Studio::query()->catalogOrder()->limit(2000)->get(),
        ]);
    });

    Route::post('/studios/upsert', function (Request $request) {
        $data = $request->validate([
            'id' => ['nullable', 'integer', 'exists:studios,id'],
            'slug' => ['nullable', 'string', 'regex:/^[a-z0-9\-]*$/'],
            'title' => ['required', 'string'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'meta_description' => ['nullable', 'string'],
            'seo_html' => ['nullable', 'string', 'max:65535'],
            'logo_url' => ['nullable', 'string'],
            'tmdb_id' => ['nullable', 'integer', 'min:1'],
            'tmdb_type' => ['nullable', 'string', Rule::in(['movie', 'tv', 'company'])],
            'sort_order' => ['nullable', 'integer'],
            'is_pinned' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'is_hidden' => ['nullable', 'boolean'],
            'noindex' => ['nullable', 'boolean'],
        ]);

        $manual = trim((string)($data['slug'] ?? ''));
        $studio = !empty($data['id'])
            ? Studio::query()->findOrFail($data['id'])
            : null;

        if ($studio) {
            $slug = $studio->slug;
        } elseif ($manual !== '') {
            $slug = \Illuminate\Support\Str::slug($manual);
        } else {
            $slug = \App\Support\SlugHelper::makeUnique(
                null,
                $data['title'],
                fn (string $candidate) => Studio::query()->where('slug', $candidate)->exists()
            );
        }

        if (!$studio && Studio::query()->where('slug', $slug)->exists()) {
            return response()->json(['ok' => false, 'error' => 'Slug already taken'], 422);
        }

        $attrs = [
            'title' => $data['title'],
            'meta_title' => $data['meta_title'] ?? null,
            'description' => $data['description'] ?? null,
            'meta_description' => $data['meta_description'] ?? null,
            'seo_html' => isset($data['seo_html']) ? str_replace("\r\n", "\n", (string)$data['seo_html']) : null,
            'logo_url' => $data['logo_url'] ?? null,
            'tmdb_id' => $data['tmdb_id'] ?? null,
            'tmdb_type' => $data['tmdb_type'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
            'is_pinned' => $data['is_pinned'] ?? false,
            'is_active' => $data['is_active'] ?? true,
            'is_hidden' => $data['is_hidden'] ?? false,
            'noindex' => $data['noindex'] ?? false,
        ];

        if ($studio) {
            $studio->update($attrs);
        } else {
            $studio = Studio::query()->create(array_merge($attrs, [
                'slug' => $slug,
            ]));
        }

        TplCache::bumpGlobalVersion();

        return response()->json(['ok' => true, 'item' => $studio->fresh()]);
    });

    Route::post('/studios/{slug}/logo', function (Request $request, string $slug) {
        $maxKb = (int)ceil(app(ImageOptimizer::class)->maxUploadBytes() / 1024);

        $request->validate([
            'logo' => ['required', 'file', 'image', 'max:' . $maxKb],
        ]);

        $studio = Studio::query()->where('slug', $slug)->firstOrFail();
        $url = app(PosterStorage::class)->storeFromUpload(
            $request->file('logo'),
            PosterContext::forStudio($studio),
        );
        $studio->logo_url = $url;
        $studio->save();

        return response()->json(['ok' => true, 'logo_url' => $url, 'item' => $studio]);
    });

    Route::get('/studios/{studio_slug}/items', function (string $studio_slug) {
        $studio = Studio::query()->where('slug', $studio_slug)->firstOrFail();

        return response()->json([
            'items' => StudioItem::query()
                ->where('studio_id', $studio->id)
                ->with('series:id,kp_id,tmdb_id,title,slug,year')
                ->orderBy('rank_order')
                ->get(),
        ]);
    });

    Route::post('/studios/{studio_slug}/items', function (Request $request, string $studio_slug) {
        $data = $request->validate([
            'items' => ['required', 'array'],
            'items.*.series_id' => ['nullable', 'integer'],
            'items.*.kp_id' => ['nullable', 'string'],
            'items.*.tmdb_id' => ['nullable', 'string'],
            'items.*.rank_order' => ['nullable', 'integer'],
        ]);

        $studio = Studio::query()->where('slug', $studio_slug)->firstOrFail();
        $added = 0;
        $skipped = 0;

        foreach ($data['items'] as $idx => $item) {
            $series = \App\Support\SeriesItemResolver::fromItem($item);
            if (!$series) {
                $skipped++;
                continue;
            }

            StudioItem::query()->updateOrCreate(
                ['studio_id' => $studio->id, 'series_id' => $series->id],
                ['rank_order' => $item['rank_order'] ?? (int) $idx]
            );

            if (!$series->studio_id) {
                $series->studio_id = $studio->id;
                $series->save();
            }
            $added++;
        }

        return response()->json(['ok' => true, 'added' => $added, 'skipped' => $skipped]);
    });

    Route::delete('/studios/{studio_slug}/items/{seriesKey}', function (string $studio_slug, string $seriesKey) {
        $studio = Studio::query()->where('slug', $studio_slug)->firstOrFail();
        $series = \App\Support\AdminSeriesResolver::byKey($seriesKey);

        StudioItem::query()
            ->where('studio_id', $studio->id)
            ->where('series_id', $series->id)
            ->delete();

        return response()->json(['ok' => true]);
    });

    Route::get('/comments', function (Request $request) {
        $status = $request->query('status', 'approved');

        $query = \App\Models\Comment::query()
            ->with(['user:id,name,email', 'series:id,title,slug,kp_id,year,start_year'])
            ->orderByDesc('id');

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        return response()->json([
            'items' => $query->limit(100)->get(),
        ]);
    });

    Route::get('/player-reports', function (Request $request) {
        $perPage = min(100, max(10, (int)$request->query('per_page', 50)));
        $page = max(1, (int)$request->query('page', 1));

        $paginator = \App\Models\PlayerReport::query()
            ->with([
                'series:id,kp_id,title,slug,year,start_year',
                'user:id,name,email',
            ])
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'items' => $paginator->items(),
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'last_page' => $paginator->lastPage(),
        ]);
    });

    Route::delete('/player-reports/{id}', function (int $id) {
        \App\Models\PlayerReport::query()->where('id', $id)->delete();

        return response()->json(['ok' => true]);
    });

    Route::post('/comments/{id}/status', function (Request $request, int $id) {
        $data = $request->validate([
            'status' => ['required', 'in:approved,rejected,pending'],
        ]);

        $comment = \App\Models\Comment::query()->with('series:id')->findOrFail($id);
        $comment->status = $data['status'];
        $comment->save();

        if ($comment->series_id) {
            TplCache::forgetSeries((int)$comment->series_id);
        }

        return response()->json(['ok' => true, 'item' => $comment]);
    });

    Route::post('/comments/{id}', function (Request $request, int $id) {
        $max = SiteConfig::int('comments_body_max_length');
        $data = $request->validate([
            'body' => ['required', 'string', 'max:' . $max],
        ]);

        $comment = \App\Models\Comment::query()->with('series:id')->findOrFail($id);
        $comment->body = CommentBody::assertValid($data['body']);
        $comment->save();

        if ($comment->series_id) {
            TplCache::forgetSeries((int)$comment->series_id);
        }

        $comment->load(['user:id,name,email', 'series:id,title,slug,kp_id,year,start_year']);

        return response()->json(['ok' => true, 'item' => $comment]);
    });

    Route::delete('/comments/{id}', function (int $id) {
        $comment = \App\Models\Comment::query()->find($id);
        if ($comment) {
            $seriesId = (int)$comment->series_id;
            $comment->delete();
            if ($seriesId > 0) {
                TplCache::forgetSeries($seriesId);
            }
        }

        return response()->json(['ok' => true]);
    });

    Route::post('/comments/{id}/pin', function (Request $request, int $id) {
        $data = $request->validate([
            'pinned' => ['required', 'boolean'],
        ]);

        $comment = \App\Models\Comment::query()->with('series:id')->findOrFail($id);

        if ($data['pinned'] && $comment->parent_id) {
            return response()->json([
                'ok' => false,
                'message' => 'Закреплять можно только комментарии верхнего уровня.',
            ], 422);
        }

        $comment->is_pinned = $data['pinned'];
        $comment->pinned_at = $data['pinned'] ? now() : null;
        $comment->save();

        if ($comment->series_id) {
            TplCache::forgetSeries((int)$comment->series_id);
        }

        return response()->json(['ok' => true, 'item' => $comment]);
    });

    Route::get('/settings', function () {
        $rows = \App\Models\SiteSetting::query()->orderBy('key')->get();
        $items = $rows->map(function ($row) {
            if ($row->key === KinoPoiskConfig::SETTING_KEY && trim((string)$row->value) !== '') {
                return ['id' => $row->id, 'key' => $row->key, 'value' => ''];
            }
            if ($row->key === AllohaConfig::SETTING_KEY && trim((string)$row->value) !== '') {
                return ['id' => $row->id, 'key' => $row->key, 'value' => ''];
            }
            if ($row->key === TmdbConfig::SETTING_KEY && trim((string)$row->value) !== '') {
                return ['id' => $row->id, 'key' => $row->key, 'value' => ''];
            }

            return ['id' => $row->id, 'key' => $row->key, 'value' => $row->value];
        });

        return response()->json([
            'items' => $items,
            'kinopoisk_api_key_set' => KinoPoiskConfig::isConfigured(),
            'alloha_api_token_set' => AllohaConfig::isConfigured(),
            'tmdb_api_key_set' => TmdbConfig::isConfigured(),
            'comments_auto_approve' => CommentModeration::autoApproveEnabled(),
            'admin_path' => AdminPath::path(),
            'admin_base' => AdminPath::base(),
            'robots_txt_default' => RobotsTxt::defaultTemplate(),
            'robots_txt_effective' => RobotsTxt::content(),
            'robots_url' => url('/robots.txt'),
            'config_schema' => SiteConfig::adminGroups(),
            'config_seo_fields' => SiteConfig::seoFields(),
        ]);
    });

    Route::get('/themes', function () {
        return response()->json([
            'items' => ThemeManager::listThemes(),
            'active' => ThemeManager::activeName(),
        ]);
    });

    Route::post('/branding/logo', function (Request $request) {
        $request->validate([
            'logo' => ['required', 'file', 'max:2048'],
        ]);

        try {
            $url = app(BrandingStorage::class)->storeLogo($request->file('logo'));
        } catch (\InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }

        TplCache::bumpGlobalVersion();

        return response()->json(['ok' => true, 'logo_url' => $url]);
    });

    Route::delete('/branding/logo', function () {
        app(BrandingStorage::class)->deleteLogo();
        TplCache::bumpGlobalVersion();

        return response()->json(['ok' => true]);
    });

    Route::post('/branding/background', function (Request $request) {
        $request->validate([
            'background' => ['required', 'file', 'max:2048'],
        ]);

        try {
            $url = app(BrandingStorage::class)->storeBackground($request->file('background'));
        } catch (\InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }

        TplCache::bumpGlobalVersion();

        return response()->json(['ok' => true, 'background_url' => $url]);
    });

    Route::delete('/branding/background', function () {
        app(BrandingStorage::class)->deleteBackground();
        TplCache::bumpGlobalVersion();

        return response()->json(['ok' => true]);
    });

    Route::post('/branding/favicon', function (Request $request) {
        $request->validate([
            'favicon' => ['required', 'file', 'max:2048'],
        ]);

        try {
            $url = app(BrandingStorage::class)->storeFavicon($request->file('favicon'));
        } catch (\InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }

        TplCache::bumpGlobalVersion();

        return response()->json(['ok' => true, 'favicon_url' => $url]);
    });

    Route::delete('/branding/favicon', function () {
        app(BrandingStorage::class)->deleteFavicon();
        TplCache::bumpGlobalVersion();

        return response()->json(['ok' => true]);
    });

    Route::post('/settings', function (Request $request) {
        $data = $request->validate([
            'settings' => ['required', 'array'],
            'settings.*.key' => ['required', 'string'],
            'settings.*.value' => ['nullable', 'string'],
        ]);

        $oldTheme = \App\Models\SiteSetting::get('active_theme');
        $previousAdminPath = AdminPath::path();
        $themeChanged = false;
        $configChanged = false;
        $adminPathChanged = false;

        foreach ($data['settings'] as $row) {
            $key = $row['key'];
            $value = $row['value'] ?? null;

            if ($key === KinoPoiskConfig::SETTING_KEY) {
                $value = trim((string)($value ?? ''));
                if ($value === '') {
                    continue;
                }
            }

            if ($key === AllohaConfig::SETTING_KEY) {
                $value = trim((string)($value ?? ''));
                if ($value === '') {
                    continue;
                }
            }

            if ($key === TmdbConfig::SETTING_KEY) {
                $value = trim((string)($value ?? ''));
                if ($value === '') {
                    continue;
                }
            }

            if ($key === AdminPath::SETTING_KEY) {
                $value = AdminPath::normalize((string)($value ?? ''));
                $error = AdminPath::validate($value);
                if ($error !== null) {
                    return response()->json(['ok' => false, 'error' => $error], 422);
                }
                if ($value !== $previousAdminPath) {
                    $adminPathChanged = true;
                }
            }

            if ($key === CommentModeration::SETTING_KEY) {
                $value = ($value === '1' || $value === true || $value === 'true') ? '1' : '0';
            }

            if ($key === 'active_theme' && is_string($value) && $value !== '') {
                if (!ThemeManager::themeExists($value)) {
                    return response()->json(['ok' => false, 'error' => "Theme not found: {$value}"], 422);
                }
            }

            if ($key === RobotsTxt::SETTING_KEY) {
                $value = str_replace("\r\n", "\n", (string)($value ?? ''));
                if (strlen($value) > 65535) {
                    return response()->json(['ok' => false, 'error' => 'robots.txt слишком длинный (макс. 65535 символов)'], 422);
                }
            }

            if ($key === 'site_background_header_offset') {
                $value = (string)max(0, min(600, (int)$value));
                $configChanged = true;
            }

            if ($key === 'site_background_color') {
                $normalized = \App\Support\SiteBranding::normalizeColor($value);
                if ($normalized === null) {
                    return response()->json(['ok' => false, 'error' => 'Цвет фона: ожидается HEX (#111 или #111111)'], 422);
                }
                $value = $normalized;
                $configChanged = true;
            }

            if ($key === 'site_background_hide_mobile') {
                $value = ($value === '1' || $value === true || $value === 'true') ? '1' : '0';
                $configChanged = true;
            }

            if (in_array($key, SiteConfig::managedKeys(), true)) {
                $normalized = SiteConfig::normalizeForSave($key, $value);
                if ($normalized === null) {
                    continue;
                }
                $value = $normalized;
                $configChanged = true;
            }

            \App\Models\SiteSetting::set($key, $value);

            if ($key === 'active_theme' && $oldTheme !== $value) {
                $themeChanged = true;
            }
        }

        if ($themeChanged || $configChanged) {
            Cache::flush();
        }

        if ($adminPathChanged) {
            Artisan::call('route:clear');
            if (app()->environment('production')) {
                Artisan::call('route:cache');
            }
        }

        return response()->json([
            'ok' => true,
            'active_theme' => ThemeManager::activeName(),
            'admin_path' => AdminPath::path(),
            'admin_base' => AdminPath::base(),
        ]);
    });

    Route::post('/sync/kp', function (Request $request) {
        $data = $request->validate([
            'keyword' => ['required', 'string'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'download_poster' => ['nullable', 'boolean'],
        ]);

        $limit = $data['limit'] ?? 20;
        $args = [
            'keyword' => $data['keyword'],
            '--limit' => $limit,
        ];
        if (!empty($data['download_poster'])) {
            $args['--download-poster'] = true;
        }

        $run = CronRunLogger::run(
            CronRunLogger::JOB_KP_SYNC,
            'kp:sync',
            CronRun::TRIGGER_ADMIN,
            function () use ($args) {
                \Illuminate\Support\Facades\Artisan::call('kp:sync', $args);
                $output = trim(\Illuminate\Support\Facades\Artisan::output());

                return [
                    'status' => CronRun::STATUS_SUCCESS,
                    'message' => 'KinoPoisk sync завершён',
                    'log' => $output,
                    'counts' => ['limit' => (int)($args['--limit'] ?? 0)],
                ];
            },
            ['keyword' => $data['keyword'], 'limit' => $limit],
            'Импорт KinoPoisk',
        );

        return response()->json([
            'ok' => true,
            'output' => (string)($run->log ?: $run->message),
            'cron_run_id' => $run->id,
        ]);
    });

    Route::post('/sync/alloha', function (Request $request) {
        $data = $request->validate([
            'kp_id' => ['nullable', 'string'],
            'mode' => ['nullable', 'in:all,ratings,players'],
            'sleep' => ['nullable', 'numeric', 'min:0', 'max:30'],
        ]);

        $args = [];
        if (!empty($data['kp_id'])) {
            $args['--kp-id'] = $data['kp_id'];
        }
        if (($data['mode'] ?? 'all') === 'ratings') {
            $args['--ratings-only'] = true;
        } elseif (($data['mode'] ?? 'all') === 'players') {
            $args['--players-only'] = true;
        }
        if (isset($data['sleep']) && (float)$data['sleep'] > 0) {
            $args['--sleep'] = (float)$data['sleep'];
        }

        \Illuminate\Support\Facades\Artisan::call('alloha:sync', $args);

        return response()->json([
            'ok' => true,
            'output' => \Illuminate\Support\Facades\Artisan::output(),
        ]);
    });

    Route::post('/sync/alloha-import', function (Request $request) {
        $data = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:500'],
            'download_poster' => ['nullable', 'boolean'],
            'sleep' => ['nullable', 'numeric', 'min:0', 'max:30'],
        ]);

        $args = [
            '--limit' => $data['limit'] ?? 50,
        ];
        if (!empty($data['download_poster'])) {
            $args['--download-poster'] = true;
        }
        if (isset($data['sleep'])) {
            $args['--sleep'] = (float)$data['sleep'];
        }

        \Illuminate\Support\Facades\Artisan::call('alloha:import', $args);

        return response()->json([
            'ok' => true,
            'output' => \Illuminate\Support\Facades\Artisan::output(),
        ]);
    });

    Route::get('/alloha/auto-sync', function () {
        $settings = AllohaAutoSyncSettings::get();
        $lastRun = AllohaAutoSyncSettings::lastRunAt();

        return response()->json([
            'settings' => $settings,
            'interval_options' => AllohaAutoSyncSettings::intervalOptions(),
            'last_run_at' => $lastRun,
            'last_run_human' => $lastRun ? date('d.m.Y H:i:s', $lastRun) : null,
            'is_due' => AllohaAutoSyncSettings::isDue(),
            'alloha_api_token_set' => AllohaConfig::isConfigured(),
        ]);
    });

    Route::post('/alloha/auto-sync', function (Request $request) {
        $data = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'interval_minutes' => ['nullable', 'integer'],
            'latest_days' => ['nullable', 'integer', 'min:1', 'max:30'],
            'auto_add_new' => ['nullable', 'boolean'],
            'new_is_hidden' => ['nullable', 'boolean'],
            'new_is_active' => ['nullable', 'boolean'],
            'download_poster_new' => ['nullable', 'boolean'],
            'update_existing' => ['nullable', 'boolean'],
            'update_ratings' => ['nullable', 'boolean'],
            'update_players' => ['nullable', 'boolean'],
            'update_metadata' => ['nullable', 'boolean'],
            'update_poster' => ['nullable', 'boolean'],
            'update_genres_countries' => ['nullable', 'boolean'],
            'fill_empty_only' => ['nullable', 'boolean'],
        ]);

        $current = AllohaAutoSyncSettings::get();
        $merged = AllohaAutoSyncSettings::normalize(array_merge($current, $data));
        AllohaAutoSyncSettings::save($merged);

        return response()->json(['ok' => true, 'settings' => $merged]);
    });

    Route::post('/sync/alloha-latest', function (Request $request, AllohaLatestSyncService $service) {
        if (!AllohaConfig::isConfigured()) {
            return response()->json(['ok' => false, 'error' => 'API-токен Alloha не настроен'], 422);
        }

        $data = $request->validate([
            'days' => ['nullable', 'integer', 'min:1', 'max:30'],
            'use_saved_settings' => ['nullable', 'boolean'],
            'settings' => ['nullable', 'array'],
        ]);

        $settings = ($data['use_saved_settings'] ?? true)
            ? AllohaAutoSyncSettings::get()
            : AllohaAutoSyncSettings::normalize($data['settings'] ?? []);

        if (isset($data['days'])) {
            $settings['latest_days'] = (int)$data['days'];
        }

        $result = CronRunLogger::run(
            CronRunLogger::JOB_ALLOHA_LATEST,
            'alloha:latest',
            CronRun::TRIGGER_ADMIN,
            function () use ($service, $settings) {
                $result = $service->run($settings);
                AllohaAutoSyncSettings::markRun();

                if (($result['added'] + $result['updated']) > 0) {
                    app(\App\Services\SitemapService::class)->markDirty();
                }

                return [
                    'status' => $result['failed'] > 0 ? CronRun::STATUS_FAILED : CronRun::STATUS_SUCCESS,
                    'counts' => [
                        'added' => $result['added'],
                        'updated' => $result['updated'],
                        'skipped' => $result['skipped'],
                        'failed' => $result['failed'],
                        'kp_ids' => count($result['kp_ids']),
                    ],
                    'message' => sprintf(
                        'Добавлено: %d, обновлено: %d, пропущено: %d, ошибок: %d',
                        $result['added'],
                        $result['updated'],
                        $result['skipped'],
                        $result['failed'],
                    ),
                    'log' => $result['log'],
                ];
            },
            ['latest_days' => $settings['latest_days']],
            'Проверка последних Alloha (админка)',
        )->fresh();

        $payload = [
            'added' => (int)($result->counts['added'] ?? 0),
            'updated' => (int)($result->counts['updated'] ?? 0),
            'skipped' => (int)($result->counts['skipped'] ?? 0),
            'failed' => (int)($result->counts['failed'] ?? 0),
            'log' => $result->log ? explode("\n", $result->log) : [],
        ];

        return response()->json([
            'ok' => true,
            'result' => $payload,
            'output' => (string)$result->message,
            'cron_run_id' => $result->id,
        ]);
    });

    Route::get('/tmdb/auto-sync', function () {
        $settings = TmdbAutoSyncSettings::get();
        $lastRun = TmdbAutoSyncSettings::lastRunAt();

        return response()->json([
            'settings' => $settings,
            'last_run_at' => $lastRun ? date('c', $lastRun) : null,
            'is_due' => TmdbAutoSyncSettings::isDue(),
            'tmdb_api_key_set' => TmdbConfig::isConfigured(),
        ]);
    });

    Route::post('/tmdb/auto-sync', function (Request $request) {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        TmdbAutoSyncSettings::save([
            'enabled' => (bool)$data['enabled'],
        ]);

        return response()->json(['ok' => true, 'settings' => TmdbAutoSyncSettings::get()]);
    });

    Route::post('/sync/tmdb-popularity', function (\Illuminate\Http\Request $request, TmdbPopularitySyncService $service) {
        if (!TmdbConfig::isConfigured()) {
            return response()->json(['ok' => false, 'error' => 'API-ключ TMDB не настроен'], 422);
        }

        $data = $request->validate([
            'restart' => ['nullable', 'boolean'],
            'continue' => ['nullable', 'boolean'],
        ]);

        $restart = (bool)($data['restart'] ?? false);
        $continue = (bool)($data['continue'] ?? false);

        // Progressive batches — does not process all series in one HTTP request.
        $progress = $service->runProgressiveBatch($restart || (!$continue), true);

        return response()->json([
            'ok' => true,
            'progress' => $progress,
            'done' => ($progress['status'] ?? '') === 'done',
            'result' => [
                'updated' => $progress['updated'] ?? 0,
                'failed' => $progress['failed'] ?? 0,
                'status_changed' => $progress['status_changed'] ?? 0,
                'schedule_synced' => $progress['schedule_synced'] ?? 0,
                'studios_linked' => $progress['studios_linked'] ?? 0,
                'studio_logos' => $progress['studio_logos'] ?? 0,
                'processed' => $progress['processed'] ?? 0,
                'total' => $progress['total'] ?? 0,
            ],
            'output' => (string)($progress['message'] ?? ''),
        ]);
    });

    Route::get('/sync/tmdb-popularity/progress', function () {
        return response()->json([
            'ok' => true,
            'progress' => TmdbSyncProgress::get(),
        ]);
    });

    Route::post('/sync/tmdb-studio-logos', function (TmdbStudioSyncService $studioSync) {
        if (!TmdbConfig::isConfigured()) {
            return response()->json(['ok' => false, 'error' => 'API-ключ TMDB не настроен'], 422);
        }

        @set_time_limit(120);
        $result = $studioSync->fillMissingLogos(100);

        return response()->json([
            'ok' => true,
            'result' => $result,
            'output' => sprintf(
                'Логотипы: проверено %d, скачано %d, без лого %d',
                $result['checked'],
                $result['downloaded'],
                $result['failed'],
            ),
        ]);
    });

    Route::get('/sitemap', function (SitemapService $sitemap) {
        $path = $sitemap->path();
        $mtime = is_file($path) ? filemtime($path) : null;

        return response()->json([
            'url' => url('/sitemap.xml'),
            'url_count' => $sitemap->urlCount(),
            'last_modified_at' => $mtime ? date('c', $mtime) : null,
            'is_dirty' => $sitemap->isDirty(),
            'is_stale' => $sitemap->isStale(),
        ]);
    });

    Route::post('/sitemap/generate', function (SitemapService $sitemap) {
        try {
            $run = CronRunLogger::run(
                CronRunLogger::JOB_SITEMAP,
                'sitemap:generate',
                CronRun::TRIGGER_ADMIN,
                function () use ($sitemap) {
                    $sitemap->generate();
                    $urlCount = $sitemap->urlCount();

                    return [
                        'status' => CronRun::STATUS_SUCCESS,
                        'counts' => ['urls' => $urlCount],
                        'message' => 'Sitemap обновлён (' . $urlCount . ' URL)',
                    ];
                },
                ['force' => true],
                'Генерация sitemap (админка)',
            );
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'ok' => false,
                'error' => $e->getMessage() ?: 'Не удалось сгенерировать sitemap.',
            ], 500);
        }

        $path = $sitemap->path();
        $mtime = is_file($path) ? filemtime($path) : null;

        return response()->json([
            'ok' => true,
            'url_count' => $sitemap->urlCount(),
            'last_modified_at' => $mtime ? date('c', $mtime) : null,
            'message' => (string)$run->message,
            'cron_run_id' => $run->id,
        ]);
    });

    Route::get('/backup/settings', function (BackupService $backup) {
        $settings = BackupSettings::get();
        $lastRun = BackupSettings::lastRunAt();

        return response()->json([
            'settings' => $settings,
            'interval_options' => BackupSettings::intervalOptions(),
            'last_run_at' => $lastRun,
            'last_run_human' => $lastRun ? date('d.m.Y H:i:s', $lastRun) : null,
            'is_due' => BackupSettings::isDue(),
            'remote_password_set' => BackupSettings::hasPassword(),
            's3_secret_set' => BackupSettings::hasS3Secret(),
            'remote_configured' => BackupSettings::isRemoteConfigured(),
            'local_backups' => $backup->listLocalBackups(),
            'backups' => $backup->listAvailableBackups(),
        ]);
    });

    Route::post('/backup/settings', function (Request $request) {
        $data = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'interval_minutes' => ['nullable', 'integer'],
            'include_database' => ['nullable', 'boolean'],
            'include_files' => ['nullable', 'boolean'],
            'remote_enabled' => ['nullable', 'boolean'],
            'protocol' => ['nullable', 'string', Rule::in(['ftp', 'sftp', 's3'])],
            'host' => ['nullable', 'string', 'max:255'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:500'],
            'remote_path' => ['nullable', 'string', 'max:500'],
            's3_key' => ['nullable', 'string', 'max:255'],
            's3_secret' => ['nullable', 'string', 'max:500'],
            's3_region' => ['nullable', 'string', 'max:100'],
            's3_bucket' => ['nullable', 'string', 'max:255'],
            's3_endpoint' => ['nullable', 'string', 'max:500'],
            's3_path_style' => ['nullable', 'boolean'],
            'retention_count' => ['nullable', 'integer', 'min:1', 'max:100'],
            'passive' => ['nullable', 'boolean'],
        ]);

        $current = BackupSettings::get();
        $merged = BackupSettings::normalize(array_merge(
            $current,
            collect($data)->except(['password', 's3_secret'])->all(),
        ));

        if (array_key_exists('password', $data) && is_string($data['password']) && $data['password'] !== '') {
            BackupSettings::setPassword($data['password']);
        }

        if (array_key_exists('s3_secret', $data) && is_string($data['s3_secret']) && $data['s3_secret'] !== '') {
            BackupSettings::setS3Secret($data['s3_secret']);
        }

        BackupSettings::save($merged);

        return response()->json([
            'ok' => true,
            'settings' => $merged,
            'remote_password_set' => BackupSettings::hasPassword(),
            's3_secret_set' => BackupSettings::hasS3Secret(),
            'remote_configured' => BackupSettings::isRemoteConfigured(),
        ]);
    });

    Route::post('/backup/test-connection', function (BackupRemoteStorage $remote) {
        try {
            $remote->testConnection();

            return response()->json(['ok' => true, 'message' => 'Подключение успешно']);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => (string)Utf8::sanitize($e->getMessage())], 422);
        }
    });

    Route::post('/backup/run', function () {
        try {
            $exitCode = Artisan::call('backup:run', [
                '--force' => true,
                '--trigger' => 'admin',
            ]);
            $output = trim((string)Utf8::sanitize(Artisan::output()));
            $lastRun = \App\Models\CronRun::query()
                ->where('job_key', CronRunLogger::JOB_BACKUP)
                ->orderByDesc('id')
                ->first();

            if ($exitCode !== 0) {
                return response()->json([
                    'ok' => false,
                    'error' => (string)Utf8::sanitize($lastRun?->error) ?: ($output ?: 'Ошибка бэкапа'),
                    'cron_run_id' => $lastRun?->id,
                ], 500);
            }

            return response()->json([
                'ok' => true,
                'message' => (string)Utf8::sanitize($lastRun?->message) ?: ($output ?: 'Бэкап создан'),
                'cron_run_id' => $lastRun?->id,
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['ok' => false, 'error' => (string)Utf8::sanitize($e->getMessage())], 500);
        }
    });

    Route::post('/backup/restore', function (Request $request, BackupService $backup) {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'source' => ['required', 'string', Rule::in(['local', 'remote'])],
            'restore_database' => ['nullable', 'boolean'],
            'restore_files' => ['nullable', 'boolean'],
        ]);

        try {
            $run = CronRunLogger::run(
                CronRunLogger::JOB_BACKUP_RESTORE,
                'backup:restore',
                CronRun::TRIGGER_ADMIN,
                fn () => $backup->restore(
                    $data['name'],
                    $data['source'],
                    (bool)($data['restore_database'] ?? true),
                    (bool)($data['restore_files'] ?? true),
                ),
                ['name' => $data['name'], 'source' => $data['source']],
                'Восстановление из бэкапа',
            );

            if ($run->status === CronRun::STATUS_FAILED) {
                return response()->json([
                    'ok' => false,
                    'error' => (string)Utf8::sanitize($run->error) ?: 'Ошибка восстановления',
                    'cron_run_id' => $run->id,
                ], 500);
            }

            return response()->json([
                'ok' => true,
                'message' => (string)Utf8::sanitize($run->message),
                'cron_run_id' => $run->id,
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['ok' => false, 'error' => (string)Utf8::sanitize($e->getMessage())], 500);
        }
    });
});

