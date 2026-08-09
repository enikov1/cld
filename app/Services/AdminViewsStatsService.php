<?php

namespace App\Services;

use App\Models\Series;
use App\Models\SeriesViewDaily;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class AdminViewsStatsService
{
    public const CACHE_TTL_SECONDS = 90;

    private const PERIODS = ['today', 'yesterday', '7d', '30d', '90d', '365d', 'all', 'custom'];

    private const GROUPS = ['day', 'week', 'month'];

    /**
     * @return array{
     *   ready: bool,
     *   period: string,
     *   group: string,
     *   date_from: string|null,
     *   date_to: string|null,
     *   cache_ttl: int,
     *   summary: array<string, int|float|null>,
     *   timeseries: list<array{bucket: string, label: string, views: int}>,
     *   top_series: list<array<string, mixed>>
     * }
     */
    public static function report(
        string $period = '30d',
        string $group = 'day',
        ?string $dateFrom = null,
        ?string $dateTo = null,
        int $topLimit = 20,
    ): array {
        $period = in_array($period, self::PERIODS, true) ? $period : '30d';
        $group = in_array($group, self::GROUPS, true) ? $group : 'day';
        $topLimit = max(5, min(50, $topLimit));

        if ($period !== 'custom') {
            $dateFrom = null;
            $dateTo = null;
        }

        $cacheKey = 'admin_views_stats:' . md5(json_encode([
            $period,
            $group,
            $dateFrom,
            $dateTo,
            $topLimit,
            now()->toDateString(),
        ], JSON_THROW_ON_ERROR));

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($period, $group, $dateFrom, $dateTo, $topLimit) {
            return self::build($period, $group, $dateFrom, $dateTo, $topLimit);
        });
    }

    /**
     * Lightweight dashboard snapshot (always short-cached).
     *
     * @return array{ready: bool, views_today: int, views_7d: int, views_30d: int, views_total: int, series_active_today: int}
     */
    public static function dashboardSnapshot(): array
    {
        $cacheKey = 'admin_views_stats:dashboard:' . now()->toDateString();

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () {
            if (!Schema::hasTable('series_view_daily')) {
                return [
                    'ready' => false,
                    'views_today' => 0,
                    'views_7d' => 0,
                    'views_30d' => 0,
                    'views_total' => 0,
                    'series_active_today' => 0,
                ];
            }

            $today = now()->toDateString();
            $from7 = now()->subDays(6)->toDateString();
            $from30 = now()->subDays(29)->toDateString();

            return [
                'ready' => true,
                'views_today' => self::sumViewsBetween($today, $today),
                'views_7d' => self::sumViewsBetween($from7, $today),
                'views_30d' => self::sumViewsBetween($from30, $today),
                'views_total' => (int) Series::query()->sum('views_count'),
                'series_active_today' => self::distinctSeriesBetween($today, $today),
            ];
        });
    }

    /**
     * @return array{
     *   ready: bool,
     *   period: string,
     *   group: string,
     *   date_from: string|null,
     *   date_to: string|null,
     *   cache_ttl: int,
     *   summary: array<string, int|float|null>,
     *   timeseries: list<array{bucket: string, label: string, views: int}>,
     *   top_series: list<array<string, mixed>>
     * }
     */
    private static function build(
        string $period,
        string $group,
        ?string $dateFrom,
        ?string $dateTo,
        int $topLimit,
    ): array {
        if (!Schema::hasTable('series_view_daily')) {
            return [
                'ready' => false,
                'period' => $period,
                'group' => $group,
                'date_from' => null,
                'date_to' => null,
                'cache_ttl' => self::CACHE_TTL_SECONDS,
                'summary' => self::emptySummary(),
                'timeseries' => [],
                'top_series' => [],
            ];
        }

        [$from, $to] = self::resolveRange($period, $dateFrom, $dateTo);
        $group = self::autoGroup($group, $from, $to);

        $dailyMap = self::dailyViewsMap($from, $to);
        $prevRange = self::previousRange($from, $to);
        $prevViews = $prevRange
            ? self::sumViewsBetween($prevRange[0]->toDateString(), $prevRange[1]->toDateString())
            : 0;

        $periodViews = array_sum($dailyMap);
        $days = max(1, $from->diffInDays($to) + 1);
        $changePct = null;
        if ($prevViews > 0) {
            $changePct = round((($periodViews - $prevViews) / $prevViews) * 100, 1);
        } elseif ($periodViews > 0) {
            $changePct = 100.0;
        }

        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();

        $summary = [
            'views_today' => self::sumViewsBetween($today, $today),
            'views_yesterday' => self::sumViewsBetween($yesterday, $yesterday),
            'views_period' => $periodViews,
            'views_prev_period' => $prevViews,
            'views_change_pct' => $changePct,
            'views_total' => (int) Series::query()->sum('views_count'),
            'series_active_period' => self::distinctSeriesBetween($from->toDateString(), $to->toDateString()),
            'series_active_today' => self::distinctSeriesBetween($today, $today),
            'avg_per_day' => (int) round($periodViews / $days),
            'days' => $days,
        ];

        return [
            'ready' => true,
            'period' => $period,
            'group' => $group,
            'date_from' => $from->toDateString(),
            'date_to' => $to->toDateString(),
            'cache_ttl' => self::CACHE_TTL_SECONDS,
            'summary' => $summary,
            'timeseries' => self::buildTimeseries($dailyMap, $from, $to, $group),
            'top_series' => self::topSeries($from->toDateString(), $to->toDateString(), $periodViews, $topLimit),
        ];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private static function resolveRange(string $period, ?string $dateFrom, ?string $dateTo): array
    {
        $today = now()->startOfDay();

        return match ($period) {
            'today' => [$today->copy(), $today->copy()],
            'yesterday' => [$today->copy()->subDay(), $today->copy()->subDay()],
            '7d' => [$today->copy()->subDays(6), $today->copy()],
            '90d' => [$today->copy()->subDays(89), $today->copy()],
            '365d' => [$today->copy()->subDays(364), $today->copy()],
            'all' => [self::earliestDate(), $today->copy()],
            'custom' => self::resolveCustomRange($dateFrom, $dateTo, $today),
            default => [$today->copy()->subDays(29), $today->copy()],
        };
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private static function resolveCustomRange(?string $dateFrom, ?string $dateTo, Carbon $today): array
    {
        $from = $dateFrom ? Carbon::parse($dateFrom)->startOfDay() : $today->copy()->subDays(29);
        $to = $dateTo ? Carbon::parse($dateTo)->startOfDay() : $today->copy();

        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        if ($to->gt($today)) {
            $to = $today->copy();
        }

        if ($from->diffInDays($to) > 730) {
            $from = $to->copy()->subDays(730);
        }

        return [$from, $to];
    }

    private static function earliestDate(): Carbon
    {
        $min = SeriesViewDaily::query()->min('view_date');
        if ($min) {
            return Carbon::parse($min)->startOfDay();
        }

        return now()->startOfDay()->subDays(29);
    }

    private static function autoGroup(string $group, Carbon $from, Carbon $to): string
    {
        $days = $from->diffInDays($to) + 1;
        if ($group === 'day' && $days > 120) {
            return 'week';
        }
        if ($group === 'week' && $days > 400) {
            return 'month';
        }

        return $group;
    }

    /**
     * @return array{0: Carbon, 1: Carbon}|null
     */
    private static function previousRange(Carbon $from, Carbon $to): ?array
    {
        $days = $from->diffInDays($to) + 1;
        if ($days < 1) {
            return null;
        }

        $prevTo = $from->copy()->subDay();
        $prevFrom = $prevTo->copy()->subDays($days - 1);

        return [$prevFrom, $prevTo];
    }

    /**
     * @return array<string, int> Y-m-d => views
     */
    private static function dailyViewsMap(Carbon $from, Carbon $to): array
    {
        $rows = SeriesViewDaily::query()
            ->selectRaw('view_date, SUM(views_count) as total')
            ->whereDate('view_date', '>=', $from->toDateString())
            ->whereDate('view_date', '<=', $to->toDateString())
            ->groupBy('view_date')
            ->orderBy('view_date')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $date = Carbon::parse($row->view_date)->toDateString();
            $map[$date] = (int) $row->total;
        }

        return $map;
    }

    private static function sumViewsBetween(string $from, string $to): int
    {
        return (int) SeriesViewDaily::query()
            ->whereDate('view_date', '>=', $from)
            ->whereDate('view_date', '<=', $to)
            ->sum('views_count');
    }

    private static function distinctSeriesBetween(string $from, string $to): int
    {
        return (int) SeriesViewDaily::query()
            ->whereDate('view_date', '>=', $from)
            ->whereDate('view_date', '<=', $to)
            ->where('views_count', '>', 0)
            ->distinct()
            ->count('series_id');
    }

    /**
     * @param array<string, int> $dailyMap
     * @return list<array{bucket: string, label: string, views: int}>
     */
    private static function buildTimeseries(array $dailyMap, Carbon $from, Carbon $to, string $group): array
    {
        if ($group === 'day') {
            $points = [];
            $cursor = $from->copy();
            while ($cursor->lte($to)) {
                $key = $cursor->toDateString();
                $points[] = [
                    'bucket' => $key,
                    'label' => $cursor->format('d.m'),
                    'views' => $dailyMap[$key] ?? 0,
                ];
                $cursor->addDay();
            }

            return $points;
        }

        $buckets = [];
        $cursor = $from->copy();
        while ($cursor->lte($to)) {
            $key = $cursor->toDateString();
            $views = $dailyMap[$key] ?? 0;

            if ($group === 'week') {
                $bucketKey = $cursor->copy()->startOfWeek(Carbon::MONDAY)->toDateString();
                $labelEnd = $cursor->copy()->endOfWeek(Carbon::SUNDAY);
                if ($labelEnd->gt($to)) {
                    $labelEnd = $to->copy();
                }
                $labelStart = Carbon::parse($bucketKey);
                if ($labelStart->lt($from)) {
                    $labelStart = $from->copy();
                }
                $label = $labelStart->format('d.m') . '–' . $labelEnd->format('d.m');
            } else {
                $bucketKey = $cursor->format('Y-m');
                $label = $cursor->format('m.Y');
            }

            if (!isset($buckets[$bucketKey])) {
                $buckets[$bucketKey] = [
                    'bucket' => $bucketKey,
                    'label' => $label,
                    'views' => 0,
                ];
            }
            $buckets[$bucketKey]['views'] += $views;
            $cursor->addDay();
        }

        return array_values($buckets);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function topSeries(string $from, string $to, int $periodViews, int $limit): array
    {
        $rows = SeriesViewDaily::query()
            ->selectRaw('series_id, SUM(views_count) as period_views')
            ->whereDate('view_date', '>=', $from)
            ->whereDate('view_date', '<=', $to)
            ->groupBy('series_id')
            ->orderByDesc('period_views')
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $ids = $rows->pluck('series_id')->map(fn ($id) => (int) $id)->all();
        $seriesMap = Series::query()
            ->whereIn('id', $ids)
            ->get(['id', 'title', 'slug', 'year', 'start_year', 'poster_url', 'views_count', 'is_active'])
            ->keyBy('id');

        $result = [];
        foreach ($rows as $row) {
            $series = $seriesMap->get((int) $row->series_id);
            if (!$series) {
                continue;
            }
            $views = (int) $row->period_views;
            $result[] = [
                'id' => (int) $series->id,
                'title' => (string) $series->title,
                'slug' => (string) $series->slug,
                'year' => $series->year ?: $series->start_year,
                'poster_url' => $series->poster_url,
                'is_active' => (bool) $series->is_active,
                'views' => $views,
                'views_total' => (int) $series->views_count,
                'share' => $periodViews > 0 ? round(($views / $periodViews) * 100, 1) : 0.0,
            ];
        }

        return $result;
    }

    /**
     * @return array<string, int|float|null>
     */
    private static function emptySummary(): array
    {
        return [
            'views_today' => 0,
            'views_yesterday' => 0,
            'views_period' => 0,
            'views_prev_period' => 0,
            'views_change_pct' => null,
            'views_total' => 0,
            'series_active_period' => 0,
            'series_active_today' => 0,
            'avg_per_day' => 0,
            'days' => 0,
        ];
    }
}
