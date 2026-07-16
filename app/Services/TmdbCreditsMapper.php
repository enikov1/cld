<?php

namespace App\Services;

use App\Support\SiteConfig;

class TmdbCreditsMapper
{
    /**
     * @param  array<string, mixed>  $details
     * @return array{
     *     _actor_people: list<array{name: string, photo_url: string|null}>,
     *     _director_people: list<array{name: string, photo_url: string|null}>
     * }
     */
    public static function toPeopleLists(array $details): array
    {
        $credits = is_array($details['credits'] ?? null) ? $details['credits'] : [];
        $cast = is_array($credits['cast'] ?? null) ? $credits['cast'] : [];
        $crew = is_array($credits['crew'] ?? null) ? $credits['crew'] : [];
        $maxActors = SiteConfig::int('import_max_actors');

        $actors = [];
        foreach ($cast as $member) {
            if (!is_array($member)) {
                continue;
            }
            $name = trim((string)($member['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $actors[] = [
                'name' => $name,
                'photo_url' => self::profileUrl($member['profile_path'] ?? null),
            ];
            if ($maxActors > 0 && count($actors) >= $maxActors) {
                break;
            }
        }

        $directors = [];
        foreach ($crew as $member) {
            if (!is_array($member)) {
                continue;
            }
            $job = strtolower(trim((string)($member['job'] ?? '')));
            if ($job !== 'director') {
                continue;
            }
            $name = trim((string)($member['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $directors[] = [
                'name' => $name,
                'photo_url' => self::profileUrl($member['profile_path'] ?? null),
            ];
        }

        return [
            '_actor_people' => $actors,
            '_director_people' => $directors,
        ];
    }

    private static function profileUrl(mixed $path): ?string
    {
        $path = trim((string)$path);
        if ($path === '') {
            return null;
        }

        $base = rtrim((string)config('tmdb.image_base_url', 'https://image.tmdb.org/t/p'), '/');

        return $base . '/w185' . $path;
    }
}
