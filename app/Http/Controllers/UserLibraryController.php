<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesSeries;
use App\Services\SeriesCardMapper;
use App\Services\UserLibraryService;
use App\Support\PluralRu;
use App\Support\SiteConfig;
use App\Support\Speedbar;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserLibraryController extends TplController
{
    use ResolvesSeries;

    public function showFavourites(Request $request)
    {
        if (!SiteConfig::bool('favourites_enabled')) {
            abort(404);
        }

        $user = Auth::user();
        if (!$user && !SiteConfig::bool('favourites_guest_enabled')) {
            abort(404);
        }

        $series = UserLibraryService::favouriteSeries($user, $request);
        $mapped = SeriesCardMapper::mapSeries($series);
        $total = count($mapped);

        $vars = [
            'page' => [
                'heading' => SiteConfig::str('favourites_ui_page_title'),
            ],
            'series_list' => $mapped,
            'favourites_total' => $total,
            'favourites_total_word' => PluralRu::series($total),
            'favourites_has_items' => $total > 0,
            'favourites_empty_text' => SiteConfig::str('favourites_ui_empty'),
            'favourites_cards_html' => $this->renderPartial('partials/series_cards.tpl', ['series_list' => $mapped]),
        ];

        $this->applySpeedbar(Speedbar::forFavourites(), $vars);

        $meta = [
            'title' => SiteConfig::str('favourites_meta_title'),
            'description' => SiteConfig::str('favourites_meta_description'),
            'canonical' => url('/favourites/'),
            'robots' => 'noindex, follow',
        ];

        return $this->renderTplPage('favourites/index.tpl', $vars, $meta);
    }

    public function favourites(Request $request)
    {
        if (!SiteConfig::bool('favourites_enabled')) {
            return response()->json(['items' => [], 'html' => '', 'count' => 0]);
        }

        $user = Auth::user();
        if (!$user && !SiteConfig::bool('favourites_guest_enabled')) {
            return response()->json(['items' => [], 'html' => '', 'count' => 0]);
        }

        $request->validate([
            'guest_key' => ['nullable', 'string', 'max:64'],
            'ids' => ['nullable'],
            'ids.*' => ['integer'],
            'sync' => ['nullable', 'boolean'],
        ]);

        if (!$user) {
            $guestKey = UserLibraryService::guestKey($request);
            $clientIds = UserLibraryService::clientSeriesIds($request);
            if ($clientIds !== [] && $request->boolean('sync', true)) {
                UserLibraryService::syncGuestFavouriteIds($guestKey, $clientIds);
            }
        }

        $series = UserLibraryService::favouriteSeries($user, $request);
        $mapped = SeriesCardMapper::mapSeries($series);

        return response()->json([
            'items' => $mapped,
            'html' => $this->renderPartial('partials/series_cards.tpl', ['series_list' => $mapped]),
            'count' => count($mapped),
            'total_word' => PluralRu::series(count($mapped)),
        ]);
    }

    public function toggleFavourite(Request $request, int $seriesId)
    {
        if (!SiteConfig::bool('favourites_enabled')) {
            return response()->json(['ok' => false, 'message' => SiteConfig::str('favourites_msg_disabled')], 403);
        }

        $user = Auth::user();
        if (!$user && !SiteConfig::bool('favourites_guest_enabled')) {
            return response()->json(['ok' => false, 'message' => SiteConfig::str('auth_msg_auth_required')], 401);
        }

        $data = $request->validate([
            'active' => ['nullable', 'boolean'],
            'guest_key' => ['nullable', 'string', 'max:64'],
        ]);

        $this->resolveActiveSeries($seriesId);
        $active = array_key_exists('active', $data) ? (bool) $data['active'] : null;
        $result = UserLibraryService::toggleFavourite($seriesId, $user, $request, $active);

        return response()->json([
            'ok' => true,
            'is_favourite' => $result['is_favourite'],
            'count' => UserLibraryService::favouritesCount($user, $request),
        ]);
    }

    public function watchHistory(Request $request)
    {
        if (!SiteConfig::bool('watch_history_enabled')) {
            return response()->json(['items' => [], 'html' => '', 'count' => 0]);
        }

        $user = Auth::user();
        if (!$user && !SiteConfig::bool('watch_history_guest_enabled')) {
            return response()->json(['items' => [], 'html' => '', 'count' => 0]);
        }

        $series = UserLibraryService::watchHistorySeries($user, $request);
        $mapped = SeriesCardMapper::mapSeries($series);

        return response()->json([
            'items' => $mapped,
            'html' => $this->renderPartial('partials/series_cards.tpl', ['series_list' => $mapped]),
            'count' => count($mapped),
        ]);
    }

    public function recordWatchHistory(Request $request, int $seriesId)
    {
        if (!SiteConfig::bool('watch_history_enabled')) {
            return response()->json(['ok' => false], 403);
        }

        $user = Auth::user();
        if (!$user && !SiteConfig::bool('watch_history_guest_enabled')) {
            return response()->json(['ok' => false], 401);
        }

        $this->resolveActiveSeries($seriesId);
        UserLibraryService::recordWatchHistory($seriesId, $user, $request);

        return response()->json(['ok' => true]);
    }

    public function mergeGuest(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['ok' => false, 'message' => SiteConfig::str('auth_msg_auth_required')], 401);
        }

        $data = $request->validate([
            'favourites' => ['nullable', 'array'],
            'favourites.*' => ['integer'],
            'history' => ['nullable', 'array'],
            'history.*' => ['integer'],
        ]);

        $guestKey = UserLibraryService::guestKey($request);
        UserLibraryService::mergeGuestToUser($user, $guestKey);
        UserLibraryService::mergeClientIds(
            $user,
            $data['favourites'] ?? [],
            $data['history'] ?? []
        );

        return response()->json(['ok' => true]);
    }
}
