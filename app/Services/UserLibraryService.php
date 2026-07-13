<?php

namespace App\Services;

use App\Models\GuestFavourite;
use App\Models\Series;
use App\Models\User;
use App\Models\WatchHistory;
use App\Models\Watchlist;
use App\Models\WatchlistItem;
use App\Support\SiteConfig;
use App\Support\WatchlistDefaults;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class UserLibraryService
{
    public static function guestKey(Request $request): string
    {
        return hash('sha256', $request->session()->getId());
    }

    public static function isFavourite(int $seriesId, ?User $user, Request $request): bool
    {
        if ($user) {
            return self::favouriteWatchlistId($user) !== null
                && WatchlistItem::query()
                    ->where('watchlist_id', self::favouriteWatchlistId($user))
                    ->where('series_id', $seriesId)
                    ->exists();
        }

        return GuestFavourite::query()
            ->where('series_id', $seriesId)
            ->where('guest_key', self::guestKey($request))
            ->exists();
    }

    /**
     * @return array{is_favourite: bool}
     */
    public static function toggleFavourite(int $seriesId, ?User $user, Request $request): array
    {
        if ($user) {
            WatchlistDefaults::ensureForUser($user);
            $listId = self::favouriteWatchlistId($user);
            if (!$listId) {
                return ['is_favourite' => false];
            }

            $existing = WatchlistItem::query()
                ->where('watchlist_id', $listId)
                ->where('series_id', $seriesId)
                ->first();

            if ($existing) {
                $existing->delete();
                return ['is_favourite' => false];
            }

            WatchlistItem::query()->create([
                'watchlist_id' => $listId,
                'series_id' => $seriesId,
            ]);

            return ['is_favourite' => true];
        }

        if (!SiteConfig::bool('favourites_guest_enabled')) {
            return ['is_favourite' => false];
        }

        $key = self::guestKey($request);
        $existing = GuestFavourite::query()
            ->where('series_id', $seriesId)
            ->where('guest_key', $key)
            ->first();

        if ($existing) {
            $existing->delete();
            return ['is_favourite' => false];
        }

        GuestFavourite::query()->create([
            'series_id' => $seriesId,
            'guest_key' => $key,
        ]);

        return ['is_favourite' => true];
    }

    public static function recordWatchHistory(int $seriesId, ?User $user, Request $request): void
    {
        if (!SiteConfig::bool('watch_history_enabled')) {
            return;
        }

        if ($user) {
            WatchHistory::query()->updateOrCreate(
                ['user_id' => $user->id, 'series_id' => $seriesId],
                ['guest_key' => null, 'viewed_at' => now()]
            );
            self::trimHistoryForUser($user->id);

            return;
        }

        if (!SiteConfig::bool('watch_history_guest_enabled')) {
            return;
        }

        $key = self::guestKey($request);
        WatchHistory::query()->updateOrCreate(
            ['guest_key' => $key, 'series_id' => $seriesId],
            ['user_id' => null, 'viewed_at' => now()]
        );
        self::trimHistoryForGuest($key);
    }

    /**
     * @return Collection<int, Series>
     */
    public static function favouriteSeries(?User $user, Request $request, ?int $limit = null): Collection
    {
        $limit = $limit ?? SiteConfig::int('favourites_list_limit');

        if ($user) {
            $listId = self::favouriteWatchlistId($user);
            if (!$listId) {
                return collect();
            }

            $ids = WatchlistItem::query()
                ->where('watchlist_id', $listId)
                ->orderByDesc('id')
                ->limit($limit)
                ->pluck('series_id')
                ->all();

            return self::seriesByIds($ids);
        }

        $ids = GuestFavourite::query()
            ->where('guest_key', self::guestKey($request))
            ->orderByDesc('id')
            ->limit($limit)
            ->pluck('series_id')
            ->all();

        return self::seriesByIds($ids);
    }

    /**
     * @return Collection<int, Series>
     */
    public static function watchHistorySeries(?User $user, Request $request, ?int $limit = null): Collection
    {
        $limit = $limit ?? SiteConfig::int('watch_history_home_limit');

        if ($user) {
            $ids = WatchHistory::query()
                ->where('user_id', $user->id)
                ->orderByDesc('viewed_at')
                ->limit($limit)
                ->pluck('series_id')
                ->all();

            return self::seriesByIds($ids);
        }

        $ids = WatchHistory::query()
            ->where('guest_key', self::guestKey($request))
            ->orderByDesc('viewed_at')
            ->limit($limit)
            ->pluck('series_id')
            ->all();

        return self::seriesByIds($ids);
    }

    public static function mergeGuestToUser(User $user, string $guestKey): void
    {
        WatchlistDefaults::ensureForUser($user);
        $listId = self::favouriteWatchlistId($user);

        if ($listId) {
            $guestFavIds = GuestFavourite::query()
                ->where('guest_key', $guestKey)
                ->pluck('series_id');

            foreach ($guestFavIds as $seriesId) {
                WatchlistItem::query()->firstOrCreate([
                    'watchlist_id' => $listId,
                    'series_id' => $seriesId,
                ]);
            }

            GuestFavourite::query()->where('guest_key', $guestKey)->delete();
        }

        $guestRows = WatchHistory::query()
            ->where('guest_key', $guestKey)
            ->get();

        foreach ($guestRows as $row) {
            $existing = WatchHistory::query()
                ->where('user_id', $user->id)
                ->where('series_id', $row->series_id)
                ->first();

            if ($existing) {
                if ($row->viewed_at && (!$existing->viewed_at || $row->viewed_at->gt($existing->viewed_at))) {
                    $existing->update(['viewed_at' => $row->viewed_at]);
                }
                $row->delete();
                continue;
            }

            $row->update([
                'user_id' => $user->id,
                'guest_key' => null,
            ]);
        }

        self::trimHistoryForUser($user->id);
    }

    /**
     * @param array<int> $extraFavouriteIds
     * @param array<int> $extraHistoryIds
     */
    public static function mergeClientIds(User $user, array $extraFavouriteIds = [], array $extraHistoryIds = []): void
    {
        WatchlistDefaults::ensureForUser($user);
        $listId = self::favouriteWatchlistId($user);

        if ($listId) {
            foreach (array_unique(array_filter(array_map('intval', $extraFavouriteIds))) as $seriesId) {
                if (!Series::query()->published()->where('id', $seriesId)->exists()) {
                    continue;
                }
                WatchlistItem::query()->firstOrCreate([
                    'watchlist_id' => $listId,
                    'series_id' => $seriesId,
                ]);
            }
        }

        foreach (array_unique(array_filter(array_map('intval', $extraHistoryIds))) as $seriesId) {
            if (!Series::query()->published()->where('id', $seriesId)->exists()) {
                continue;
            }
            WatchHistory::query()->updateOrCreate(
                ['user_id' => $user->id, 'series_id' => $seriesId],
                ['guest_key' => null, 'viewed_at' => now()]
            );
        }

        self::trimHistoryForUser($user->id);
    }

    /**
     * @param array<int> $ids
     * @return Collection<int, Series>
     */
    private static function seriesByIds(array $ids): Collection
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids === []) {
            return collect();
        }

        $series = Series::query()
            ->published()
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        $ordered = collect();
        foreach ($ids as $id) {
            if ($series->has($id)) {
                $ordered->push($series->get($id));
            }
        }

        return $ordered;
    }

    private static function favouriteWatchlistId(User $user): ?int
    {
        return Watchlist::query()
            ->where('user_id', $user->id)
            ->where('system_key', 'favourite')
            ->value('id');
    }

    private static function trimHistoryForUser(int $userId): void
    {
        $max = SiteConfig::int('watch_history_max_items');
        $keepIds = WatchHistory::query()
            ->where('user_id', $userId)
            ->orderByDesc('viewed_at')
            ->limit($max)
            ->pluck('id');

        WatchHistory::query()
            ->where('user_id', $userId)
            ->whereNotIn('id', $keepIds)
            ->delete();
    }

    private static function trimHistoryForGuest(string $guestKey): void
    {
        $max = SiteConfig::int('watch_history_max_items');
        $keepIds = WatchHistory::query()
            ->where('guest_key', $guestKey)
            ->orderByDesc('viewed_at')
            ->limit($max)
            ->pluck('id');

        WatchHistory::query()
            ->where('guest_key', $guestKey)
            ->whereNotIn('id', $keepIds)
            ->delete();
    }
}
