<?php

namespace App\Support;

use App\Models\Collection;
use App\Models\Country;
use App\Models\Genre;
use App\Models\Year;
use App\Models\NavItem;
use App\Models\NavMegaButton;
use App\Models\NavMegaLink;
use App\Models\NavMegaSection;
use App\Models\Studio;
use App\Models\Voice;
use Illuminate\Database\Eloquent\Builder;

class NavMenuBuilder
{
    private static ?string $requestId = null;

    /** @var array<string, mixed>|null */
    private static ?array $forTplMemo = null;

    public static function flushRequestCache(): void
    {
        self::$requestId = null;
        self::$forTplMemo = null;
    }

    /**
     * @return array<string, mixed>
     */
    public static function forTpl(): array
    {
        $id = (string) spl_object_id(request());
        if (self::$requestId === $id && self::$forTplMemo !== null) {
            return self::$forTplMemo;
        }

        $items = NavItem::query()
            ->with([
                'megaButtons' => fn ($q) => $q->where('is_active', true),
                'megaSections' => fn ($q) => $q->where('is_active', true)->with([
                    'links' => fn ($lq) => $lq->where('is_active', true),
                ]),
            ])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $desktop = [];
        $mobile = [];

        foreach ($items as $item) {
            $mapped = self::mapItem($item);

            if ($item->show_desktop) {
                $desktop[] = $mapped;
            }

            if ($item->show_mobile) {
                $mobile[] = $mapped;
            }
        }

        self::$requestId = $id;
        self::$forTplMemo = [
            'nav_desktop_items' => $desktop,
            'nav_mobile_items' => $mobile,
            'categories_list' => $desktop,
        ];

        return self::$forTplMemo;
    }

    /**
     * @return array<string, mixed>
     */
    private static function mapItem(NavItem $item): array
    {
        return [
            'title' => $item->title,
            'url' => self::resolveLink(
                $item->link_type,
                $item->custom_url,
                $item->taxonomy_type,
                self::taxonomySlug($item->taxonomy_type, $item->taxonomy_id),
            ),
            'has_mega' => $item->has_mega,
            'mega_buttons' => $item->megaButtons
                ->map(fn (NavMegaButton $btn) => [
                    'title' => $btn->title,
                    'subtitle' => $btn->subtitle ?? '',
                    'url' => self::resolveLink(
                        $btn->link_type,
                        $btn->custom_url,
                        $btn->taxonomy_type,
                        self::taxonomySlug($btn->taxonomy_type, $btn->taxonomy_id),
                    ),
                ])
                ->values()
                ->all(),
            'mega_sections' => $item->megaSections
                ->map(fn (NavMegaSection $section) => [
                    'title' => $section->title,
                    'css_class' => $section->css_class,
                    'links' => self::buildSectionLinks($section),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return list<array{label: string, url: string}>
     */
    private static function buildSectionLinks(NavMegaSection $section): array
    {
        return match ($section->source_type) {
            NavMegaSection::SOURCE_GENRES => self::taxonomyLinks(
                Genre::query()->where('is_active', true),
                TaxonomyRegistry::TYPE_GENRES,
                $section,
            ),
            NavMegaSection::SOURCE_COUNTRIES => self::taxonomyLinks(
                Country::query()->where('is_active', true),
                TaxonomyRegistry::TYPE_COUNTRIES,
                $section,
            ),
            NavMegaSection::SOURCE_VOICES => self::taxonomyLinks(
                Voice::query()
                    ->where('is_active', true)
                    ->where('is_hidden', false)
                    ->whereHas('series', fn (Builder $q) => self::visibleSeries($q)),
                TaxonomyRegistry::TYPE_VOICES,
                $section,
            ),
            NavMegaSection::SOURCE_COLLECTIONS => Collection::query()
                ->where('is_active', true)
                ->where('is_hidden', false)
                ->catalogOrder()
                ->limit(max(1, $section->item_limit))
                ->get()
                ->map(fn (Collection $c) => [
                    'label' => TaxonomyRegistry::displayName($c),
                    'url' => '/collections/' . $c->slug . '/',
                ])
                ->all(),
            NavMegaSection::SOURCE_STUDIOS => Studio::query()
                ->where('is_active', true)
                ->where('is_hidden', false)
                ->withCount(['items as items_count' => function ($query) {
                    $query->whereHas('series', function ($q) {
                        $q->where('is_active', true)
                            ->where('is_hidden', false);
                    });
                }])
                ->orderByDesc('items_count')
                ->orderByDesc('is_pinned')
                ->orderBy('sort_order')
                ->orderByDesc('id')
                ->limit(max(1, $section->item_limit))
                ->get()
                ->map(fn (Studio $s) => [
                    'label' => TaxonomyRegistry::displayName($s),
                    'url' => '/studios/' . $s->slug . '/',
                ])
                ->all(),
            NavMegaSection::SOURCE_YEARS => Year::query()
                ->where('is_active', true)
                ->where('is_hidden', false)
                ->orderByDesc('sort_order')
                ->limit(max(1, $section->item_limit))
                ->get()
                ->map(fn (Year $y) => [
                    'label' => TaxonomyRegistry::displayName($y),
                    'url' => TaxonomyRegistry::publicUrl(TaxonomyRegistry::TYPE_YEARS, $y->slug),
                ])
                ->all(),
            default => $section->links
                ->map(fn (NavMegaLink $link) => [
                    'label' => Utf8::ucfirst($link->label),
                    'url' => $link->url,
                ])
                ->values()
                ->all(),
        };
    }

    /**
     * @param Builder<\Illuminate\Database\Eloquent\Model> $query
     * @return list<array{label: string, url: string}>
     */
    private static function taxonomyLinks(Builder $query, string $type, NavMegaSection $section): array
    {
        $sort = $section->itemSort();
        if ($sort === NavMegaSection::SORT_SERIES_COUNT) {
            $query->withCount(['series as series_count' => fn (Builder $q) => self::visibleSeries($q)])
                ->orderByDesc('series_count')
                ->orderBy('name');
        } elseif ($sort === NavMegaSection::SORT_ORDER) {
            $query->orderBy('sort_order')->orderBy('name');
        } else {
            $query->orderBy('name');
        }

        return $query
            ->limit(max(1, (int) $section->item_limit))
            ->get()
            ->map(fn ($item) => [
                'label' => TaxonomyRegistry::displayName($item),
                'url' => TaxonomyRegistry::publicUrl($type, (string) $item->slug),
            ])
            ->all();
    }

    private static function visibleSeries(Builder $query): Builder
    {
        return $query->where('is_active', true)->where('is_hidden', false);
    }

    private static function taxonomySlug(?string $type, ?int $id): ?string
    {
        if (!$type || !$id || !TaxonomyRegistry::isValidType($type)) {
            return null;
        }

        $model = TaxonomyRegistry::modelClass($type);

        return $model::query()->whereKey($id)->value('slug');
    }

    public static function resolveLink(
        string $linkType,
        ?string $customUrl,
        ?string $taxonomyType = null,
        ?string $taxonomySlug = null,
    ): string {
        return match ($linkType) {
            NavItem::LINK_HOME => '/',
            NavItem::LINK_COLLECTIONS => '/collections/',
            NavItem::LINK_STUDIOS => '/studios/',
            NavItem::LINK_CATALOG => '/catalog/',
            NavItem::LINK_COMING_SOON => '/skoro/',
            NavItem::LINK_CALENDAR => '/kalendar/',
            NavItem::LINK_CATEGORY => '/',
            NavItem::LINK_TAXONOMY => ($taxonomyType && $taxonomySlug)
                ? TaxonomyRegistry::publicUrl($taxonomyType, $taxonomySlug)
                : '/',
            NavItem::LINK_CUSTOM => $customUrl !== null && $customUrl !== '' ? $customUrl : '/',
            default => '/',
        };
    }
}
