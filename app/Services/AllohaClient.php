<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class AllohaClient
{
    private string $apiToken;
    private string $baseUrl;
    private int $timeout;

    public function __construct()
    {
        $this->apiToken = AllohaConfig::apiToken();
        $this->baseUrl = rtrim((string)config('alloha.base_url', 'https://apbugall.org'), '/');
        $this->timeout = (int)config('alloha.request_timeout', 20);
    }

    public function isConfigured(): bool
    {
        return $this->apiToken !== '';
    }

    /**
     * @return array<string,mixed>
     */
    public function getLatest(int $days = 7, int $page = 1): array
    {
        return $this->getJson('/v2/movies/latest', [
            'days' => max(1, min(30, $days)),
            'page' => max(1, $page),
        ]);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function fetchAllLatest(int $days = 7, int $maxPages = 20): array
    {
        $items = [];
        $page = 1;

        while ($page <= $maxPages) {
            $response = $this->getLatest($days, $page);
            $chunk = $response['data'] ?? [];
            if (!is_array($chunk) || $chunk === []) {
                break;
            }

            foreach ($chunk as $row) {
                if (is_array($row)) {
                    $items[] = $row;
                }
            }

            $hasMore = (bool)($response['meta']['has_more'] ?? false);
            if (!$hasMore) {
                break;
            }

            $page++;
        }

        return $items;
    }

    /**
     * @return array<string,mixed>
     */
    public function getMovieByKp(int|string $kpId): array
    {
        return $this->getJson('/v2/movies/kp/' . rawurlencode((string) $kpId));
    }

    public function getMovieByImdb(string $imdbId): array
    {
        return $this->getJson('/v2/movies/imdb/' . rawurlencode($imdbId));
    }

    public function getMovieByTmdb(int|string $tmdbId): array
    {
        return $this->getJson('/v2/movies/tmdb/' . rawurlencode((string) $tmdbId));
    }

    /**
     * Lookup movie details: KP → IMDb → TMDB.
     *
     * @return array<string, mixed>
     */
    public function getMovieWithFallback(?string $kpId = null, ?string $imdbId = null, ?string $tmdbId = null, ?int $timeout = null, int $maxLookups = 3): array
    {
        $timeout = $timeout ?? $this->timeout;
        $maxLookups = max(1, $maxLookups);
        $lookups = 0;

        $kpId = trim((string) ($kpId ?? ''));
        if ($kpId !== '' && preg_match('/^\d+$/', $kpId)) {
            $response = $this->getJson('/v2/movies/kp/' . rawurlencode($kpId), [], $timeout);
            $lookups++;
            if ($response !== []) {
                return $response;
            }
            if ($lookups >= $maxLookups) {
                return [];
            }
        }

        $imdbId = self::normalizeImdbId($imdbId);
        if ($imdbId !== '') {
            $response = $this->getJson('/v2/movies/imdb/' . rawurlencode($imdbId), [], $timeout);
            $lookups++;
            if ($response !== []) {
                return $response;
            }
            if ($lookups >= $maxLookups) {
                return [];
            }
        }

        $tmdbId = trim((string) ($tmdbId ?? ''));
        if ($tmdbId !== '' && preg_match('/^\d+$/', $tmdbId)) {
            return $this->getJson('/v2/movies/tmdb/' . rawurlencode($tmdbId), [], $timeout);
        }

        return [];
    }

    public static function normalizeImdbId(mixed $value): string
    {
        $imdbId = trim((string) ($value ?? ''));
        if ($imdbId === '') {
            return '';
        }

        if (preg_match('/^tt\d+$/i', $imdbId)) {
            return 'tt' . substr($imdbId, 2);
        }

        if (preg_match('/^\d+$/', $imdbId)) {
            return 'tt' . $imdbId;
        }

        return '';
    }

    /**
     * @param array{kp?: int|string|null, token?: string|null, imdb?: string|null, tmdb?: int|string|null, world_art?: int|string|null}|int|string $identifiers
     * @return array{exists: bool, iframe?: string|null}
     */
    public function movieExists(array|int|string $identifiers): array
    {
        $query = [];

        if (!is_array($identifiers)) {
            $identifiers = ['kp' => $identifiers];
        }

        if (isset($identifiers['kp']) && $identifiers['kp'] !== '' && $identifiers['kp'] !== null) {
            $query['kp'] = $identifiers['kp'];
        }
        if (!empty($identifiers['token'])) {
            $query['token'] = trim((string) $identifiers['token']);
        }
        if (!empty($identifiers['imdb'])) {
            $query['imdb'] = trim((string) $identifiers['imdb']);
        }
        if (isset($identifiers['tmdb']) && $identifiers['tmdb'] !== '' && $identifiers['tmdb'] !== null) {
            $query['tmdb'] = $identifiers['tmdb'];
        }
        if (isset($identifiers['world_art']) && $identifiers['world_art'] !== '' && $identifiers['world_art'] !== null) {
            $query['world_art'] = $identifiers['world_art'];
        }

        if ($query === []) {
            return ['exists' => false];
        }

        $json = $this->getJson('/v2/movies/exists', $query);
        if ($json === []) {
            return ['exists' => false];
        }

        return [
            'exists' => (bool) ($json['exists'] ?? $json['data']['exists'] ?? false),
            'iframe' => $json['iframe'] ?? $json['data']['iframe'] ?? null,
        ];
    }

    /**
     * @return list<int|string>
     */
    public function catalogKpIds(): array
    {
        $json = $this->getJson('/v2/catalog/kp');
        $data = $json['data'] ?? $json;

        return is_array($data) ? array_values($data) : [];
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    public function translations(): array
    {
        return Cache::remember('alloha:meta:translations', 3600, function () {
            $json = $this->getJson('/v2/meta/translations');
            $data = $json['data'] ?? $json;

            return is_array($data) ? $data : [];
        });
    }

    /**
     * @param array<string,scalar|null> $query
     * @return array<string,mixed>
     */
    private function getJson(string $path, array $query = [], ?int $timeout = null): array
    {
        if (!$this->isConfigured()) {
            return [];
        }

        $url = $this->baseUrl . $path;
        $request = Http::timeout($timeout ?? $this->timeout)
            ->connectTimeout(5)
            ->withHeaders([
                'Authorization' => 'Bearer ' . $this->apiToken,
                'Accept' => 'application/json',
            ]);

        try {
            $res = $query === [] ? $request->get($url) : $request->get($url, $query);
        } catch (\Throwable) {
            return [];
        }

        if (!$res->ok()) {
            return [];
        }

        $json = $res->json();

        return is_array($json) ? $json : [];
    }
}
