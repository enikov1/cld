<?php

namespace App\Services;

use App\Models\ReactionType;
use App\Models\Series;
use App\Models\SeriesReactionVote;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdminReactionStatsService
{
    public const CACHE_TTL_SECONDS = 120;

    private const CACHE_VERSION_KEY = 'admin_reaction_stats:ver';

    private const PERIODS = ['today', 'yesterday', '7d', '30d', '90d', '365d', 'all', 'custom'];

    private const GROUPS = ['day', 'week', 'month'];

    public static function bumpCache(): void
    {
        if (Cache::has(self::CACHE_VERSION_KEY)) {
            Cache::increment(self::CACHE_VERSION_KEY);

            return;
        }

        Cache::forever(self::CACHE_VERSION_KEY, 2);
    }

    /**
     * @return array<string, mixed>
     */
    public static function report(
        string $period = '30d',
        string $group = 'day',
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?int $reactionTypeId = null,
        int $topLimit = 25,
        bool $fresh = false,
    ): array {
        $period = in_array($period, self::PERIODS, true) ? $period : '30d';
        $group = in_array($group, self::GROUPS, true) ? $group : 'day';
        $topLimit = max(5, min(50, $topLimit));
        $reactionTypeId = $reactionTypeId && $reactionTypeId > 0 ? $reactionTypeId : null;

        if ($period !== 'custom') {
            $dateFrom = null;
            $dateTo = null;
        }

        $cacheKey = 'admin_reaction_stats:' . md5(json_encode([
            self::cacheVersion(),
            $period,
            $group,
            $dateFrom,
            $dateTo,
            $reactionTypeId,
            $topLimit,
            now()->toDateString(),
        ], JSON_THROW_ON_ERROR));

        if ($fresh) {
            $report = self::build($period, $group, $dateFrom, $dateTo, $reactionTypeId, $topLimit);
            Cache::put($cacheKey, $report, self::CACHE_TTL_SECONDS);

            return $report;
        }

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($period, $group, $dateFrom, $dateTo, $reactionTypeId, $topLimit) {
            return self::build($period, $group, $dateFrom, $dateTo, $reactionTypeId, $topLimit);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private static function build(
        string $period,
        string $group,
        ?string $dateFrom,
        ?string $dateTo,
        ?int $reactionTypeId,
        int $topLimit,
    ): array {
        if (!Schema::hasTable('series_reaction_votes') || !Schema::hasTable('reaction_types')) {
            return self::emptyReport($period, $group, false);
        }

        [$from, $to] = self::resolveRange($period, $dateFrom, $dateTo);
        $group = self::autoGroup($group, $from, $to);
        $types = self::reactionTypes();

        $periodAgg = self::aggregateVotes($from, $to, $reactionTypeId);
        $typePeriodVotes = $reactionTypeId
            ? self::aggregateVotes($from, $to, null)['votes']
            : $periodAgg['votes'];
        $prevRange = self::previousRange($from, $to);
        $prevVotes = $prevRange
            ? self::aggregateVotes($prevRange[0], $prevRange[1], $reactionTypeId)['votes']
            : 0;

        $today = now()->startOfDay();
        $yesterday = $today->copy()->subDay();
        $votesToday = self::aggregateVotes($today, $today->copy(), $reactionTypeId)['votes'];
        $votesYesterday = self::aggregateVotes($yesterday, $yesterday->copy(), $reactionTypeId)['votes'];
        $votesTotal = self::aggregateVotes(null, null, $reactionTypeId)['votes'];

        $periodVotes = $periodAgg['votes'];
        $days = max(1, $from->diffInDays($to) + 1);
        $changePct = null;
        if ($prevVotes > 0) {
            $changePct = round((($periodVotes - $prevVotes) / $prevVotes) * 100, 1);
        } elseif ($periodVotes > 0) {
            $changePct = 100.0;
        }

        $dailyMap = self::dailyVotesMap($from, $to, $reactionTypeId);
        $byType = self::byType($types, $from, $to, $typePeriodVotes, $reactionTypeId);
        $topSeries = self::topSeries($types, $from, $to, $periodVotes, $reactionTypeId, $topLimit);

        return [
            'ready' => true,
            'period' => $period,
            'group' => $group,
            'date_from' => $from->toDateString(),
            'date_to' => $to->toDateString(),
            'reaction_type_id' => $reactionTypeId,
            'cache_ttl' => self::CACHE_TTL_SECONDS,
            'types' => $types->map(fn (ReactionType $type) => [
                'id' => (int) $type->id,
                'emoji' => (string) $type->emoji,
                'label' => (string) $type->label,
                'is_active' => (bool) $type->is_active,
            ])->values()->all(),
            'summary' => [
                'votes_today' => $votesToday,
                'votes_yesterday' => $votesYesterday,
                'votes_period' => $periodVotes,
                'votes_prev_period' => $prevVotes,
                'votes_change_pct' => $changePct,
                'votes_total' => $votesTotal,
                'series_period' => $periodAgg['series_count'],
                'users_period' => $periodAgg['users'],
                'guests_period' => $periodAgg['guests'],
                'voters_period' => $periodAgg['users'] + $periodAgg['guests'],
                'avg_per_day' => (int) round($periodVotes / $days),
                'days' => $days,
            ],
            'by_type' => $byType,
            'timeseries' => self::buildTimeseries($dailyMap, $from, $to, $group),
            'top_series' => $topSeries,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function emptyReport(string $period, string $group, bool $ready): array
    {
        return [
            'ready' => $ready,
            'period' => $period,
            'group' => $group,
            'date_from' => null,
            'date_to' => null,
            'reaction_type_id' => null,
            'cache_ttl' => self::CACHE_TTL_SECONDS,
            'types' => [],
            'summary' => [
                'votes_today' => 0,
                'votes_yesterday' => 0,
                'votes_period' => 0,
                'votes_prev_period' => 0,
                'votes_change_pct' => null,
                'votes_total' => 0,
                'series_period' => 0,
                'users_period' => 0,
                'guests_period' => 0,
                'voters_period' => 0,
                'avg_per_day' => 0,
                'days' => 0,
            ],
            'by_type' => [],
            'timeseries' => [],
            'top_series' => [],
        ];
    }

    private static function cacheVersion(): int
    {
        $ver = Cache::get(self::CACHE_VERSION_KEY);
        if (!is_numeric($ver)) {
            Cache::forever(self::CACHE_VERSION_KEY, 1);

            return 1;
        }

        return (int) $ver;
    }

    /**
     * @return \Illuminate\Support\Collection<int, ReactionType>
     */
    private static function reactionTypes()
    {
        return ReactionType::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'emoji', 'label', 'is_active', 'sort_order']);
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
        $min = SeriesReactionVote::query()->min('created_at');
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
     * @return array{votes: int, series_count: int, users: int, guests: int}
     */
    private static function aggregateVotes(?Carbon $from, ?Carbon $to, ?int $reactionTypeId): array
    {
        $row = self::voteQuery($from, $to, $reactionTypeId)
            ->selectRaw('COUNT(*) as votes')
            ->selectRaw('COUNT(DISTINCT series_id) as series_count')
            ->selectRaw('COUNT(DISTINCT user_id) as users')
            ->selectRaw('COUNT(DISTINCT voter_key) as guests')
            ->first();

        return [
            'votes' => (int) ($row->votes ?? 0),
            'series_count' => (int) ($row->series_count ?? 0),
            'users' => (int) ($row->users ?? 0),
            'guests' => (int) ($row->guests ?? 0),
        ];
    }

    /**
     * @return array<string, int>
     */
    private static function dailyVotesMap(Carbon $from, Carbon $to, ?int $reactionTypeId): array
    {
        $dayExpr = self::dateExpr('created_at');
        $rows = self::voteQuery($from, $to, $reactionTypeId)
            ->selectRaw("{$dayExpr} as day, COUNT(*) as total")
            ->groupByRaw($dayExpr)
            ->orderBy('day')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            if (!$row->day) {
                continue;
            }
            $map[Carbon::parse($row->day)->toDateString()] = (int) $row->total;
        }

        return $map;
    }

    /**
     * @param \Illuminate\Support\Collection<int, ReactionType> $types
     * @return list<array<string, mixed>>
     */
    private static function byType($types, Carbon $from, Carbon $to, int $periodVotes, ?int $highlightTypeId): array
    {
        $rows = self::voteQuery($from, $to, null)
            ->selectRaw('reaction_type_id, COUNT(*) as votes, COUNT(DISTINCT series_id) as series_count')
            ->groupBy('reaction_type_id')
            ->get()
            ->keyBy('reaction_type_id');

        return $types->map(function (ReactionType $type) use ($rows, $periodVotes, $highlightTypeId) {
            $row = $rows->get($type->id);
            $votes = (int) ($row->votes ?? 0);

            return [
                'id' => (int) $type->id,
                'emoji' => (string) $type->emoji,
                'label' => (string) $type->label,
                'is_active' => (bool) $type->is_active,
                'votes' => $votes,
                'series_count' => (int) ($row->series_count ?? 0),
                'share' => $periodVotes > 0 ? round(($votes / $periodVotes) * 100, 1) : 0.0,
                'highlighted' => $highlightTypeId !== null && $highlightTypeId === (int) $type->id,
            ];
        })->values()->all();
    }

    /**
     * @param \Illuminate\Support\Collection<int, ReactionType> $types
     * @return list<array<string, mixed>>
     */
    private static function topSeries($types, Carbon $from, Carbon $to, int $periodVotes, ?int $reactionTypeId, int $limit): array
    {
        $rows = self::voteQuery($from, $to, $reactionTypeId)
            ->selectRaw('series_id, COUNT(*) as votes')
            ->groupBy('series_id')
            ->orderByDesc('votes')
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $ids = $rows->pluck('series_id')->map(fn ($id) => (int) $id)->all();
        $seriesMap = Series::query()
            ->whereIn('id', $ids)
            ->get(['id', 'title', 'slug', 'year', 'start_year', 'poster_url', 'is_active'])
            ->keyBy('id');

        $breakdownRows = self::voteQuery($from, $to, null)
            ->selectRaw('series_id, reaction_type_id, COUNT(*) as votes')
            ->whereIn('series_id', $ids)
            ->groupBy('series_id', 'reaction_type_id')
            ->get();

        $typeById = $types->keyBy('id');
        $breakdown = [];
        foreach ($breakdownRows as $row) {
            $type = $typeById->get((int) $row->reaction_type_id);
            if (!$type) {
                continue;
            }
            $breakdown[(int) $row->series_id][] = [
                'id' => (int) $type->id,
                'emoji' => (string) $type->emoji,
                'label' => (string) $type->label,
                'votes' => (int) $row->votes,
            ];
        }

        $result = [];
        foreach ($rows as $row) {
            $series = $seriesMap->get((int) $row->series_id);
            if (!$series) {
                continue;
            }
            $votes = (int) $row->votes;
            $mix = $breakdown[(int) $series->id] ?? [];
            usort($mix, fn (array $a, array $b) => $b['votes'] <=> $a['votes'] ?: $a['id'] <=> $b['id']);
            $top = $mix[0] ?? null;

            $result[] = [
                'id' => (int) $series->id,
                'title' => (string) $series->title,
                'slug' => (string) $series->slug,
                'year' => $series->year ?: $series->start_year,
                'poster_url' => $series->poster_url,
                'is_active' => (bool) $series->is_active,
                'votes' => $votes,
                'share' => $periodVotes > 0 ? round(($votes / $periodVotes) * 100, 1) : 0.0,
                'top_emoji' => $top['emoji'] ?? null,
                'reactions' => $mix,
            ];
        }

        return $result;
    }

    /**
     * @param array<string, int> $dailyMap
     * @return list<array{bucket: string, label: string, votes: int}>
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
                    'votes' => $dailyMap[$key] ?? 0,
                ];
                $cursor->addDay();
            }

            return $points;
        }

        $buckets = [];
        $cursor = $from->copy();
        while ($cursor->lte($to)) {
            $key = $cursor->toDateString();
            $votes = $dailyMap[$key] ?? 0;

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
                    'votes' => 0,
                ];
            }
            $buckets[$bucketKey]['votes'] += $votes;
            $cursor->addDay();
        }

        return array_values($buckets);
    }

    private static function voteQuery(?Carbon $from, ?Carbon $to, ?int $reactionTypeId): Builder
    {
        $query = SeriesReactionVote::query();

        if ($from) {
            $query->where('created_at', '>=', $from->copy()->startOfDay());
        }
        if ($to) {
            $query->where('created_at', '<=', $to->copy()->endOfDay());
        }
        if ($reactionTypeId) {
            $query->where('reaction_type_id', $reactionTypeId);
        }

        return $query;
    }

    private static function dateExpr(string $column): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "date({$column})"
            : "DATE({$column})";
    }
}
