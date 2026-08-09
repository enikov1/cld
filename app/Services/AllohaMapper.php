<?php

namespace App\Services;

use App\Support\ContentTypes;
use App\Support\SiteConfig;

class AllohaMapper
{
    /**
     * @param array<string,mixed> $response
     * @return array<string,mixed>
     */
    public static function toSeriesAttributes(array $response): array
    {
        $data = $response['data'] ?? $response;
        if (!is_array($data) || empty($data['name'])) {
            return [];
        }

        $ids = is_array($data['ids'] ?? null) ? $data['ids'] : [];
        $rating = is_array($data['rating'] ?? null) ? $data['rating'] : [];
        $credits = is_array($data['credits'] ?? null) ? $data['credits'] : [];
        $premiere = is_array($data['premiere'] ?? null) ? $data['premiere'] : [];
        $category = is_array($data['category'] ?? null) ? $data['category'] : [];

        $kpId = $ids['kp'] ?? null;
        if ($kpId === null) {
            return [];
        }

        $actors = TaxonomyService::parseCreditsString($credits['actors'] ?? null);
        $maxActors = SiteConfig::int('import_max_actors');
        if ($maxActors > 0 && count($actors) > $maxActors) {
            $actors = array_slice($actors, 0, $maxActors);
        }

        $directors = TaxonomyService::parseCreditsString($credits['directors'] ?? null);
        $maxDirectors = SiteConfig::int('import_max_directors');
        if ($maxDirectors > 0 && count($directors) > $maxDirectors) {
            $directors = array_slice($directors, 0, $maxDirectors);
        }

        return [
            'kp_id' => (string)$kpId,
            'imdb_id' => isset($ids['imdb']) ? (string)$ids['imdb'] : null,
            'tmdb_id' => isset($ids['tmdb']) ? (string)$ids['tmdb'] : null,
            'title' => (string)$data['name'],
            'title_original' => $data['original_name'] ?? null,
            'title_en' => $data['alternative_name'] ?? null,
            'description' => $data['description'] ?? null,
            'slogan' => $data['tagline'] ?? null,
            'year' => isset($data['year']) ? (int)$data['year'] : null,
            'kp_rating' => isset($rating['kp']) ? (float)$rating['kp'] : null,
            'imdb_rating' => isset($rating['imdb']) ? (float)$rating['imdb'] : null,
            'age_limit' => isset($rating['age']) ? (string)$rating['age'] : null,
            'duration_minutes' => self::parseRuntime($data['runtime'] ?? null),
            'premiere_date' => self::parseDate($premiere['ru'] ?? $premiere['world'] ?? null),
            'content_type' => self::resolveContentType((string)($category['slug'] ?? '')),
            'alloha_token' => $data['token'] ?? null,
            'season_number' => self::resolveSeasonNumber($data),
            'last_episode_number' => self::resolveLastEpisode($data),
            '_genre_names' => self::splitList($data['genre'] ?? null),
            '_country_names' => self::splitList($data['country'] ?? null),
            '_actor_people' => $actors,
            '_director_people' => $directors,
            'poster_source_url' => $data['poster'] ?? null,
            '_translations' => is_array($data['translations'] ?? null) ? $data['translations'] : [],
            '_default_iframe' => $data['iframe'] ?? null,
        ];
    }

    /**
     * @return list<string>
     */
    private static function splitList(mixed $value): array
    {
        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        $items = [];
        foreach (preg_split('/\s*,\s*/', trim($value)) as $part) {
            $part = trim($part);
            if ($part !== '') {
                $items[] = $part;
            }
        }

        return $items;
    }

    private static function parseRuntime(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            $minutes = (int)$value;

            return $minutes > 0 ? $minutes : null;
        }

        $value = trim((string)$value);

        if (preg_match('/^(\d+):(\d{1,2}):(\d{1,2})$/', $value, $m)) {
            $minutes = ((int)$m[1] * 60) + (int)$m[2] + (int)round((int)$m[3] / 60);

            return $minutes > 0 ? $minutes : null;
        }

        if (preg_match('/^(\d{1,2}):(\d{1,2})$/', $value, $m)) {
            $minutes = ((int)$m[1] * 60) + (int)$m[2];

            return $minutes > 0 ? $minutes : null;
        }

        if (preg_match('/(\d+)\s*(?:min|мин)\b/i', $value, $m)) {
            $minutes = (int)$m[1];

            return $minutes > 0 ? $minutes : null;
        }

        if (preg_match('/(\d+)/', $value, $m)) {
            $minutes = (int)$m[1];

            return $minutes > 0 ? $minutes : null;
        }

        return null;
    }

    private static function parseDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_string($value) && !is_numeric($value)) {
            return null;
        }

        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $value, $m)) {
            return $m[1];
        }

        $timestamp = strtotime($value);

        return $timestamp ? date('Y-m-d', $timestamp) : null;
    }

    private static function resolveContentType(string $slug): string
    {
        $slug = strtolower(trim($slug));

        $mapped = match ($slug) {
            'film', 'movie' => 'film',
            'serial' => 'series',
            'multfilm', 'cartoon', 'animation' => 'cartoon',
            'multserial', 'cartoon-serial', 'cartoon_serial' => 'cartoon_series',
            'anime', 'anime-serial', 'anime_serial' => 'anime',
            'dorama' => 'dorama',
            'tv-show', 'tvshow', 'tv_show' => 'tv_show',
            default => $slug,
        };

        return ContentTypes::isValid($mapped) ? $mapped : 'film';
    }

    private static function resolveSeasonNumber(array $data): ?int
    {
        if (isset($data['seasons_count']) && (int)$data['seasons_count'] > 0) {
            return (int)$data['seasons_count'];
        }

        $seasons = $data['seasons'] ?? [];
        if (!is_array($seasons) || $seasons === []) {
            return null;
        }

        $max = 0;
        foreach ($seasons as $season) {
            if (!is_array($season)) {
                continue;
            }
            $num = (int)($season['season'] ?? 0);
            if ($num > $max) {
                $max = $num;
            }
        }

        return $max > 0 ? $max : null;
    }

    private static function resolveLastEpisode(array $data): ?int
    {
        if (isset($data['last_episode']) && (int)$data['last_episode'] > 0) {
            return (int)$data['last_episode'];
        }

        $seasons = $data['seasons'] ?? [];
        if (!is_array($seasons) || $seasons === []) {
            return null;
        }

        $max = 0;
        foreach ($seasons as $season) {
            if (!is_array($season)) {
                continue;
            }
            $count = (int)($season['episodes_count'] ?? 0);
            if ($count > $max) {
                $max = $count;
            }
        }

        return $max > 0 ? $max : null;
    }
}
