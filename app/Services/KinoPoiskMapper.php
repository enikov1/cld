<?php

namespace App\Services;

use App\Support\AgeLimitFormatter;

class KinoPoiskMapper
{
    /**
     * @param array<string,mixed> $details
     * @return array<string,mixed>
     */
    /**
     * @param array<int, array<string, mixed>> $distributions
     */
    public static function toSeriesAttributes(array $details, ?array $searchItem = null, array $distributions = []): array
    {
        $filmId = $details['kinopoiskId']
            ?? $details['filmId']
            ?? $searchItem['filmId']
            ?? $searchItem['kinopoiskId']
            ?? null;

        $title = $details['nameRu']
            ?? $details['nameEn']
            ?? $details['name']
            ?? $searchItem['nameRu']
            ?? $searchItem['name']
            ?? null;

        if (!$title || $filmId === null) {
            return [];
        }

        $countries = collect($details['countries'] ?? [])
            ->pluck('country')
            ->filter()
            ->values()
            ->all();

        $genres = collect($details['genres'] ?? [])
            ->pluck('genre')
            ->filter()
            ->values()
            ->all();

        $contentType = self::resolveContentType($details);
        $broadcastStatus = self::resolveBroadcastStatus($details);

        return [
            'kp_id' => (string)$filmId,
            'imdb_id' => isset($details['imdbId']) ? (string)$details['imdbId'] : null,
            'title' => (string)$title,
            'title_en' => $details['nameEn'] ?? null,
            'title_original' => $details['nameOriginal'] ?? null,
            'description' => $details['description'] ?? null,
            'short_description' => $details['shortDescription'] ?? null,
            'slogan' => $details['slogan'] ?? null,
            'year' => isset($details['year']) ? (int)$details['year'] : null,
            'start_year' => isset($details['startYear']) ? (int)$details['startYear'] : null,
            'end_year' => isset($details['endYear']) ? (int)$details['endYear'] : null,
            'duration_minutes' => isset($details['filmLength']) ? (int)$details['filmLength'] : null,
            'kp_rating' => isset($details['ratingKinopoisk']) ? (float)$details['ratingKinopoisk'] : null,
            'imdb_rating' => isset($details['ratingImdb']) ? (float)$details['ratingImdb'] : null,
            'kp_votes_count' => isset($details['ratingKinopoiskVoteCount']) ? (int)$details['ratingKinopoiskVoteCount'] : null,
            'imdb_votes_count' => isset($details['ratingImdbVoteCount']) ? (int)$details['ratingImdbVoteCount'] : null,
            '_genre_names' => $genres ?: [],
            '_country_names' => $countries ?: [],
            'age_limit' => AgeLimitFormatter::normalize($details['ratingAgeLimits'] ?? null),
            'premiere_date' => self::resolvePremiereDate($details, $distributions),
            'kp_web_url' => $details['webUrl'] ?? null,
            'content_type' => $contentType,
            'broadcast_status' => $broadcastStatus,
            'poster_source_url' => $details['posterUrl']
                ?? $details['posterUrlPreview']
                ?? $searchItem['posterUrlPreview']
                ?? null,
        ];
    }

    /**
     * @param array<string,mixed> $details
     */
    private static function resolveContentType(array $details): string
    {
        $type = strtoupper((string)($details['type'] ?? ''));
        if (in_array($type, ['TV_SERIES', 'MINI_SERIES'], true)) {
            return 'series';
        }
        if ($type === 'FILM') {
            return 'film';
        }

        return !empty($details['serial']) ? 'series' : 'film';
    }

    /**
     * @param array<string,mixed> $details
     */
    /**
     * @param array<int, array<string, mixed>> $distributions
     */
    public static function resolvePremiereDate(array $details, array $distributions = []): ?string
    {
        foreach (['premiereRu', 'premiereWorld', 'releaseDate'] as $key) {
            $parsed = self::parseDateString($details[$key] ?? null);
            if ($parsed !== null) {
                return $parsed;
            }
        }

        foreach ($distributions as $item) {
            if (!is_array($item)) {
                continue;
            }

            $type = strtoupper((string)($item['type'] ?? ''));
            if (!in_array($type, ['PREMIERE', 'WORLD_PREMIER', 'COUNTRY_SPECIFIC', 'ALL'], true)) {
                continue;
            }

            $parsed = self::parseDateString($item['date'] ?? null);
            if ($parsed !== null) {
                return $parsed;
            }
        }

        return null;
    }

    private static function parseDateString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $value, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private static function resolveBroadcastStatus(array $details): ?string
    {
        if (!empty($details['completed'])) {
            return 'completed';
        }

        $production = strtoupper((string)($details['productionStatus'] ?? ''));
        if (in_array($production, ['POST_PRODUCTION', 'FILMING'], true)) {
            return 'ongoing';
        }
        if ($production === 'SUSPENDED') {
            return 'paused';
        }

        $contentType = self::resolveContentType($details);
        if ($contentType === 'film') {
            return 'completed';
        }

        return 'ongoing';
    }
}
