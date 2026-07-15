<?php

namespace App\Services;

use App\Models\HomeSection;
use App\Models\Series;
use App\Support\AdminSeriesFilter;
use App\Support\PaginationHelper;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

class HomeBlockService
{
    /**
     * @return SupportCollection<int, HomeSection>
     */
    public static function activeBlocks(): SupportCollection
    {
        return HomeSection::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param array<string, mixed>|null $filters
     * @return array<string, mixed>
     */
    public static function normalizeFilters(?array $filters): array
    {
        $filters = is_array($filters) ? $filters : [];

        if (($filters['year_mode'] ?? '') === 'current_year') {
            $year = (int) date('Y');
            $filters['year_from'] = $year;
            $filters['year_to'] = $year;
        }

        unset($filters['year_mode']);

        $clean = [];
        foreach ($filters as $key => $value) {
            if ($value === null || $value === '' || $value === []) {
                continue;
            }
            $clean[$key] = $value;
        }

        return $clean;
    }

    /**
     * @return Collection<int, Series>
     */
    public static function seriesForBlock(HomeSection $block, string $sort): Collection
    {
        $limit = max(1, min(60, (int) ($block->item_limit ?? 18)));

        return self::seriesQuery(self::normalizeFilters($block->filters), $sort)
            ->limit($limit)
            ->get();
    }

    /**
     * @param array<string, mixed>|null $filters
     */
    public static function seriesCount(?array $filters): int
    {
        return self::seriesQuery(self::normalizeFilters($filters), HomeSectionService::SORT_LATEST)
            ->count();
    }

    /**
     * @param array<string, mixed> $filters
     * @return \Illuminate\Database\Eloquent\Builder<Series>
     */
    private static function seriesQuery(array $filters, string $sort): \Illuminate\Database\Eloquent\Builder
    {
        $query = Series::query()->published();
        AdminSeriesFilter::apply($query, $filters);
        HomeSectionService::applySort($query, $sort);

        return $query;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function mapBlocksForHome(SupportCollection $blocks, callable $renderCards): array
    {
        $out = [];

        foreach ($blocks as $block) {
            $sort = HomeSectionService::normalizeSort($block->default_sort ?? HomeSectionService::SORT_LATEST);
            $items = self::seriesForBlock($block, $sort);
            if ($items->isEmpty()) {
                continue;
            }

            $mapped = PaginationHelper::mapSeries($items);

            $out[] = [
                'id' => $block->id,
                'block_id' => $block->id,
                'title' => $block->title,
                'link_url' => trim((string) ($block->link_url ?? '')),
                'show_tabs' => (bool) ($block->show_tabs ?? true),
                'default_sort' => $sort,
                'tab_latest_active' => $sort === HomeSectionService::SORT_LATEST,
                'tab_popular_active' => $sort === HomeSectionService::SORT_POPULAR,
                'tab_rating_active' => $sort === HomeSectionService::SORT_RATING,
                'cards_html' => $renderCards($mapped),
                'cards_count' => count($mapped),
            ];
        }

        return $out;
    }
}
