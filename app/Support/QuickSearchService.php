<?php

namespace App\Support;

use App\Models\Collection;
use App\Models\Genre;
use App\Models\Person;
use App\Models\Series;
use App\Models\Studio;
use App\Models\Year;
use App\Support\SeriesUrl;
use Illuminate\Database\Eloquent\Builder;

class QuickSearchService
{
    /**
     * @return array{query: string, groups: list<array{type: string, label: string, items: list<array<string, string>>}>}
     */
    public static function suggest(string $query, ?int $limit = null): array
    {
        $query = trim($query);
        $minChars = SiteConfig::int('search_suggest_min_chars');
        $limit = max(1, min(10, $limit ?? SiteConfig::int('search_suggest_limit')));

        if (mb_strlen($query) < $minChars) {
            return [
                'query' => $query,
                'groups' => [],
            ];
        }

        return [
            'query' => $query,
            'groups' => self::buildGroups($query, $limit, includeSeries: true, includeDirectors: false),
        ];
    }

    /**
     * @return list<array{type: string, label: string, items: list<array<string, string>>}>
     */
    public static function taxonomyGroups(string $query, ?int $limit = null): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $limit = max(1, min(60, $limit ?? SiteConfig::int('search_full_group_limit')));

        return self::buildGroups($query, $limit, includeSeries: false, includeDirectors: true);
    }

    public static function hasResults(string $query): bool
    {
        $query = trim($query);
        if ($query === '') {
            return false;
        }

        if (self::suggest($query)['groups'] !== []) {
            return true;
        }

        if (self::taxonomyGroups($query) !== []) {
            return true;
        }

        $like = '%' . self::escapeLike($query) . '%';

        return Series::query()
            ->published()
            ->where(function (Builder $builder) use ($like) {
                $builder->where('description', 'like', $like)
                    ->orWhere('short_description', 'like', $like);
            })
            ->exists();
    }

    /**
     * @return list<array{type: string, label: string, items: list<array<string, string>>}>
     */
    private static function buildGroups(
        string $query,
        int $limit,
        bool $includeSeries,
        bool $includeDirectors,
    ): array {
        $like = '%' . self::escapeLike($query) . '%';
        $built = [];

        if ($includeSeries) {
            $series = Series::query()
                ->published()
                ->where(function (Builder $builder) use ($like) {
                    $builder->where('title', 'like', $like)
                        ->orWhere('title_en', 'like', $like)
                        ->orWhere('title_original', 'like', $like);
                })
                ->orderByDesc('views_count')
                ->orderByDesc('id')
                ->limit($limit)
                ->get();

            if ($series->isNotEmpty()) {
                $built['series'] = [
                    'type' => 'series',
                    'label' => 'Сериалы',
                    'items' => $series->map(static fn (Series $item) => [
                        'type' => 'series',
                        'title' => $item->title,
                        'subtitle' => $item->year ? (string)$item->year : '',
                        'url' => SeriesUrl::path($item),
                        'image' => $item->poster_url ?? '',
                    ])->all(),
                ];
            }
        }

        $actors = self::searchPeople($like, $limit, 'actor')->get();
        if ($actors->isNotEmpty()) {
            $built['actors'] = [
                'type' => 'actors',
                'label' => 'Актёры',
                'items' => $actors->map(static fn (Person $item) => [
                    'type' => 'actors',
                    'title' => Utf8::ucfirst($item->name),
                    'subtitle' => 'Актёр',
                    'url' => '/person/' . rawurlencode($item->slug) . '/',
                    'image' => $item->photo_url ?? '',
                ])->all(),
            ];
        }

        if ($includeDirectors) {
            $directors = self::searchPeople($like, $limit, 'director')->get();
            if ($directors->isNotEmpty()) {
                $built['directors'] = [
                    'type' => 'directors',
                    'label' => 'Режиссёры',
                    'items' => $directors->map(static fn (Person $item) => [
                        'type' => 'directors',
                        'title' => Utf8::ucfirst($item->name),
                        'subtitle' => 'Режиссёр',
                        'url' => '/person/' . rawurlencode($item->slug) . '/',
                        'image' => $item->photo_url ?? '',
                    ])->all(),
                ];
            }
        }

        $genres = Genre::query()
            ->where('is_active', true)
            ->where('is_hidden', false)
            ->where('name', 'like', $like)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->limit($limit)
            ->get();

        if ($genres->isNotEmpty()) {
            $built['genres'] = [
                'type' => 'genres',
                'label' => 'Жанры',
                'items' => $genres->map(static fn (Genre $item) => [
                    'type' => 'genres',
                    'title' => TaxonomyRegistry::displayName($item),
                    'subtitle' => 'Жанр',
                    'url' => '/genre/' . rawurlencode($item->slug) . '/',
                    'image' => '',
                ])->all(),
            ];
        }

        $years = Year::query()
            ->where('is_active', true)
            ->where('is_hidden', false)
            ->where(function (Builder $builder) use ($like, $query) {
                $builder->where('name', 'like', $like)
                    ->orWhere('slug', 'like', $like);

                if (ctype_digit($query)) {
                    $builder->orWhere('slug', $query)
                        ->orWhere('name', $query);
                }
            })
            ->orderByDesc('name')
            ->limit($limit)
            ->get();

        if ($years->isNotEmpty()) {
            $built['years'] = [
                'type' => 'years',
                'label' => 'Годы',
                'items' => $years->map(static fn (Year $item) => [
                    'type' => 'years',
                    'title' => TaxonomyRegistry::displayName($item),
                    'subtitle' => 'Сериалы года',
                    'url' => '/year/' . rawurlencode($item->slug) . '/',
                    'image' => '',
                ])->all(),
            ];
        }

        $studios = Studio::query()
            ->where('is_active', true)
            ->where('is_hidden', false)
            ->where('title', 'like', $like)
            ->catalogOrder()
            ->limit($limit)
            ->get();

        if ($studios->isNotEmpty()) {
            $built['studios'] = [
                'type' => 'studios',
                'label' => 'Студии',
                'items' => $studios->map(static fn (Studio $item) => [
                    'type' => 'studios',
                    'title' => TaxonomyRegistry::displayName($item),
                    'subtitle' => 'Студия',
                    'url' => '/studios/' . $item->slug . '/',
                    'image' => $item->logo_url ?? '',
                ])->all(),
            ];
        }

        $collections = Collection::query()
            ->where('is_active', true)
            ->where('is_hidden', false)
            ->where('title', 'like', $like)
            ->catalogOrder()
            ->limit($limit)
            ->get();

        if ($collections->isNotEmpty()) {
            $built['collections'] = [
                'type' => 'collections',
                'label' => 'Подборки',
                'items' => $collections->map(static fn (Collection $item) => [
                    'type' => 'collections',
                    'title' => TaxonomyRegistry::displayName($item),
                    'subtitle' => 'Подборка',
                    'url' => '/collections/' . $item->slug . '/',
                    'image' => $item->cover_url ?? '',
                ])->all(),
            ];
        }

        $order = $includeDirectors
            ? ['actors', 'directors', 'genres', 'years', 'studios', 'collections']
            : ['series', 'studios', 'collections', 'genres', 'years', 'actors'];

        $groups = [];
        foreach ($order as $type) {
            if (isset($built[$type])) {
                $groups[] = $built[$type];
            }
        }

        return $groups;
    }

    private static function searchPeople(string $like, int $limit, string $role): Builder
    {
        $query = Person::query()
            ->where('is_active', true)
            ->where('is_hidden', false)
            ->where('name', 'like', $like);

        if ($role === 'director') {
            $query->whereHas('series', static fn (Builder $builder) => $builder->where('series_person.role', 'director'));
        } else {
            $query->where(static function (Builder $builder) {
                $builder->whereHas('series', static fn (Builder $series) => $series->where('series_person.role', 'actor'))
                    ->orWhereDoesntHave('series');
            });
        }

        return $query
            ->orderBy('sort_order')
            ->orderBy('name')
            ->limit($limit);
    }

    private static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
