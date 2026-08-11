<?php

use App\Http\Controllers\AdminPanelController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\HomeSectionController;
use App\Http\Controllers\HomeScheduleController;
use App\Http\Controllers\ComingSoonController;
use App\Http\Controllers\CollectionsController;
use App\Http\Controllers\StudiosController;
use App\Http\Controllers\LegacyRedirectController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\SeriesAnticipationController;
use App\Http\Controllers\SeriesController;
use App\Http\Controllers\SeriesEngagementController;
use App\Http\Controllers\SeriesGalleryController;
use App\Http\Controllers\SeriesPreviewController;
use App\Http\Controllers\SeriesReactionController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\TaxonomyController;
use App\Http\Controllers\ThemeAssetController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\UserLibraryController;
use App\Http\Controllers\WatchlistController;
use App\Support\AdminPath;
use App\Support\ReservedPaths;
use Illuminate\Support\Facades\Route;

$adminPath = AdminPath::path();

Route::get(AdminPanelController::ASSET_ROUTE . '/{file}', [AdminPanelController::class, 'asset'])
    ->where('file', '.+')
    ->name('admin.asset.static');

Route::get($adminPath . '/assets/{file}', [AdminPanelController::class, 'asset'])
    ->where('file', '.+')
    ->name('admin.asset');

Route::get($adminPath . '/{spaPath?}', [AdminPanelController::class, 'serve'])
    ->where('spaPath', '.*')
    ->name('admin.spa');

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/page/{page}/', [HomeController::class, 'index'])->where('page', '[0-9]+');

Route::get('/favourites/', [UserLibraryController::class, 'showFavourites'])
    ->middleware('site.feature:favourites_enabled')
    ->name('favourites.show');

Route::get('/api/home/sections/{type}/{id}/series', [HomeSectionController::class, 'series'])
    ->where('id', '[0-9]+')
    ->name('api.home.sections.series');

Route::get('/api/home/blocks/{id}/series', [HomeSectionController::class, 'blockSeries'])
    ->where('id', '[0-9]+')
    ->name('api.home.blocks.series');

Route::get('/api/home/content-types/{contentType}/series', [HomeSectionController::class, 'contentTypeSeries'])
    ->where('contentType', '[a-z_]+')
    ->name('api.home.content-types.series');

Route::get('/api/home/episode-calendar', [HomeScheduleController::class, 'calendar'])
    ->name('api.home.episode-calendar');

Route::get('/collections/', [CollectionsController::class, 'index'])->name('collections.index');
Route::get('/collections/{slug}/', [CollectionsController::class, 'show'])->name('collections.show');
Route::get('/collections/{slug}/page/{page}/', [CollectionsController::class, 'show'])
    ->where('page', '[0-9]+')
    ->name('collections.show.page');

Route::get('/studios/', [StudiosController::class, 'index'])->name('studios.index');
Route::get('/studios/{slug}/', [StudiosController::class, 'show'])->name('studios.show');
Route::get('/studios/{slug}/page/{page}/', [StudiosController::class, 'show'])
    ->where('page', '[0-9]+')
    ->name('studios.show.page');

Route::get('/skoro/', [ComingSoonController::class, 'index'])->name('coming_soon.index');
Route::get('/skoro/page/{page}/', [ComingSoonController::class, 'index'])
    ->where('page', '[0-9]+')
    ->name('coming_soon.index.page');
Route::get('/api/coming-soon/browse', [ComingSoonController::class, 'browse'])->name('api.coming_soon.browse');

Route::get('/catalog/', [CatalogController::class, 'index'])->name('catalog.index');
Route::get('/catalog/page/{page}/', [CatalogController::class, 'index'])
    ->where('page', '[0-9]+')
    ->name('catalog.index.page');

Route::get('/api/search/suggest', [SearchController::class, 'suggest'])->name('search.suggest');

Route::get('/api/csrf', function () {
    return response()->json(['token' => csrf_token()]);
})->name('api.csrf');

Route::get('/search', [SearchController::class, 'search'])->name('search');
Route::get('/search/page/{page}/', [SearchController::class, 'search'])
    ->where('page', '[0-9]+')
    ->name('search.page');

Route::post('/password/email', [PasswordResetController::class, 'sendLink'])
    ->middleware(['guest', 'throttle:6,1', 'site.feature:auth_password_reset_enabled'])
    ->name('password.email');
Route::post('/password/reset', [PasswordResetController::class, 'reset'])
    ->middleware(['guest', 'throttle:6,1', 'site.feature:auth_password_reset_enabled'])
    ->name('password.update');

Route::post('/login', [AuthController::class, 'login'])
    ->middleware(['guest', 'throttle:10,1', 'site.feature:auth_login_enabled'])
    ->name('login');
Route::post('/register', [AuthController::class, 'register'])
    ->middleware(['guest', 'throttle:5,1', 'site.feature:auth_register_enabled'])
    ->name('register');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'site.feature:auth_profile_enabled'])->group(function () {
    Route::get('/profile/', [ProfileController::class, 'show'])->name('profile.show');
    Route::post('/profile/update', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::post('/profile/lists', [ProfileController::class, 'storeList'])->name('profile.lists.store');
    Route::post('/profile/lists/{id}', [ProfileController::class, 'updateList'])->name('profile.lists.update');
    Route::post('/profile/lists/{id}/delete', [ProfileController::class, 'destroyList'])->name('profile.lists.destroy');
    Route::post('/profile/lists/{id}/remove-item', [ProfileController::class, 'removeListItem'])->name('profile.lists.remove-item');
});

Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
Route::get('/robots.txt', [RobotsController::class, 'index'])->name('robots');

Route::get('/theme-assets/{theme}/{path}', [ThemeAssetController::class, 'show'])
    ->where('theme', '[a-zA-Z0-9_-]+')
    ->where('path', '.+')
    ->name('theme.asset');

Route::prefix('api/series/{seriesId}')->where(['seriesId' => '[0-9]+'])->group(function () {
    Route::get('/preview', [SeriesPreviewController::class, 'show']);
    Route::get('/gallery', [SeriesGalleryController::class, 'show']);
    Route::get('/engagement', [SeriesEngagementController::class, 'engagement']);
    Route::get('/comments', [SeriesEngagementController::class, 'comments'])->middleware('site.feature:comments_enabled');
    Route::post('/comments', [SeriesEngagementController::class, 'storeComment'])->middleware('site.feature:comments_enabled');
    Route::post('/vote', [SeriesEngagementController::class, 'vote'])->middleware('site.feature:series_vote_enabled');
    Route::get('/anticipation', [SeriesAnticipationController::class, 'show']);
    Route::post('/anticipation', [SeriesAnticipationController::class, 'vote']);
    Route::get('/reactions', [SeriesReactionController::class, 'show']);
    Route::post('/reactions', [SeriesReactionController::class, 'vote']);
    Route::post('/player-report', [SeriesEngagementController::class, 'storePlayerReport']);
    Route::post('/watchlist', [SeriesEngagementController::class, 'watchlist'])
        ->middleware(['auth', 'site.feature:watchlists_enabled']);
    Route::post('/favourite', [UserLibraryController::class, 'toggleFavourite'])
        ->middleware('site.feature:favourites_enabled');
    Route::post('/watch-history', [UserLibraryController::class, 'recordWatchHistory'])
        ->middleware('site.feature:watch_history_enabled');
    Route::get('/notifications', [SeriesEngagementController::class, 'notificationSettings'])
        ->middleware(['auth', 'site.feature:notifications_enabled']);
    Route::post('/notifications', [SeriesEngagementController::class, 'saveNotifications'])
        ->middleware(['auth', 'site.feature:notifications_enabled']);
    Route::delete('/notifications', [SeriesEngagementController::class, 'deleteNotifications'])
        ->middleware(['auth', 'site.feature:notifications_enabled']);
});

Route::middleware(['auth', 'site.feature:notifications_enabled'])->prefix('api/notifications')->group(function () {
    Route::get('/', [NotificationController::class, 'index']);
    Route::post('/read', [NotificationController::class, 'markRead']);
    Route::post('/clear', [NotificationController::class, 'clearAll']);
    Route::delete('/{id}', [NotificationController::class, 'dismiss'])->where('id', '[0-9]+');
    Route::get('/preferences', [NotificationController::class, 'preferences']);
    Route::post('/preferences', [NotificationController::class, 'savePreferences']);
    Route::delete('/series/{seriesId}', [NotificationController::class, 'unsubscribeSeries'])
        ->where('seriesId', '[0-9]+');
});

Route::middleware('site.feature:notifications_enabled')->prefix('api/push')->group(function () {
    Route::get('/vapid-public-key', [PushSubscriptionController::class, 'vapidPublicKey']);
    Route::post('/subscribe', [PushSubscriptionController::class, 'store'])->middleware('auth');
    Route::post('/unsubscribe', [PushSubscriptionController::class, 'destroy'])->middleware('auth');
});

Route::post('/api/comments/{id}/vote', [SeriesEngagementController::class, 'voteComment'])
    ->where('id', '[0-9]+')
    ->middleware('site.feature:comments_vote_enabled');

Route::middleware('auth')->prefix('api/watchlists')->group(function () {
    Route::get('/', [WatchlistController::class, 'index']);
    Route::post('/', [WatchlistController::class, 'store']);
    Route::put('/{id}', [WatchlistController::class, 'update']);
    Route::delete('/{id}', [WatchlistController::class, 'destroy']);
    Route::delete('/{id}/items', [WatchlistController::class, 'removeItem']);
});

Route::get('/api/catalog/browse', [CatalogController::class, 'browse'])->name('api.catalog.browse');

Route::get('/api/favourites', [UserLibraryController::class, 'favourites'])
    ->middleware('site.feature:favourites_enabled');
Route::get('/api/watch-history', [UserLibraryController::class, 'watchHistory'])
    ->middleware('site.feature:watch_history_enabled');
Route::post('/api/user-library/merge-guest', [UserLibraryController::class, 'mergeGuest'])
    ->middleware('auth');

Route::get('/api/taxonomy/genre/{slug}/browse', [TaxonomyController::class, 'browseGenre'])
    ->where('slug', '[a-z0-9\-]+');
Route::get('/api/taxonomy/country/{slug}/browse', [TaxonomyController::class, 'browseCountry'])
    ->where('slug', '[a-z0-9\-]+');
Route::get('/api/taxonomy/person/{slug}/browse', [TaxonomyController::class, 'browsePerson'])
    ->where('slug', '[a-z0-9\-]+');
Route::get('/api/taxonomy/year/{slug}/browse', [TaxonomyController::class, 'browseYear'])
    ->where('slug', '[0-9]+');

Route::get('/genre/{slug}/', [TaxonomyController::class, 'showGenre'])
    ->where('slug', '[a-z0-9\-]+')
    ->name('taxonomy.genre.show');
Route::get('/genre/{slug}/page/{page}/', [TaxonomyController::class, 'showGenre'])
    ->where('slug', '[a-z0-9\-]+')
    ->where('page', '[0-9]+')
    ->name('taxonomy.genre.show.page');

Route::get('/country/{slug}/', [TaxonomyController::class, 'showCountry'])
    ->where('slug', '[a-z0-9\-]+')
    ->name('taxonomy.country.show');
Route::get('/country/{slug}/page/{page}/', [TaxonomyController::class, 'showCountry'])
    ->where('slug', '[a-z0-9\-]+')
    ->where('page', '[0-9]+')
    ->name('taxonomy.country.show.page');

Route::get('/person/{slug}/', [TaxonomyController::class, 'showPerson'])
    ->where('slug', '[a-z0-9\-]+')
    ->name('taxonomy.person.show');
Route::get('/person/{slug}/page/{page}/', [TaxonomyController::class, 'showPerson'])
    ->where('slug', '[a-z0-9\-]+')
    ->where('page', '[0-9]+')
    ->name('taxonomy.person.show.page');

Route::get('/year/{slug}/', [TaxonomyController::class, 'showYear'])
    ->where('slug', '[0-9]+')
    ->name('taxonomy.year.show');
Route::get('/year/{slug}/page/{page}/', [TaxonomyController::class, 'showYear'])
    ->where('slug', '[0-9]+')
    ->where('page', '[0-9]+')
    ->name('taxonomy.year.show.page');

$legacyCategory = ReservedPaths::legacyCategoryConstraint();

Route::get('/{category}/{slug}.html', [LegacyRedirectController::class, 'series'])
    ->where('category', $legacyCategory)
    ->where('slug', '[a-z0-9\-]+');

Route::get('/{category}/', [LegacyRedirectController::class, 'category'])
    ->where('category', $legacyCategory);

Route::get('/{category}/page/{page}/', [LegacyRedirectController::class, 'categoryPage'])
    ->where('category', $legacyCategory)
    ->where('page', '[0-9]+');

Route::get('/{seriesPath}.html', [SeriesController::class, 'show'])
    ->where('seriesPath', ReservedPaths::seriesPathConstraint())
    ->name('series.show');
