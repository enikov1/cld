<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class TmdbGenreCache
{
    private const CACHE_TTL = 86400;

    /**
     * @return array<int, string>
     */
    public function movieGenres(TmdbClient $client): array
    {
        return $this->loadGenres($client, 'movie');
    }

    /**
     * @return array<int, string>
     */
    public function tvGenres(TmdbClient $client): array
    {
        return $this->loadGenres($client, 'tv');
    }

    /**
     * @param  list<int>  $genreIds
     * @return list<string>
     */
    public function resolveNames(array $genreIds, string $mediaType, TmdbClient $client): array
    {
        if ($genreIds === []) {
            return [];
        }

        $map = $mediaType === 'tv'
            ? $this->tvGenres($client)
            : $this->movieGenres($client);

        $names = [];
        foreach ($genreIds as $id) {
            $id = (int)$id;
            if ($id > 0 && isset($map[$id])) {
                $names[] = $map[$id];
            }
        }

        return $names;
    }

    /**
     * @return array<int, string>
     */
    private function loadGenres(TmdbClient $client, string $type): array
    {
        if (!$client->isConfigured()) {
            return [];
        }

        return Cache::remember(
            'tmdb_genres_' . $type,
            self::CACHE_TTL,
            function () use ($client, $type): array {
                $payload = $client->getGenreList($type);
                $genres = $payload['genres'] ?? [];
                if (!is_array($genres)) {
                    return [];
                }

                $map = [];
                foreach ($genres as $genre) {
                    if (!is_array($genre)) {
                        continue;
                    }
                    $id = (int)($genre['id'] ?? 0);
                    $name = trim((string)($genre['name'] ?? ''));
                    if ($id > 0 && $name !== '') {
                        $map[$id] = $name;
                    }
                }

                return $map;
            },
        );
    }
}
