<?php

namespace App\Services;

class SeriesLookupService
{
    public function __construct(
        private readonly KinoPoiskClient $kpClient,
        private readonly TmdbClient $tmdbClient,
        private readonly TmdbGenreCache $genreCache,
    ) {
    }

    /**
     * @return array{results: list<array<string, mixed>>, warnings: list<string>}
     */
    public function search(string $query, int $limit = 10): array
    {
        $query = trim($query);
        if (mb_strlen($query) < 2) {
            return [
                'results' => [],
                'warnings' => ['Введите минимум 2 символа для поиска'],
            ];
        }

        $limit = max(1, min(20, $limit));
        $warnings = [];
        $results = [];

        $kpResults = [];
        $tmdbResults = [];

        if ($this->kpClient->isConfigured()) {
            $kpResults = $this->kpClient->searchByKeyword($query, $limit);
        } else {
            $warnings[] = 'API-ключ KinoPoisk не настроен';
        }

        if ($this->tmdbClient->isConfigured()) {
            $payload = $this->tmdbClient->searchMulti($query, 1);
            $tmdbResults = is_array($payload['results'] ?? null) ? $payload['results'] : [];
        } else {
            $warnings[] = 'API-ключ TMDB не настроен';
        }

        foreach ($kpResults as $item) {
            if (!is_array($item)) {
                continue;
            }
            $normalized = $this->normalizeKinopoisk($item);
            if ($normalized !== null) {
                $results[] = $normalized;
            }
            if (count(array_filter($results, fn ($r) => $r['source'] === 'kinopoisk')) >= $limit) {
                break;
            }
        }

        $tmdbCount = 0;
        foreach ($tmdbResults as $item) {
            if (!is_array($item)) {
                continue;
            }
            $normalized = $this->normalizeTmdb($item);
            if ($normalized === null) {
                continue;
            }
            $results[] = $normalized;
            $tmdbCount++;
            if ($tmdbCount >= $limit) {
                break;
            }
        }

        return [
            'results' => $results,
            'warnings' => $warnings,
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>|null
     */
    private function normalizeKinopoisk(array $item): ?array
    {
        $id = $item['filmId'] ?? $item['kinopoiskId'] ?? null;
        if ($id === null) {
            return null;
        }

        $title = $item['nameRu'] ?? $item['nameEn'] ?? $item['name'] ?? null;
        if (!$title || trim((string)$title) === '') {
            return null;
        }

        $genres = [];
        if (isset($item['genres']) && is_array($item['genres'])) {
            foreach ($item['genres'] as $genre) {
                if (is_array($genre)) {
                    $name = $genre['genre'] ?? null;
                } else {
                    $name = $genre;
                }
                $name = trim((string)$name);
                if ($name !== '') {
                    $genres[] = $name;
                }
            }
        }

        $type = strtoupper((string)($item['type'] ?? ''));
        $mediaType = in_array($type, ['TV_SERIES', 'MINI_SERIES'], true) ? 'series' : 'film';

        return [
            'source' => 'kinopoisk',
            'id' => (string)$id,
            'media_type' => $mediaType,
            'title' => (string)$title,
            'title_original' => $item['nameEn'] ?? $item['nameOriginal'] ?? null,
            'year' => isset($item['year']) ? (int)$item['year'] : null,
            'genres' => $genres,
            'poster_url' => $item['posterUrlPreview'] ?? $item['posterUrl'] ?? null,
            'rating' => isset($item['rating']) ? (float)$item['rating'] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>|null
     */
    private function normalizeTmdb(array $item): ?array
    {
        $mediaType = strtolower((string)($item['media_type'] ?? ''));
        if (!in_array($mediaType, ['movie', 'tv'], true)) {
            return null;
        }

        $id = $item['id'] ?? null;
        if ($id === null) {
            return null;
        }

        $title = $mediaType === 'tv'
            ? ($item['name'] ?? null)
            : ($item['title'] ?? null);
        if (!$title || trim((string)$title) === '') {
            return null;
        }

        $date = $mediaType === 'tv'
            ? ($item['first_air_date'] ?? null)
            : ($item['release_date'] ?? null);
        $year = null;
        if (is_string($date) && preg_match('/^(\d{4})/', $date, $m)) {
            $year = (int)$m[1];
        }

        $genreIds = is_array($item['genre_ids'] ?? null) ? $item['genre_ids'] : [];
        $genres = $this->genreCache->resolveNames($genreIds, $mediaType, $this->tmdbClient);

        $posterPath = trim((string)($item['poster_path'] ?? ''));
        $posterUrl = null;
        if ($posterPath !== '') {
            $base = rtrim((string)config('tmdb.image_base_url', 'https://image.tmdb.org/t/p'), '/');
            $posterUrl = $base . '/w92' . $posterPath;
        }

        return [
            'source' => 'tmdb',
            'id' => (string)$id,
            'media_type' => $mediaType,
            'title' => (string)$title,
            'title_original' => $mediaType === 'tv'
                ? ($item['original_name'] ?? null)
                : ($item['original_title'] ?? null),
            'year' => $year,
            'genres' => $genres,
            'poster_url' => $posterUrl,
            'rating' => isset($item['vote_average']) ? round((float)$item['vote_average'], 1) : null,
        ];
    }
}
