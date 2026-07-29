<?php

namespace App\Services;

use App\Models\Series;
use App\Support\CatalogFilterRegistry;
use App\Support\PaginationHelper;
use App\Support\SiteConfig;
use App\Support\TaxonomyRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection as SupportCollection;

class HomeSectionService
{
    public const SORT_LATEST = 'latest';
    public const SORT_POPULAR = 'popular';
    public const SORT_POPULAR_ASC = 'popular_asc';
    public const SORT_RATING = 'rating';
    public const SORT_VIEWS = 'views';
    public const SORT_VIEWS_PERIOD = 'views_period';
    public const SORT_COMMENTS = 'comments';
    public const SORT_USER_RATING = 'user_rating';
    public const SORT_IMDB_RATING = 'imdb_rating';

    /**
     * @return SupportCollection<int, array{type: string, item: Model}>
     */
    public static function activeSections(): SupportCollection
    {
        $sections = collect();

        foreach (TaxonomyRegistry::TYPES as $type => $config) {
            $model = $config['model'];

            $items = $model::query()
                ->where('show_on_home', true)
                ->where('is_active', true)
                ->where('is_hidden', false)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();

            foreach ($items as $item) {
                $sections->push([
                    'type' => $type,
                    'item' => $item,
                ]);
            }
        }

        return $sections->values();
    }

    public static function normalizeSort(?string $sort): string
    {
        return match ($sort) {
            self::SORT_POPULAR => self::SORT_POPULAR,
            self::SORT_POPULAR_ASC => self::SORT_POPULAR_ASC,
            self::SORT_RATING => self::SORT_RATING,
            default => self::SORT_LATEST,
        };
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Series>
     */
    public static function seriesForTaxonomy(string $type, Model $item, string $sort): \Illuminate\Database\Eloquent\Collection
    {
        $limit = max(1, min(60, (int)($item->home_item_limit ?? 18)));

        $query = Series::query()
            ->published();

        TaxonomyRegistry::applySeriesScope($query, $type, $item);
        self::applySort($query, $sort);

        return $query->limit($limit)->get();
    }

    public static function applySort($query, string $sort): void
    {
        match (self::normalizeSort($sort)) {
            self::SORT_POPULAR => $query
                ->orderByDesc('is_pinned')
                ->orderByDesc('pinned_at')
                ->orderByRaw('tmdb_popularity IS NULL')
                ->orderByDesc('tmdb_popularity')
                ->orderByDesc('kp_votes_count')
                ->orderByDesc('imdb_votes_count')
                ->orderByDesc('id'),
            self::SORT_POPULAR_ASC => $query
                ->orderByDesc('is_pinned')
                ->orderByDesc('pinned_at')
                ->orderByRaw('tmdb_popularity IS NULL')
                ->orderBy('tmdb_popularity')
                ->orderBy('id'),
            self::SORT_RATING => $query
                ->orderByRaw('kp_rating IS NULL')
                ->orderByDesc('kp_rating')
                ->orderByDesc('imdb_rating')
                ->orderByDesc('id'),
            default => $query->catalogOrder(),
        };
    }

    public static function normalizeHomePopularSort(?string $sort): string
    {
        return match ($sort) {
            self::SORT_VIEWS,
            self::SORT_VIEWS_PERIOD,
            self::SORT_COMMENTS,
            self::SORT_USER_RATING,
            self::SORT_IMDB_RATING,
            self::SORT_POPULAR,
            self::SORT_RATING,
            self::SORT_LATEST => $sort,
            default => self::SORT_POPULAR,
        };
    }

    /**
     * Sort for the home «Популярное» carousel (admin-configurable).
     */
    public static function applyHomePopularSort($query, ?string $sort = null): void
    {
        $sort = self::normalizeHomePopularSort($sort ?? SiteConfig::str('home_popular_sort'));

        match ($sort) {
            self::SORT_VIEWS => $query
                ->orderByDesc('is_pinned')
                ->orderByDesc('pinned_at')
                ->orderByDesc('views_count')
                ->orderByDesc('id'),
            self::SORT_VIEWS_PERIOD => self::applyViewsPeriodSort($query),
            self::SORT_COMMENTS => $query
                ->orderByDesc('is_pinned')
                ->orderByDesc('pinned_at')
                ->orderByRaw('(' . CatalogFilterRegistry::approvedCommentsCountSql() . ') DESC')
                ->orderByDesc('id'),
            self::SORT_USER_RATING => $query
                ->orderByDesc('is_pinned')
                ->orderByDesc('pinned_at')
                ->orderByRaw('(' . CatalogFilterRegistry::userRatingPercentSql() . ') DESC')
                ->orderByDesc('id'),
            self::SORT_IMDB_RATING => $query
                ->orderByDesc('is_pinned')
                ->orderByDesc('pinned_at')
                ->orderByRaw('imdb_rating IS NULL')
                ->orderByDesc('imdb_rating')
                ->orderByDesc('id'),
            self::SORT_RATING => $query
                ->orderByDesc('is_pinned')
                ->orderByDesc('pinned_at')
                ->orderByRaw('kp_rating IS NULL')
                ->orderByDesc('kp_rating')
                ->orderByDesc('imdb_rating')
                ->orderByDesc('id'),
            self::SORT_LATEST => $query->catalogOrder(),
            default => self::applySort($query, self::SORT_POPULAR),
        };
    }

    private static function applyViewsPeriodSort($query): void
    {
        $days = max(1, SiteConfig::int('home_popular_views_days'));
        $fromDate = now()->subDays($days - 1)->toDateString();

        $query
            ->orderByDesc('is_pinned')
            ->orderByDesc('pinned_at')
            ->orderByRaw(
                '(
                    SELECT COALESCE(SUM(series_view_daily.views_count), 0)
                    FROM series_view_daily
                    WHERE series_view_daily.series_id = series.id
                      AND series_view_daily.view_date >= ?
                ) DESC',
                [$fromDate]
            )
            ->orderByDesc('id');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function mapSectionsForHome(SupportCollection $sections, callable $renderCards): array
    {
        $out = [];

        foreach ($sections as $section) {
            $type = $section['type'];
            $item = $section['item'];
            $sort = self::normalizeSort($item->home_default_sort ?? self::SORT_LATEST);
            $items = self::seriesForTaxonomy($type, $item, $sort);
            if ($items->isEmpty()) {
                continue;
            }

            $mapped = PaginationHelper::mapSeries($items);

            $out[] = [
                'id' => $type . '-' . $item->id,
                'taxonomy_type' => $type,
                'taxonomy_id' => $item->id,
                'slug' => $item->slug,
                'url' => TaxonomyRegistry::publicUrl($type, $item->slug),
                'title' => TaxonomyRegistry::homeTitle($item),
                'show_tabs' => (bool)($item->home_show_tabs ?? true),
                'default_sort' => $sort,
                'tab_latest_active' => $sort === self::SORT_LATEST,
                'tab_popular_active' => $sort === self::SORT_POPULAR,
                'tab_rating_active' => $sort === self::SORT_RATING,
                'cards_html' => $renderCards($mapped),
                'cards_count' => count($mapped),
            ];
        }

        return $out;
    }
}
