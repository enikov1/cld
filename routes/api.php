<?php

use App\Http\Controllers\AdminCollectionController;
use App\Http\Controllers\AdminRedirectController;
use App\Http\Controllers\AdminNavController;
use App\Http\Controllers\AdminStudioController;
use App\Http\Controllers\AdminTemplateController;
use App\Http\Controllers\AdminHomeSectionController;
use App\Http\Controllers\AdminReactionController;
use App\Http\Controllers\AdminReviewController;
use App\Http\Controllers\AdminPlayersController;
use App\Http\Controllers\AdminScheduleController;
use App\Http\Controllers\AdminSearchController;
use App\Http\Controllers\AdminGlobalSearchController;
use App\Http\Controllers\AdminMediaController;
use App\Http\Controllers\AdminSeriesController;
use App\Http\Controllers\AdminTaxonomyController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AdminTokensController;
use App\Http\Controllers\AdminAuditLogController;
use App\Http\Controllers\AdminCacheController;
use App\Http\Controllers\AdminSystemController;
use App\Http\Controllers\AdminViewsStatsController;
use App\Models\Collection;
use App\Models\CronRun;
use App\Models\Series;
use App\Models\Studio;
use App\Services\KinoPoiskBulkProgress;
use App\Services\KinoPoiskBulkSync;
use App\Services\KinoPoiskConfig;
use App\Services\AllohaConfig;
use App\Services\AllohaAutoSyncSettings;
use App\Services\AllohaLatestSyncService;
use App\Services\AdminViewsStatsService;
use App\Services\CronRunLogger;
use App\Services\TmdbConfig;
use App\Services\TmdbAutoSyncSettings;
use App\Services\TmdbPopularitySyncService;
use App\Services\TmdbStudioSyncService;
use App\Services\TmdbSyncProgress;
use App\Support\AdminAccess;
use App\Support\AdminAudit;
use App\Support\AdminPath;
use App\Support\CommentBody;
use App\Support\CommentModeration;
use App\Support\ReviewModeration;
use App\Support\EncryptedSiteSecret;
use App\Support\RobotsTxt;
use App\Support\SiteConfig;
use App\Support\ThemeManager;
use App\Support\TplCache;
use App\Support\Utf8;
use App\Support\ArtisanDetached;
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

Route::middleware(['throttle:admin-api', 'admin.token'])->prefix('admin')->group(function () {
    Route::post('/site-access', function (Request $request) {
        $cookie = AdminAccess::makeCookie();
        $response = response()->json(['ok' => true]);
        if ($cookie) {
            AdminAudit::log('admin.login', 'session', null, 'Вход в админку (сессия)', null, $request);
        }

        return $cookie ? $response->withCookie($cookie) : $response;
    })->middleware('throttle:admin-auth');

    Route::delete('/site-access', function () {
        return response()->json(['ok' => true])->withCookie(AdminAccess::forgetCookie());
    })->middleware('throttle:admin-auth');

    Route::get('/me', [AdminTokensController::class, 'me']);

    Route::get('/moderation-counts', function () {
        return response()->json([
            'comments_pending' => \App\Models\Comment::query()->where('status', 'pending')->count(),
            'reviews_pending' => \App\Models\Review::query()->where('status', 'pending')->count(),
            'player_reports_total' => \App\Models\PlayerReport::query()->count(),
        ]);
    });

    Route::get('/admin-tokens/meta', [AdminTokensController::class, 'meta']);
    Route::get('/admin-tokens', [AdminTokensController::class, 'index']);
    Route::post('/admin-tokens', [AdminTokensController::class, 'store']);
    Route::get('/admin-tokens/{id}', [AdminTokensController::class, 'show'])->whereNumber('id');
    Route::put('/admin-tokens/{id}', [AdminTokensController::class, 'update'])->whereNumber('id');
    Route::post('/admin-tokens/{id}/regenerate', [AdminTokensController::class, 'regenerate'])->whereNumber('id');
    Route::delete('/admin-tokens/{id}', [AdminTokensController::class, 'destroy'])->whereNumber('id');

    Route::get('/audit-logs', [AdminAuditLogController::class, 'index']);

    Route::get('/stats', function () {
        return response()->json(Cache::remember('admin_inventory_stats', 60, function () {
            return [
                'series_total' => Series::query()->count(),
                'series_active' => Series::query()->where('is_active', true)->count(),
                'collections' => Collection::query()->count(),
                'collections_active' => Collection::query()->where('is_active', true)->count(),
                'studios' => Studio::query()->count(),
                'studios_active' => Studio::query()->where('is_active', true)->count(),
                'comments_total' => \App\Models\Comment::query()->count(),
                'comments_pending' => \App\Models\Comment::query()->where('status', 'pending')->count(),
                'reviews_total' => \App\Models\Review::query()->count(),
                'reviews_pending' => \App\Models\Review::query()->where('status', 'pending')->count(),
                'player_reports_total' => \App\Models\PlayerReport::query()->count(),
                'player_reports_today' => \App\Models\PlayerReport::query()->where('created_at', '>=', now()->startOfDay())->count(),
                'users_total' => \App\Models\User::query()->count(),
                'users_blocked' => \App\Models\User::query()->where('is_blocked', true)->count(),
                'series_with_player' => Series::query()
                    ->where(function ($q) {
                        $q->whereExists(function ($sub) {
                            $sub->selectRaw('1')
                                ->from('player_sources')
                                ->whereColumn('player_sources.series_id', 'series.id')
                                ->where('player_sources.is_active', true);
                        })->orWhere(function ($legacy) {
                            $legacy->whereNotNull('player_url')->where('player_url', '!=', '');
                        });
                    })
                    ->count(),
                'active_theme' => ThemeManager::activeName(),
                'views' => AdminViewsStatsService::dashboardSnapshot(),
            ];
        }));
    });

    Route::get('/views-stats', [AdminViewsStatsController::class, 'index']);
    Route::get('/views-stats/summary', [AdminViewsStatsController::class, 'summary']);

    Route::get('/cache', [AdminCacheController::class, 'info']);
    Route::post('/cache/clear', [AdminCacheController::class, 'clear'])->middleware('throttle:admin-destructive');
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
    Route::get('/series/{kp_id}/poster-meta', [AdminSeriesController::class, 'posterMeta']);
    Route::get('/series/{kp_id}/tmdb-images', [AdminSeriesController::class, 'tmdbImages']);
    Route::post('/series/{kp_id}/brand', [AdminSeriesController::class, 'uploadBrand']);
    Route::delete('/series/{kp_id}/brand', [AdminSeriesController::class, 'destroyBrand']);
    Route::get('/series/{kp_id}/brand-meta', [AdminSeriesController::class, 'brandMeta']);
    Route::post('/series/{kp_id}/gallery', [AdminSeriesController::class, 'uploadGallery']);
    Route::delete('/series/{kp_id}/gallery', [AdminSeriesController::class, 'destroyGalleryItem']);

    Route::get('/media', [AdminMediaController::class, 'index']);
    Route::post('/media/upload', [AdminMediaController::class, 'upload']);
    Route::delete('/media', [AdminMediaController::class, 'destroy']);
    Route::post('/series/{kp_id}/pin', [AdminSeriesController::class, 'pin']);
    Route::post('/series/{kp_id}/visibility', [AdminSeriesController::class, 'visibility']);
    Route::delete('/series/{kp_id}', [AdminSeriesController::class, 'destroy']);
    Route::post('/series/{kp_id}/restore', [AdminSeriesController::class, 'restore']);
    Route::get('/series/{kp_id}/seo-ai-prompt', [AdminSeriesController::class, 'seoAiPrompt']);
    Route::get('/series/{kp_id}/schedule', [AdminScheduleController::class, 'show']);
    Route::post('/series/{kp_id}/schedule', [AdminScheduleController::class, 'save']);
    Route::post('/series/{kp_id}/schedule/import-tmdb', [AdminScheduleController::class, 'importFromTmdb']);
    Route::get('/series/{kp_id}/players', [AdminPlayersController::class, 'show']);
    Route::post('/series/{kp_id}/players/add-alloha', [AdminPlayersController::class, 'addAllohaPlayer']);
    Route::get('/series/{kp_id}/players/rutube-trailer/search', [AdminPlayersController::class, 'searchRutubeTrailers']);
    Route::post('/series/{kp_id}/players/add-rutube-trailer', [AdminPlayersController::class, 'addRutubeTrailer']);
    Route::post('/series/{kp_id}/players', [AdminPlayersController::class, 'save']);
    Route::post('/players/alloha/sync-all', [AdminPlayersController::class, 'syncAllohaAll']);
    Route::get('/players/alloha/sync-progress', [AdminPlayersController::class, 'allohaSyncProgress']);
    Route::post('/players/alloha/sync-pause', [AdminPlayersController::class, 'pauseAllohaSync']);
    Route::post('/players/alloha/sync-resume', [AdminPlayersController::class, 'resumeAllohaSync']);
    Route::post('/players/alloha/sync-stop', [AdminPlayersController::class, 'stopAllohaSync']);
    Route::post('/players/rutube-trailer/sync-all', [AdminPlayersController::class, 'syncRutubeTrailersAll']);
    Route::get('/players/rutube-trailer/sync-progress', [AdminPlayersController::class, 'rutubeTrailerSyncProgress']);
    Route::post('/players/rutube-trailer/sync-pause', [AdminPlayersController::class, 'pauseRutubeTrailerSync']);
    Route::post('/players/rutube-trailer/sync-resume', [AdminPlayersController::class, 'resumeRutubeTrailerSync']);
    Route::post('/players/rutube-trailer/sync-stop', [AdminPlayersController::class, 'stopRutubeTrailerSync']);
    Route::post('/players/cdnvideohub/sync-all', [AdminPlayersController::class, 'syncCdnVideoHubAll']);
    Route::get('/players/cdnvideohub/sync-progress', [AdminPlayersController::class, 'cdnVideoHubSyncProgress']);
    Route::post('/players/cdnvideohub/sync-pause', [AdminPlayersController::class, 'pauseCdnVideoHubSync']);
    Route::post('/players/cdnvideohub/sync-resume', [AdminPlayersController::class, 'resumeCdnVideoHubSync']);
    Route::post('/players/cdnvideohub/sync-stop', [AdminPlayersController::class, 'stopCdnVideoHubSync']);

    Route::get('/users', [AdminUserController::class, 'index']);
    Route::post('/users', [AdminUserController::class, 'store']);
    Route::post('/users/{id}', [AdminUserController::class, 'update']);
    Route::delete('/users/{id}', [AdminUserController::class, 'destroy']);

    Route::get('/search-stats', [AdminSearchController::class, 'index']);
    Route::post('/search-stats/clear', [AdminSearchController::class, 'clear']);
    Route::delete('/search-stats/logs/{id}', [AdminSearchController::class, 'destroyLog']);
    Route::delete('/search-stats/{id}', [AdminSearchController::class, 'destroy']);

    Route::get('/global-search', [AdminGlobalSearchController::class, 'search']);

    Route::get('/taxonomies/options', [AdminTaxonomyController::class, 'options']);
    Route::post('/taxonomies/voices/sync-alloha', [AdminTaxonomyController::class, 'syncVoicesFromAlloha']);
    Route::get('/taxonomies/voices/sync-alloha/progress', [AdminTaxonomyController::class, 'voicesSyncProgress']);
    Route::post('/taxonomies/voices/sync-alloha/stop', [AdminTaxonomyController::class, 'stopVoicesSync']);
    Route::delete('/taxonomies/{type}', [AdminTaxonomyController::class, 'destroyAll']);
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
    Route::get('/reactions/stats', [AdminReactionController::class, 'stats']);
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
    Route::get('/templates/guide', [AdminTemplateController::class, 'guide']);
    Route::get('/templates/guide/download', [AdminTemplateController::class, 'guideDownload']);
    Route::get('/templates/css-classes', [AdminTemplateController::class, 'cssClasses']);

    Route::get('/redirects', [AdminRedirectController::class, 'index']);
    Route::get('/redirects/series-options', [AdminRedirectController::class, 'seriesOptions']);
    Route::post('/redirects/upsert', [AdminRedirectController::class, 'upsert']);
    Route::post('/redirects/{id}/toggle', [AdminRedirectController::class, 'toggle']);
    Route::delete('/redirects/{id}', [AdminRedirectController::class, 'destroy']);

    Route::get('/collections', [AdminCollectionController::class, 'index']);
    Route::get('/collections/ai-prompt', [AdminCollectionController::class, 'aiPrompt']);
    Route::post('/collections/ai-import', [AdminCollectionController::class, 'aiImport']);
    Route::post('/collections/upsert', [AdminCollectionController::class, 'upsert']);
    Route::post('/collections/{collection_slug}/auto-sync', [AdminCollectionController::class, 'autoSync']);
    Route::post('/collections/{slug}/cover', [AdminCollectionController::class, 'uploadCover']);
    Route::delete('/collections/{slug}/cover', [AdminCollectionController::class, 'destroyCover']);
    Route::get('/collections/{collection_slug}/items', [AdminCollectionController::class, 'items']);
    Route::post('/collections/{collection_slug}/items', [AdminCollectionController::class, 'saveItems']);
    Route::delete('/collections/{collection_slug}/items/{seriesKey}', [AdminCollectionController::class, 'destroyItem']);
    Route::delete('/collections/{collection_slug}', [AdminCollectionController::class, 'destroy']);

    Route::get('/studios', [AdminStudioController::class, 'index']);
    Route::post('/studios/upsert', [AdminStudioController::class, 'upsert']);
    Route::post('/studios/{slug}/logo', [AdminStudioController::class, 'uploadLogo']);
    Route::get('/studios/{studio_slug}/items', [AdminStudioController::class, 'items']);
    Route::post('/studios/{studio_slug}/items', [AdminStudioController::class, 'saveItems']);
    Route::delete('/studios/{studio_slug}/items/{seriesKey}', [AdminStudioController::class, 'destroyItem']);
    Route::delete('/studios/{studio_slug}', [AdminStudioController::class, 'destroy']);

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

    Route::get('/reviews', [AdminReviewController::class, 'index']);
    Route::post('/reviews', [AdminReviewController::class, 'store']);
    Route::post('/reviews/{id}/status', [AdminReviewController::class, 'updateStatus'])->where('id', '[0-9]+');
    Route::post('/reviews/{id}', [AdminReviewController::class, 'update'])->where('id', '[0-9]+');
    Route::delete('/reviews/{id}', [AdminReviewController::class, 'destroy'])->where('id', '[0-9]+');

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

        $comment = \App\Models\Comment::query()->with(['series', 'user'])->findOrFail($id);
        $previousStatus = (string) $comment->status;
        $comment->status = $data['status'];
        $comment->save();

        if ($comment->series_id) {
            TplCache::forgetSeries((int) $comment->series_id);
        }

        \App\Services\ModerationNotifier::commentApproved($comment, $previousStatus);

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
            'reviews_auto_approve' => ReviewModeration::autoApproveEnabled(),
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

        $brandingTextKeys = [
            'site_name',
            'site_tagline',
            'footer_text',
            'home_heading',
            'home_lead',
            'home_seo_html',
        ];

        $allowedKeys = array_values(array_unique(array_merge(
            SiteConfig::managedKeys(),
            $brandingTextKeys,
            [
                'active_theme',
                'site_background_header_offset',
                'site_background_color',
                'site_background_hide_mobile',
                'site_logo_url',
                'site_background_url',
                'site_favicon_url',
                KinoPoiskConfig::SETTING_KEY,
                AllohaConfig::SETTING_KEY,
                TmdbConfig::SETTING_KEY,
                AdminPath::SETTING_KEY,
                CommentModeration::SETTING_KEY,
                ReviewModeration::SETTING_KEY,
                RobotsTxt::SETTING_KEY,
            ],
        )));

        $oldTheme = \App\Models\SiteSetting::get('active_theme');
        $previousAdminPath = AdminPath::path();
        $themeChanged = false;
        $configChanged = false;
        $adminPathChanged = false;

        foreach ($data['settings'] as $row) {
            $key = $row['key'];
            $value = $row['value'] ?? null;

            if (!in_array($key, $allowedKeys, true)) {
                continue;
            }

            if ($key === KinoPoiskConfig::SETTING_KEY
                || $key === AllohaConfig::SETTING_KEY
                || $key === TmdbConfig::SETTING_KEY
            ) {
                $value = trim((string) ($value ?? ''));
                if ($value === '') {
                    continue;
                }
                EncryptedSiteSecret::set($key, $value);
                continue;
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

            if ($key === CommentModeration::SETTING_KEY || $key === ReviewModeration::SETTING_KEY) {
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

            if (in_array($key, $brandingTextKeys, true)) {
                $value = is_string($value) ? $value : (string) ($value ?? '');
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

        $savedKeys = array_values(array_unique(array_map(
            static fn ($row) => (string) ($row['key'] ?? ''),
            $data['settings'],
        )));

        AdminAudit::log(
            'settings.save',
            'settings',
            null,
            'Сохранены настройки (' . count($savedKeys) . ' ключей)',
            ['keys' => $savedKeys],
            $request,
        );

        return response()->json([
            'ok' => true,
            'active_theme' => ThemeManager::activeName(),
            'admin_path' => AdminPath::path(),
            'admin_base' => AdminPath::base(),
        ]);
    });

    Route::post('/sync/kp', function (Request $request, KinoPoiskBulkSync $sync) {
        $data = $request->validate([
            'keyword' => ['nullable', 'string'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:250'],
            'sleep' => ['nullable', 'numeric', 'min:0', 'max:30'],
            'download_poster' => ['nullable', 'boolean'],
            'batch_size' => ['nullable', 'integer', 'min:1', 'max:50'],
            'restart' => ['nullable', 'boolean'],
            'continue' => ['nullable', 'boolean'],
        ]);

        $restart = (bool) ($data['restart'] ?? false);
        $continue = (bool) ($data['continue'] ?? false);

        $progress = $sync->runProgressiveBatch(
            $restart || !$continue,
            (string) ($data['keyword'] ?? ''),
            (int) ($data['limit'] ?? 20),
            (float) ($data['sleep'] ?? 0),
            (bool) ($data['download_poster'] ?? false),
            (int) ($data['batch_size'] ?? 5),
        );

        if (($progress['status'] ?? '') === 'failed') {
            return response()->json([
                'ok' => false,
                'error' => (string) ($progress['message'] ?? 'Не удалось выполнить импорт KinoPoisk'),
                'progress' => $progress,
                'percent' => KinoPoiskBulkProgress::percent($progress),
                'done' => false,
            ], 422);
        }

        $status = (string) ($progress['status'] ?? '');

        return response()->json([
            'ok' => true,
            'progress' => $progress,
            'percent' => KinoPoiskBulkProgress::percent($progress),
            'done' => $status === 'done',
            'paused' => $status === 'paused',
            'stopped' => $status === 'stopped',
            'message' => (string) ($progress['message'] ?? ''),
            'synced' => (int) ($progress['synced'] ?? 0),
            'skipped' => (int) ($progress['skipped'] ?? 0),
            'failed' => (int) ($progress['failed'] ?? 0),
            'output' => (string) ($progress['message'] ?? ''),
        ]);
    });

    Route::get('/sync/kp/progress', function () {
        $progress = KinoPoiskBulkProgress::get();
        $status = (string) ($progress['status'] ?? '');

        return response()->json([
            'ok' => true,
            'progress' => $progress,
            'percent' => KinoPoiskBulkProgress::percent($progress),
            'done' => $status === 'done',
            'paused' => $status === 'paused',
            'stopped' => $status === 'stopped',
        ]);
    });

    Route::post('/sync/kp/pause', function (KinoPoiskBulkSync $sync) {
        $progress = $sync->pause();

        return response()->json([
            'ok' => true,
            'progress' => $progress,
            'percent' => KinoPoiskBulkProgress::percent($progress),
            'paused' => ($progress['status'] ?? '') === 'paused',
        ]);
    });

    Route::post('/sync/kp/resume', function (KinoPoiskBulkSync $sync) {
        $progress = $sync->resume();

        return response()->json([
            'ok' => true,
            'progress' => $progress,
            'percent' => KinoPoiskBulkProgress::percent($progress),
            'paused' => ($progress['status'] ?? '') === 'paused',
        ]);
    });

    Route::post('/sync/kp/stop', function (KinoPoiskBulkSync $sync) {
        $progress = $sync->stop();

        return response()->json([
            'ok' => true,
            'progress' => $progress,
            'percent' => KinoPoiskBulkProgress::percent($progress),
            'stopped' => ($progress['status'] ?? '') === 'stopped',
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
            'update_voices' => ['nullable', 'boolean'],
            'update_metadata' => ['nullable', 'boolean'],
            'update_poster' => ['nullable', 'boolean'],
            'update_genres_countries' => ['nullable', 'boolean'],
            'fill_empty_only' => ['nullable', 'boolean'],
            'bump_date_on_update' => ['nullable', 'boolean'],
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

    Route::get('/backup/settings', function () {
        $settings = BackupSettings::get();
        $lastRun = BackupSettings::lastRunAt();

        // Intentionally no archive listing here — remote FTP/S3 listing can hang the page.
        return response()->json([
            'settings' => $settings,
            'interval_options' => BackupSettings::intervalOptions(),
            'last_run_at' => $lastRun,
            'last_run_human' => $lastRun ? date('d.m.Y H:i:s', $lastRun) : null,
            'is_due' => BackupSettings::isDue(),
            'remote_password_set' => BackupSettings::hasPassword(),
            's3_secret_set' => BackupSettings::hasS3Secret(),
            'remote_configured' => BackupSettings::isRemoteConfigured(),
            'backup_running' => CronRunLogger::isJobRunning(CronRunLogger::JOB_BACKUP),
            'restore_running' => CronRunLogger::isJobRunning(CronRunLogger::JOB_BACKUP_RESTORE),
        ]);
    });

    Route::get('/backup/archives', function (BackupService $backup) {
        return response()->json([
            'local_backups' => $backup->listLocalBackups(),
            'backups' => $backup->listAvailableBackups(),
            'remote_configured' => BackupSettings::isRemoteConfigured(),
            'backup_running' => CronRunLogger::isJobRunning(CronRunLogger::JOB_BACKUP),
            'restore_running' => CronRunLogger::isJobRunning(CronRunLogger::JOB_BACKUP_RESTORE),
        ]);
    });

    Route::get('/backup/job', function (Request $request) {
        $type = trim((string)$request->query('type', 'run'));
        $jobKey = $type === 'restore' ? CronRunLogger::JOB_BACKUP_RESTORE : CronRunLogger::JOB_BACKUP;
        $run = CronRunLogger::latestJob($jobKey);
        $progress = null;
        if ($run && is_array($run->meta) && isset($run->meta['progress']) && is_array($run->meta['progress'])) {
            $progress = $run->meta['progress'];
        }

        return response()->json([
            'type' => $type === 'restore' ? 'restore' : 'run',
            'running' => $run !== null && $run->status === CronRun::STATUS_RUNNING,
            'progress' => $progress,
            'run' => $run ? [
                'id' => $run->id,
                'status' => $run->status,
                'message' => $run->message,
                'error' => $run->error,
                'log' => $run->log,
                'counts' => $run->counts,
                'progress' => $progress,
                'started_at' => optional($run->started_at)?->toIso8601String(),
                'finished_at' => optional($run->finished_at)?->toIso8601String(),
                'duration_ms' => $run->duration_ms,
            ] : null,
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
    })->middleware('throttle:admin-destructive');

    Route::post('/backup/test-connection', function (BackupRemoteStorage $remote) {
        try {
            $remote->testConnection();

            return response()->json(['ok' => true, 'message' => 'Подключение успешно']);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => (string)Utf8::sanitize($e->getMessage())], 422);
        }
    })->middleware('throttle:admin-destructive');

    Route::post('/backup/run', function (Request $request) {
        try {
            if (CronRunLogger::isJobRunning(CronRunLogger::JOB_BACKUP)) {
                $run = CronRunLogger::latestJob(CronRunLogger::JOB_BACKUP);

                return response()->json([
                    'ok' => true,
                    'started' => false,
                    'already_running' => true,
                    'message' => 'Бэкап уже выполняется',
                    'cron_run_id' => $run?->id,
                ]);
            }

            // Detached process — HTTP must not wait for 500MB+ archives.
            ArtisanDetached::spawn([
                'backup:run',
                '--force',
                '--trigger=admin',
            ]);

            AdminAudit::log('backup.run', 'backup', null, 'Запущен ручной бэкап', null, $request);

            return response()->json([
                'ok' => true,
                'started' => true,
                'message' => 'Бэкап запущен в фоне. Можно закрыть страницу — статус появится в истории задач.',
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['ok' => false, 'error' => (string)Utf8::sanitize($e->getMessage())], 500);
        }
    })->middleware('throttle:admin-destructive');

    Route::post('/backup/restore', function (Request $request) {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'source' => ['required', 'string', Rule::in(['local', 'remote'])],
            'restore_database' => ['nullable', 'boolean'],
            'restore_files' => ['nullable', 'boolean'],
            'confirm_token' => ['required', 'string', 'max:500'],
        ]);

        if (!AdminAccess::matchesMasterToken($data['confirm_token'])) {
            return response()->json([
                'ok' => false,
                'error' => 'Для восстановления подтвердите ADMIN_TOKEN',
            ], 403);
        }

        if (!preg_match('/^backup_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.zip$/', $data['name'])) {
            return response()->json(['ok' => false, 'error' => 'Некорректное имя архива'], 422);
        }

        $restoreDatabase = (bool)($data['restore_database'] ?? true);
        $restoreFiles = (bool)($data['restore_files'] ?? true);
        if (!$restoreDatabase && !$restoreFiles) {
            return response()->json(['ok' => false, 'error' => 'Выберите, что восстанавливать'], 422);
        }

        try {
            if (CronRunLogger::isJobRunning(CronRunLogger::JOB_BACKUP_RESTORE)) {
                $run = CronRunLogger::latestJob(CronRunLogger::JOB_BACKUP_RESTORE);

                return response()->json([
                    'ok' => true,
                    'started' => false,
                    'already_running' => true,
                    'message' => 'Восстановление уже выполняется',
                    'cron_run_id' => $run?->id,
                ]);
            }

            $args = [
                'backup:restore',
                $data['name'],
                '--source=' . $data['source'],
                '--trigger=admin',
            ];
            if ($restoreDatabase && !$restoreFiles) {
                $args[] = '--database';
            } elseif ($restoreFiles && !$restoreDatabase) {
                $args[] = '--files';
            }

            ArtisanDetached::spawn($args);

            AdminAudit::log(
                'backup.restore',
                'backup',
                $data['name'],
                'Запущено восстановление из «' . $data['name'] . '»',
                [
                    'source' => $data['source'],
                    'restore_database' => $restoreDatabase,
                    'restore_files' => $restoreFiles,
                ],
                $request,
            );

            return response()->json([
                'ok' => true,
                'started' => true,
                'message' => 'Восстановление запущено в фоне. Не обновляйте сайт до завершения.',
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['ok' => false, 'error' => (string)Utf8::sanitize($e->getMessage())], 500);
        }
    })->middleware('throttle:admin-destructive');
});

