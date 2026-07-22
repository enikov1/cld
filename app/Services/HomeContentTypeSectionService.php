<?php

namespace App\Services;

use App\Models\Series;
use App\Support\ContentTypes;
use App\Support\PaginationHelper;
use App\Support\SiteConfig;
use Illuminate\Database\Eloquent\Collection;

class HomeContentTypeSectionService
{
    /**
     * @return Collection<int, Series>
     */
    public static function seriesForContentType(string $contentType, string $sort): Collection
    {
        if (!ContentTypes::isValid($contentType)) {
            return new Collection();
        }

        $limit = max(1, min(60, SiteConfig::int('home_content_types_limit')));
        $sort = HomeSectionService::normalizeSort($sort);

        $query = Series::query()
            ->published()
            ->where('content_type', $contentType);
        HomeSectionService::applySort($query, $sort);

        return $query->limit($limit)->get();
    }

    /**
     * @return array{
     *     sections: list<array<string, mixed>>,
     *     flags: array<string, string>,
     *     by_index: array<int, array<string, mixed>>
     * }
     */
    public static function build(callable $renderCards, ?callable $renderBlock = null): array
    {
        if (!SiteConfig::bool('home_content_types_enabled')) {
            return self::emptyPayload();
        }

        $limit = max(1, min(60, SiteConfig::int('home_content_types_limit')));
        $sort = HomeSectionService::normalizeSort(SiteConfig::str('home_content_types_sort'));

        $sections = [];
        $flags = [];
        $byIndex = [];
        $index = 0;

        foreach (ContentTypes::slugs() as $slug) {
            $index++;
            $typeKey = 'type-' . $index;

            $query = Series::query()
                ->published()
                ->where('content_type', $slug);
            HomeSectionService::applySort($query, $sort);

            $items = $query->limit($limit)->get();
            if ($items->isEmpty()) {
                $flags[$typeKey] = '';
                $byIndex[$index] = self::emptySection($slug, $index, $typeKey);
                continue;
            }

            $mapped = PaginationHelper::mapSeries($items);
            $section = self::mapSection($slug, $index, $typeKey, $sort, $mapped, $renderCards);

            if ($renderBlock !== null) {
                $section['block_html'] = $renderBlock($section);
            }

            $flags[$typeKey] = '1';
            $sections[] = $section;
            $byIndex[$index] = $section;
        }

        return [
            'sections' => $sections,
            'flags' => $flags,
            'by_index' => $byIndex,
        ];
    }

    /**
     * @param list<array<string, mixed>> $mapped
     * @return array<string, mixed>
     */
    private static function mapSection(
        string $slug,
        int $index,
        string $typeKey,
        string $sort,
        array $mapped,
        callable $renderCards,
    ): array {
        return [
            'type_index' => $index,
            'type_key' => $typeKey,
            'content_type' => $slug,
            'content_type_label' => ContentTypes::label($slug),
            'title' => self::sectionTitle($slug),
            'show_tabs' => true,
            'default_sort' => $sort,
            'tab_latest_active' => $sort === HomeSectionService::SORT_LATEST,
            'tab_popular_active' => $sort === HomeSectionService::SORT_POPULAR,
            'tab_rating_active' => $sort === HomeSectionService::SORT_RATING,
            'cards_list' => $mapped,
            'cards_html' => $renderCards($mapped),
            'cards_count' => count($mapped),
        ];
    }

    /**
     * @return array{sections: list<array<string, mixed>>, flags: array<string, string>, by_index: array<int, array<string, mixed>>}
     */
    private static function emptyPayload(): array
    {
        $flags = [];
        $byIndex = [];
        $index = 0;

        foreach (ContentTypes::slugs() as $slug) {
            $index++;
            $typeKey = 'type-' . $index;
            $flags[$typeKey] = '';
            $byIndex[$index] = self::emptySection($slug, $index, $typeKey);
        }

        return [
            'sections' => [],
            'flags' => $flags,
            'by_index' => $byIndex,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function emptySection(string $slug, int $index, string $typeKey): array
    {
        $sort = HomeSectionService::normalizeSort(SiteConfig::str('home_content_types_sort'));

        return [
            'type_index' => $index,
            'type_key' => $typeKey,
            'content_type' => $slug,
            'content_type_label' => ContentTypes::label($slug),
            'title' => self::sectionTitle($slug),
            'show_tabs' => true,
            'default_sort' => $sort,
            'tab_latest_active' => $sort === HomeSectionService::SORT_LATEST,
            'tab_popular_active' => $sort === HomeSectionService::SORT_POPULAR,
            'tab_rating_active' => $sort === HomeSectionService::SORT_RATING,
            'cards_list' => [],
            'cards_html' => '',
            'cards_count' => 0,
            'block_html' => '',
        ];
    }

    private static function sectionTitle(string $slug): string
    {
        $custom = trim(SiteConfig::str('home_content_type_' . $slug . '_title'));
        if ($custom !== '') {
            return $custom;
        }

        return ContentTypes::label($slug);
    }
}
