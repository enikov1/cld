<?php

namespace App\Support;

use App\Models\Collection;
use App\Models\Series;
use App\Models\Studio;
use App\Support\SeriesUrl;

class Speedbar
{
    /** @var list<array{label: string, url: string|null, is_current: bool}> */
    private array $items = [];

    public static function create(): self
    {
        return new self();
    }

    /**
     * @param array<string,scalar|null> $params Route parameters for the segment.
     */
    public function add(string $key, array $params = [], ?string $label = null, bool $isCurrent = false): self
    {
        $segment = config('speedbar.segments.' . $key);
        if (!$segment) {
            throw new \InvalidArgumentException("Unknown speedbar segment: {$key}");
        }

        $routeName = $segment['route'];
        $routeParams = $this->resolveParams($segment, $params);
        $itemLabel = $label ?? $segment['label'] ?? '';

        if ($itemLabel === '') {
            throw new \InvalidArgumentException("Speedbar segment [{$key}] requires a label.");
        }

        $this->items[] = [
            'label' => $itemLabel,
            'url' => route($routeName, $routeParams),
            'is_current' => $isCurrent,
        ];

        return $this;
    }

    public function current(string $label, ?string $url = null): self
    {
        $this->items[] = [
            'label' => $label,
            'url' => $url ?? url()->current(),
            'is_current' => true,
        ];

        return $this;
    }

    public function pageSuffix(int $page): self
    {
        if ($page > 1) {
            $this->current('Страница ' . $page);
        }

        return $this;
    }

    /**
     * @return list<array{label: string, url: string|null, is_current: bool}>
     */
    public function items(): array
    {
        return $this->items;
    }

    public function isEmpty(): bool
    {
        return count($this->items) === 0;
    }

    /**
     * @return array<string,mixed>
     */
    public function toBreadcrumbJsonLd(): array
    {
        $elements = [];
        foreach ($this->items as $i => $item) {
            $elements[] = [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'item' => [
                    '@id' => $item['url'],
                    'name' => $item['label'],
                ],
            ];
        }

        return [
            '@type' => 'BreadcrumbList',
            'itemListElement' => $elements,
        ];
    }

    /**
     * @param array<string,mixed>|list<array<string,mixed>>|null $extra
     */
    public function toJsonLd(?array $extra = null): string
    {
        $breadcrumb = $this->toBreadcrumbJsonLd();

        if ($extra === null) {
            return json_encode(
                ['@context' => 'https://schema.org'] + $breadcrumb,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
        }

        $nodes = [$breadcrumb];
        foreach ($extra as $node) {
            if (is_array($node)) {
                $nodes[] = $node;
            }
        }

        return json_encode(
            [
                '@context' => 'https://schema.org',
                '@graph' => $nodes,
            ],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }

    public static function forHome(int $page = 1): self
    {
        if ($page <= 1) {
            return self::create();
        }

        return self::create()
            ->add('home')
            ->pageSuffix($page);
    }

    public static function forCollectionsIndex(): self
    {
        return self::create()
            ->add('home')
            ->add('collections', isCurrent: true);
    }

    public static function forCollection(Collection $collection, int $page = 1): self
    {
        $bar = self::create()
            ->add('home')
            ->add('collections')
            ->add('collection', ['slug' => $collection->slug], $collection->title, isCurrent: $page <= 1);

        return $bar->pageSuffix($page);
    }

    public static function forStudiosIndex(): self
    {
        return self::create()
            ->add('home')
            ->add('studios', isCurrent: true);
    }

    public static function forStudio(Studio $studio, int $page = 1): self
    {
        $bar = self::create()
            ->add('home')
            ->add('studios')
            ->add('studio', ['slug' => $studio->slug], $studio->title, isCurrent: $page <= 1);

        return $bar->pageSuffix($page);
    }

    public static function forSeries(Series $series): self
    {
        return self::create()
            ->add('home')
            ->add('series', ['seriesPath' => SeriesUrl::segment($series)], $series->title, isCurrent: true);
    }

    public static function forSearch(?string $query = null, int $page = 1): self
    {
        $bar = self::create()->add('home');

        if ($query !== null && $query !== '') {
            $label = 'Поиск: ' . $query;
            if ($page > 1) {
                $label .= ' — стр. ' . $page;
            }

            return $bar->current($label);
        }

        return $bar->add('search', isCurrent: true);
    }

    public static function forProfile(): self
    {
        return self::create()
            ->add('home')
            ->add('profile', isCurrent: true);
    }

    public static function forFavourites(): self
    {
        return self::create()
            ->add('home')
            ->add('favourites', isCurrent: true);
    }

    public static function forComingSoon(int $page = 1): self
    {
        $bar = self::create()
            ->add('home')
            ->add('coming_soon', isCurrent: $page <= 1);

        return $bar->pageSuffix($page);
    }

    public static function forCatalog(int $page = 1): self
    {
        $bar = self::create()
            ->add('home')
            ->add('catalog', isCurrent: $page <= 1);

        return $bar->pageSuffix($page);
    }

    public static function forTaxonomy(string $type, object $item, int $page = 1): self
    {
        $segment = match ($type) {
            'genre' => 'taxonomy_genre',
            'country' => 'taxonomy_country',
            'person' => 'taxonomy_person',
            'year' => 'taxonomy_year',
            default => throw new \InvalidArgumentException("Unknown taxonomy type: {$type}"),
        };

        $bar = self::create()
            ->add('home')
            ->add($segment, ['slug' => $item->slug], $item->name, isCurrent: $page <= 1);

        return $bar->pageSuffix($page);
    }

    /**
     * @param array<string,mixed> $segment
     * @param array<string,scalar|null> $params
     * @return array<string,scalar|null>
     */
    private function resolveParams(array $segment, array $params): array
    {
        $required = $segment['params'] ?? [];
        $resolved = [];

        foreach ($required as $name) {
            if (!array_key_exists($name, $params)) {
                throw new \InvalidArgumentException("Missing speedbar route param: {$name}");
            }
            $resolved[$name] = $params[$name];
        }

        return $resolved;
    }
}
