<?php

namespace App\Support;

use App\Models\SearchQuery;
use App\Models\SearchQueryLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class SearchQueryStatService
{
    private const MAX_QUERY_LENGTH = 120;

    public static function tryRecord(Request $request, string $query, string $source, bool $found, int $resultsCount = 0): void
    {
        if (!self::isReady()) {
            return;
        }

        $display = self::normalizeDisplay($query);
        if ($display === '') {
            return;
        }

        $minChars = SiteConfig::int('search_suggest_min_chars');
        if (mb_strlen($display) < $minChars) {
            return;
        }

        $normalized = mb_strtolower($display);
        $source = $source === 'suggest' ? 'suggest' : 'full';
        $sessionKey = 'search_stat_recorded:' . $source . ':' . $normalized;
        if ($request->session()->has($sessionKey)) {
            return;
        }

        self::logEvent($request, $display, $normalized, $source, $found, $resultsCount);

        if ($found) {
            self::recordSuccessful($display, $source);
        }

        $request->session()->put($sessionKey, true);
    }

    public static function logEvent(
        Request $request,
        string $display,
        string $normalized,
        string $source,
        bool $found,
        int $resultsCount = 0
    ): void {
        if (!self::logsReady()) {
            return;
        }

        try {
            SearchQueryLog::query()->create([
                'query' => $display,
                'query_normalized' => $normalized,
                'source' => $source === 'suggest' ? 'suggest' : 'full',
                'found' => $found,
                'results_count' => max(0, $resultsCount),
                'ip' => self::clientIp($request),
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            // Ignore logging failures — search must keep working.
        }
    }

    public static function recordSuccessful(string $query, string $source = 'full'): void
    {
        if (!self::isReady()) {
            return;
        }

        $display = self::normalizeDisplay($query);
        if ($display === '') {
            return;
        }

        $minChars = SiteConfig::int('search_suggest_min_chars');
        if (mb_strlen($display) < $minChars) {
            return;
        }

        $normalized = mb_strtolower($display);
        $source = $source === 'suggest' ? 'suggest' : 'full';

        try {
            $row = SearchQuery::query()->where('query_normalized', $normalized)->first();
            if ($row) {
                $row->increment('hits');
                if ($source === 'suggest') {
                    $row->increment('suggest_hits');
                } else {
                    $row->increment('full_hits');
                }
                $row->update([
                    'query' => $display,
                    'last_searched_at' => now(),
                ]);

                return;
            }

            SearchQuery::query()->create([
                'query_normalized' => $normalized,
                'query' => $display,
                'hits' => 1,
                'suggest_hits' => $source === 'suggest' ? 1 : 0,
                'full_hits' => $source === 'full' ? 1 : 0,
                'last_searched_at' => now(),
            ]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            $row = SearchQuery::query()->where('query_normalized', $normalized)->first();
            if ($row) {
                $row->increment('hits');
                if ($source === 'suggest') {
                    $row->increment('suggest_hits');
                } else {
                    $row->increment('full_hits');
                }
            }
        }
    }

    /**
     * @param array{
     *     q?: string,
     *     date_from?: string|null,
     *     date_to?: string|null,
     *     found?: string|null,
     *     source?: string|null,
     *     ip?: string|null
     * } $filters
     */
    public static function filteredLogQuery(array $filters): Builder
    {
        $query = SearchQueryLog::query();

        self::applyLogFilters($query, $filters);

        return $query;
    }

    /**
     * @param array{
     *     q?: string,
     *     date_from?: string|null,
     *     date_to?: string|null,
     *     found?: string|null,
     *     source?: string|null,
     *     ip?: string|null
     * } $filters
     * @return array{
     *     total_events: int,
     *     found_events: int,
     *     not_found_events: int,
     *     unique_queries: int,
     *     suggest_events: int,
     *     full_events: int,
     *     events_today: int,
     *     events_week: int
     * }
     */
    public static function summary(array $filters = []): array
    {
        $aggregated = self::aggregatedSummary();
        $logs = self::logsSummary($filters);

        return array_merge($aggregated, $logs);
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<array{query: string, count: int, found_count: int, not_found_count: int, share: float}>
     */
    public static function topQueries(array $filters, int $limit = 5): array
    {
        if (!self::logsReady()) {
            return [];
        }

        $limit = max(1, min(20, $limit));
        $base = self::filteredLogQuery($filters);
        $total = (int)(clone $base)->count();
        if ($total === 0) {
            return [];
        }

        $rows = (clone $base)
            ->selectRaw('query, COUNT(*) as total_count, SUM(CASE WHEN found = 1 THEN 1 ELSE 0 END) as found_count')
            ->groupBy('query')
            ->orderByDesc('total_count')
            ->limit($limit)
            ->get();

        return $rows->map(static function ($row) use ($total) {
            $count = (int)$row->total_count;
            $foundCount = (int)$row->found_count;

            return [
                'query' => (string)$row->query,
                'count' => $count,
                'found_count' => $foundCount,
                'not_found_count' => $count - $foundCount,
                'share' => round(($count / $total) * 100, 1),
            ];
        })->all();
    }

    /**
     * @return list<array{query: string, url: string, hits: int}>
     */
    public static function popular(?int $limit = null, ?string $excludeQuery = null): array
    {
        if (!self::isReady()) {
            return [];
        }

        $limit = max(1, min(50, $limit ?? SiteConfig::int('search_popular_limit')));

        $query = SearchQuery::query()
            ->where('hits', '>', 0)
            ->orderByDesc('hits')
            ->orderByDesc('last_searched_at')
            ->limit($limit);

        $exclude = self::normalizeDisplay($excludeQuery ?? '');
        if ($exclude !== '') {
            $query->where('query_normalized', '!=', mb_strtolower($exclude));
        }

        return $query
            ->get()
            ->map(static fn (SearchQuery $item) => [
                'query' => $item->query,
                'url' => '/search?' . http_build_query(['q' => $item->query], '', '&', PHP_QUERY_RFC3986),
                'hits' => $item->hits,
            ])
            ->all();
    }

    /**
     * @param array<string, mixed> $filters
     */
    private static function applyLogFilters(Builder $query, array $filters): void
    {
        $search = trim((string)($filters['q'] ?? ''));
        if ($search !== '') {
            $like = '%' . $search . '%';
            $query->where(function (Builder $builder) use ($like) {
                $builder->where('query', 'like', $like)
                    ->orWhere('query_normalized', 'like', mb_strtolower($like));
            });
        }

        $dateFrom = trim((string)($filters['date_from'] ?? ''));
        if ($dateFrom !== '') {
            $query->where('created_at', '>=', $dateFrom . ' 00:00:00');
        }

        $dateTo = trim((string)($filters['date_to'] ?? ''));
        if ($dateTo !== '') {
            $query->where('created_at', '<=', $dateTo . ' 23:59:59');
        }

        $found = $filters['found'] ?? null;
        if ($found === '1' || $found === 'yes' || $found === true) {
            $query->where('found', true);
        } elseif ($found === '0' || $found === 'no' || $found === false) {
            $query->where('found', false);
        }

        $source = trim((string)($filters['source'] ?? ''));
        if ($source === 'suggest' || $source === 'full') {
            $query->where('source', $source);
        }

        $ip = trim((string)($filters['ip'] ?? ''));
        if ($ip !== '') {
            $query->where('ip', 'like', '%' . $ip . '%');
        }
    }

    /**
     * @return array{
     *     unique_queries: int,
     *     total_hits: int,
     *     suggest_hits: int,
     *     full_hits: int,
     *     hits_today: int,
     *     hits_week: int
     * }
     */
    private static function aggregatedSummary(): array
    {
        if (!self::isReady()) {
            return [
                'unique_queries' => 0,
                'total_hits' => 0,
                'suggest_hits' => 0,
                'full_hits' => 0,
                'hits_today' => 0,
                'hits_week' => 0,
            ];
        }

        $base = SearchQuery::query();

        return [
            'unique_queries' => (int)(clone $base)->count(),
            'total_hits' => (int)(clone $base)->sum('hits'),
            'suggest_hits' => (int)(clone $base)->sum('suggest_hits'),
            'full_hits' => (int)(clone $base)->sum('full_hits'),
            'hits_today' => (int)(clone $base)->where('last_searched_at', '>=', now()->startOfDay())->sum('hits'),
            'hits_week' => (int)(clone $base)->where('last_searched_at', '>=', now()->subDays(7))->sum('hits'),
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{
     *     total_events: int,
     *     found_events: int,
     *     not_found_events: int,
     *     log_unique_queries: int,
     *     suggest_events: int,
     *     full_events: int,
     *     events_today: int,
     *     events_week: int
     * }
     */
    private static function logsSummary(array $filters): array
    {
        if (!self::logsReady()) {
            return [
                'total_events' => 0,
                'found_events' => 0,
                'not_found_events' => 0,
                'log_unique_queries' => 0,
                'suggest_events' => 0,
                'full_events' => 0,
                'events_today' => 0,
                'events_week' => 0,
            ];
        }

        $base = self::filteredLogQuery($filters);
        $todayBase = self::filteredLogQuery(array_merge($filters, [
            'date_from' => now()->toDateString(),
            'date_to' => now()->toDateString(),
        ]));
        $weekFilters = $filters;
        if (!isset($weekFilters['date_from']) || $weekFilters['date_from'] === '') {
            $weekFilters['date_from'] = now()->subDays(7)->toDateString();
        }

        $weekBase = self::filteredLogQuery($weekFilters);

        return [
            'total_events' => (int)(clone $base)->count(),
            'found_events' => (int)(clone $base)->where('found', true)->count(),
            'not_found_events' => (int)(clone $base)->where('found', false)->count(),
            'log_unique_queries' => (int)(clone $base)->distinct('query_normalized')->count('query_normalized'),
            'suggest_events' => (int)(clone $base)->where('source', 'suggest')->count(),
            'full_events' => (int)(clone $base)->where('source', 'full')->count(),
            'events_today' => (int)(clone $todayBase)->count(),
            'events_week' => (int)(clone $weekBase)->count(),
        ];
    }

    private static function normalizeDisplay(string $query): string
    {
        $query = trim(preg_replace('/\s+/u', ' ', $query) ?? '');
        if ($query === '') {
            return '';
        }

        if (mb_strlen($query) > self::MAX_QUERY_LENGTH) {
            $query = mb_substr($query, 0, self::MAX_QUERY_LENGTH);
        }

        return $query;
    }

    private static function clientIp(Request $request): ?string
    {
        $ip = trim((string)$request->ip());
        if ($ip === '') {
            return null;
        }

        return mb_substr($ip, 0, 45);
    }

    private static function isReady(): bool
    {
        return Schema::hasTable('search_queries');
    }

    private static function logsReady(): bool
    {
        return Schema::hasTable('search_query_logs');
    }
}
