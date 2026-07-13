<?php

namespace App\Support;

use App\Models\SearchQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class SearchQueryStatService
{
    private const MAX_QUERY_LENGTH = 120;

    public static function tryRecord(Request $request, string $query, string $source): void
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

        if (!QuickSearchService::hasResults($display)) {
            return;
        }

        $normalized = mb_strtolower($display);
        $sessionKey = 'search_stat_recorded:' . $normalized;
        if ($request->session()->has($sessionKey)) {
            return;
        }

        self::recordSuccessful($display, $source);
        $request->session()->put($sessionKey, true);
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
     * @return array{
     *     unique_queries: int,
     *     total_hits: int,
     *     suggest_hits: int,
     *     full_hits: int,
     *     hits_today: int,
     *     hits_week: int
     * }
     */
    public static function summary(): array
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

    private static function isReady(): bool
    {
        return Schema::hasTable('search_queries');
    }
}
