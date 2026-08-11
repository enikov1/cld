<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class TmdbClient
{
    private string $apiKey;
    private string $baseUrl;
    private int $timeout;
    private string $language;

    public function __construct()
    {
        $this->apiKey = TmdbConfig::apiKey();
        $this->baseUrl = rtrim((string)config('tmdb.base_url', 'https://api.themoviedb.org/3'), '/');
        $this->timeout = (int)config('tmdb.request_timeout', 20);
        $this->language = (string)config('tmdb.language', 'ru-RU');
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    /**
     * @param  list<string>  $append
     * @return array<string,mixed>
     */
    public function getTvDetails(int|string $tmdbId, array $append = []): array
    {
        $query = [];
        if ($append !== []) {
            $query['append_to_response'] = implode(',', $append);
        }

        return $this->getJson('/tv/' . rawurlencode((string)$tmdbId), $query);
    }

    /**
     * @return array<string,mixed>
     */
    public function getTvSeasonDetails(int|string $tmdbId, int $seasonNumber): array
    {
        return $this->getJson(
            '/tv/' . rawurlencode((string)$tmdbId) . '/season/' . $seasonNumber
        );
    }

    /**
     * @param  list<string>  $append
     * @return array<string,mixed>
     */
    public function getMovieDetails(int|string $tmdbId, array $append = []): array
    {
        $query = [];
        if ($append !== []) {
            $query['append_to_response'] = implode(',', $append);
        }

        return $this->getJson('/movie/' . rawurlencode((string)$tmdbId), $query);
    }

    /**
     * @return array<string,mixed>
     */
    public function getMovieImages(int|string $tmdbId): array
    {
        return $this->getJson('/movie/' . rawurlencode((string)$tmdbId) . '/images', [
            'include_image_language' => 'ru,en,null',
            // Images endpoint ignores language filter for listing when include_image_language is set;
            // omit default language so we get the full filtered set.
            'language' => '',
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    public function getTvImages(int|string $tmdbId): array
    {
        return $this->getJson('/tv/' . rawurlencode((string)$tmdbId) . '/images', [
            'include_image_language' => 'ru,en,null',
            'language' => '',
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    public function searchMulti(string $query, int $page = 1): array
    {
        return $this->getJson('/search/multi', [
            'query' => $query,
            'include_adult' => 'true',
            'page' => max(1, $page),
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    public function getGenreList(string $type): array
    {
        $type = $type === 'tv' ? 'tv' : 'movie';

        return $this->getJson('/genre/' . $type . '/list');
    }

    /**
     * @return array<string,mixed>
     */
    public function getNetworkDetails(int|string $networkId): array
    {
        return $this->getJson('/network/' . rawurlencode((string)$networkId));
    }

    /**
     * @return array<string,mixed>
     */
    public function getCompanyDetails(int|string $companyId): array
    {
        return $this->getJson('/company/' . rawurlencode((string)$companyId));
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string,mixed>
     */
    private function getJson(string $path, array $query = []): array
    {
        if (!$this->isConfigured()) {
            return [];
        }

        $query = array_merge([
            'api_key' => $this->apiKey,
            'language' => $this->language,
        ], $query);

        // Allow callers to omit language (e.g. /images with include_image_language).
        $query = array_filter(
            $query,
            static fn ($value) => $value !== null && $value !== '',
        );

        $response = Http::timeout($this->timeout)
            ->acceptJson()
            ->get($this->baseUrl . $path, $query);

        if (!$response->ok()) {
            return [];
        }

        $json = $response->json();

        return is_array($json) ? $json : [];
    }
}
