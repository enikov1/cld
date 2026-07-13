<?php

namespace App\Services;

use App\Models\Series;
use App\Support\PaginationHelper;
use App\Support\TaxonomyRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection as SupportCollection;

class HomeSectionService
{
    public const SORT_LATEST = 'latest';
    public const SORT_POPULAR = 'popular';
    public const SORT_POPULAR_ASC = 'popular_asc';
    public const SORT_RATING = 'rating';

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
