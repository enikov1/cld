<?php

namespace App\Services;

use App\Models\Series;
use App\Models\SeriesViewDaily;
use App\Support\SiteConfig;

class SeriesPopularityService
{
    /**
     * @return array<int, int> series_id => views in window
     */
    public static function viewsInWindowForPublished(int $days): array
    {
        if ($days < 1) {
            return [];
        }

        $fromDate = now()->subDays($days - 1)->toDateString();

        return SeriesViewDaily::query()
            ->join('series', 'series.id', '=', 'series_view_daily.series_id')
            ->where('series.is_active', true)
            ->where('series.is_hidden', false)
            ->whereNull('series.deleted_at')
            ->where('series_view_daily.view_date', '>=', $fromDate)
            ->groupBy('series_view_daily.series_id')
            ->selectRaw('series_view_daily.series_id as series_id, SUM(series_view_daily.views_count) as total')
            ->pluck('total', 'series_id')
            ->map(fn ($v) => (int)$v)
            ->all();
    }

    /**
     * @param array<int, int> $viewsBySeriesId
     * @return array<int, true>
     */
    public static function popularSeriesIdsFromViews(array $viewsBySeriesId, int $minViews, int $percentile): array
    {
        $positive = array_values(array_filter($viewsBySeriesId, static fn (int $v) => $v > 0));
        if ($positive === []) {
            return [];
        }

        $threshold = self::percentileThreshold($positive, $percentile);
        $out = [];

        foreach ($viewsBySeriesId as $seriesId => $views) {
            if ($views >= $minViews && $views >= $threshold) {
                $out[(int)$seriesId] = true;
            }
        }

        return $out;
    }

    /**
     * @param array<int, int> $values
     */
    public static function percentileThreshold(array $values, int $percentile): int
    {
        if ($values === []) {
            return PHP_INT_MAX;
        }

        $percentile = max(1, min(100, $percentile));
        sort($values, SORT_NUMERIC);
        $index = (int)ceil(count($values) * (1 - $percentile / 100)) - 1;
        $index = max(0, min(count($values) - 1, $index));

        return (int)$values[$index];
    }

    public static function refreshPopularBadges(): int
    {
        $days = SiteConfig::int('card_badge_popular_days');
        $minViews = SiteConfig::int('card_badge_popular_min_views');
        $percentile = SiteConfig::int('card_badge_popular_percentile');

        $viewsBySeries = self::viewsInWindowForPublished($days);
        $popularIds = array_keys(self::popularSeriesIdsFromViews($viewsBySeries, $minViews, $percentile));
        $now = now();
        $updated = 0;

        $deactivateQuery = Series::query()->where('popular_badge_active', true);
        if ($popularIds !== []) {
            $deactivateQuery->whereNotIn('id', $popularIds);
        }
        $updated += $deactivateQuery->update([
            'popular_badge_active' => false,
            'popular_badge_refreshed_at' => $now,
        ]);

        if ($popularIds !== []) {
            $updated += Series::query()
                ->whereIn('id', $popularIds)
                ->where('popular_badge_active', false)
                ->update([
                    'popular_badge_active' => true,
                    'popular_badge_refreshed_at' => $now,
                ]);

            Series::query()
                ->whereIn('id', $popularIds)
                ->where('popular_badge_active', true)
                ->update(['popular_badge_refreshed_at' => $now]);
        }

        return $updated;
    }
}
