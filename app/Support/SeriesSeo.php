<?php

namespace App\Support;

use App\Models\Series;

class SeriesSeo
{
    public const SNIPPET_MAX = 180;

    public const MIN_RATING_COUNT = 5;

    public static function oneLine(string $text): string
    {
        $text = preg_replace('/\s+/u', ' ', trim($text)) ?? trim($text);

        return trim($text);
    }

    public static function snippet(string $text, int $max = self::SNIPPET_MAX): string
    {
        $text = self::oneLine($text);
        if ($text === '' || mb_strlen($text) <= $max) {
            return $text;
        }

        $cut = mb_substr($text, 0, $max);
        $space = mb_strrpos($cut, ' ');
        if ($space !== false && $space > (int) ($max * 0.6)) {
            $cut = mb_substr($cut, 0, $space);
        }

        return rtrim($cut, " \t.,;:—-") . '…';
    }

    public static function absoluteUrl(?string $path): string
    {
        $path = trim((string) $path);
        if ($path === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $path) === 1) {
            return $path;
        }

        if (str_starts_with($path, '//')) {
            return 'https:' . $path;
        }

        return url($path);
    }

    public static function ogType(Series $series): string
    {
        return ContentTypes::isFilmLike($series->content_type) ? 'video.movie' : 'video.tv_show';
    }

    public static function schemaType(Series $series): string
    {
        return ContentTypes::isFilmLike($series->content_type) ? 'Movie' : 'TVSeries';
    }

    /**
     * @param  array<string, mixed>  $vars
     */
    public static function metaDescription(Series $series, string $adminTemplate, TplRenderer $renderer, array $vars): string
    {
        $admin = trim($adminTemplate);
        if ($admin !== '') {
            return self::snippet($renderer->interpolate($admin, $vars));
        }

        $plot = trim((string) ($series->short_description ?: $series->description ?: ''));
        $fallback = trim(SiteConfig::str('series_meta_description_fallback'));
        $title = trim((string) $series->title);

        if ($plot !== '') {
            $text = $title . ' смотреть онлайн. ' . $plot;
        } else {
            $text = trim($title . ' смотреть онлайн бесплатно. ' . $fallback);
        }

        return self::snippet($text);
    }

    /**
     * @param  list<array<string, mixed>>  $schedule
     * @param  list<array<string, mixed>>  $reviewItems
     * @return list<array<string, mixed>>
     */
    public static function jsonLdNodes(Series $series, string $seriesUrl, array $schedule = [], array $reviewItems = [], int $commentCount = 0): array
    {
        $image = self::absoluteUrl((string) ($series->poster_url ?? ''));
        $description = self::snippet((string) ($series->short_description ?: $series->description ?: ''), 500);

        $node = [
            '@type' => self::schemaType($series),
            'name' => $series->title,
            'url' => $seriesUrl,
            'inLanguage' => 'ru',
        ];

        $alternate = trim((string) ($series->title_original ?: $series->title_en ?: ''));
        if ($alternate !== '' && $alternate !== $series->title) {
            $node['alternateName'] = $alternate;
        }
        if ($description !== '') {
            $node['description'] = $description;
        }
        if ($image !== '') {
            $node['image'] = $image;
        }

        $published = self::datePublished($series);
        if ($published !== null) {
            $node['datePublished'] = $published;
        }

        $genres = self::names($series, 'genres');
        if ($genres !== []) {
            $node['genre'] = $genres;
        }

        $actors = self::people($series, 'actors');
        if ($actors !== []) {
            $node['actor'] = $actors;
        }

        $directors = self::people($series, 'directors');
        if ($directors !== []) {
            $node['director'] = $directors;
        }

        $countries = self::names($series, 'countries');
        if ($countries !== []) {
            $node['countryOfOrigin'] = array_map(
                static fn (string $name) => ['@type' => 'Country', 'name' => $name],
                $countries
            );
        }

        $contentRating = $series->ageLimitLabel();
        if ($contentRating) {
            $node['contentRating'] = $contentRating;
        }

        if ($series->duration_minutes) {
            $node['duration'] = 'PT' . (int) $series->duration_minutes . 'M';
        }

        $seasonCount = count($schedule);
        $episodeCount = 0;
        foreach ($schedule as $season) {
            $episodeCount += count($season['episodes'] ?? []);
        }
        if ($seasonCount > 0 && !ContentTypes::isFilmLike($series->content_type)) {
            $node['numberOfSeasons'] = $seasonCount;
        }
        if ($episodeCount > 0) {
            $node['numberOfEpisodes'] = $episodeCount;
        }
        if ($commentCount > 0) {
            $node['commentCount'] = $commentCount;
        }

        $aggregate = self::aggregateRating($series);
        if ($aggregate !== null) {
            $node['aggregateRating'] = $aggregate;
        }

        $reviews = ReviewView::jsonLdNodes(array_slice($reviewItems, 0, 10));
        if ($reviews !== []) {
            $node['review'] = $reviews;
        }

        $nodes = [array_filter(
            $node,
            static fn ($value) => $value !== null && $value !== '' && $value !== []
        )];

        if ($image !== '') {
            $video = [
                '@type' => 'VideoObject',
                'name' => $series->title,
                'url' => $seriesUrl,
                'thumbnailUrl' => $image,
                'potentialAction' => [
                    '@type' => 'WatchAction',
                    'target' => $seriesUrl,
                ],
            ];
            if ($description !== '') {
                $video['description'] = $description;
            }
            if ($published !== null) {
                $video['uploadDate'] = $published;
            }
            if ($series->duration_minutes) {
                $video['duration'] = 'PT' . (int) $series->duration_minutes . 'M';
            }
            $nodes[] = $video;
        }

        return $nodes;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function aggregateRating(Series $series): ?array
    {
        $fromReviews = ReviewView::aggregateRatingJsonLd((int) $series->id);
        if (self::ratingCount($fromReviews) >= self::MIN_RATING_COUNT) {
            return $fromReviews;
        }

        $userCount = $series->votesCount();
        if ($userCount >= self::MIN_RATING_COUNT && $series->userRatingLabel()) {
            return [
                '@type' => 'AggregateRating',
                'ratingValue' => $series->userRatingLabel(),
                'bestRating' => '10',
                'worstRating' => '1',
                'ratingCount' => $userCount,
            ];
        }

        $kpCount = (int) ($series->kp_votes_count ?? 0);
        if ($series->kp_rating && $kpCount >= self::MIN_RATING_COUNT) {
            return [
                '@type' => 'AggregateRating',
                'ratingValue' => (string) $series->kp_rating,
                'bestRating' => '10',
                'worstRating' => '1',
                'ratingCount' => $kpCount,
            ];
        }

        $imdbCount = (int) ($series->imdb_votes_count ?? 0);
        if ($series->imdb_rating && $imdbCount >= self::MIN_RATING_COUNT) {
            return [
                '@type' => 'AggregateRating',
                'ratingValue' => (string) $series->imdb_rating,
                'bestRating' => '10',
                'worstRating' => '1',
                'ratingCount' => $imdbCount,
            ];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|null  $node
     */
    private static function ratingCount(?array $node): int
    {
        if ($node === null) {
            return 0;
        }

        return (int) ($node['ratingCount'] ?? 0);
    }

    private static function datePublished(Series $series): ?string
    {
        if ($series->premiere_date && !$series->premiereIsYearOnly()) {
            return $series->premiere_date->format('Y-m-d');
        }

        $year = (int) ($series->year ?: $series->start_year ?: 0);
        if ($year >= 1900 && $year <= 2100) {
            return (string) $year;
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private static function names(Series $series, string $relation): array
    {
        if (!$series->relationLoaded($relation)) {
            return [];
        }

        $names = [];
        foreach ($series->{$relation} as $item) {
            $name = TaxonomyRegistry::displayName($item);
            if ($name !== '') {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * @return list<array<string, string>>
     */
    private static function people(Series $series, string $relation): array
    {
        if (!$series->relationLoaded($relation)) {
            return [];
        }

        $people = [];
        foreach ($series->{$relation}->take(15) as $person) {
            $name = Utf8::ucfirst((string) ($person->name ?? ''));
            if ($name === '') {
                continue;
            }
            $node = [
                '@type' => 'Person',
                'name' => $name,
            ];
            $slug = trim((string) ($person->slug ?? ''));
            if ($slug !== '') {
                $node['url'] = self::absoluteUrl('/person/' . $slug . '/');
            }
            $people[] = $node;
        }

        return $people;
    }
}
