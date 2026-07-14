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
     * @return array<string,mixed>
     */
    public function getMovieDetails(int|string $tmdbId): array
    {
        return $this->getJson('/movie/' . rawurlencode((string)$tmdbId));
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

        $response = Http::timeout($this->timeout)
            ->acceptJson()
            ->get($this->baseUrl . $path, array_merge([
                'api_key' => $this->apiKey,
                'language' => $this->language,
            ], $query));

        if (!$response->ok()) {
            return [];
        }

        $json = $response->json();

        return is_array($json) ? $json : [];
    }
}
