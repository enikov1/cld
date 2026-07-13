<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class KinoPoiskClient
{
    private string $apiKey;
    private string $baseUrl;
    private int $timeout;

    public function __construct()
    {
        $this->apiKey = KinoPoiskConfig::apiKey();
        $this->baseUrl = (string)config('kinopoisk.base_url', 'https://kinopoiskapiunofficial.tech/api');
        $this->timeout = (int)config('kinopoisk.request_timeout', 20);
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    /**
     * Search films by keyword (unofficial KinoPoisk API).
     *
     * @return array<int,array<string,mixed>>
     */
    public function searchByKeyword(string $keyword, int $limit = 20): array
    {
        $keyword = trim($keyword);
        if ($keyword === '') {
            return [];
        }

        $res = Http::timeout($this->timeout)
            ->withHeaders([
                'X-API-KEY' => $this->apiKey,
            ])
            ->get($this->baseUrl . '/v2.1/films/search-by-keyword', [
                'keyword' => $keyword,
            ]);

        if (!$res->ok()) {
            return [];
        }

        $json = $res->json();
        $films = $json['films'] ?? $json['items'] ?? [];
        if (!is_array($films)) {
            return [];
        }

        // Some responses include more fields than needed.
        return array_slice($films, 0, max(0, $limit));
    }

    /**
     * Get film details by KinoPoisk ID.
     *
     * @return array<string,mixed>
     */
    public function getFilm(int|string $id): array
    {
        $res = Http::timeout($this->timeout)
            ->withHeaders([
                'X-API-KEY' => $this->apiKey,
            ])
            ->get($this->baseUrl . '/v2.2/films/' . $id);

        if (!$res->ok()) {
            return [];
        }

        $json = $res->json();
        if (!is_array($json)) {
            return [];
        }

        return $json;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getDistributions(int|string $filmId): array
    {
        $res = Http::timeout($this->timeout)
            ->withHeaders([
                'X-API-KEY' => $this->apiKey,
            ])
            ->get($this->baseUrl . '/v2.2/films/' . $filmId . '/distributions');

        if (!$res->ok()) {
            return [];
        }

        $json = $res->json();
        if (!is_array($json)) {
            return [];
        }

        $items = $json['items'] ?? [];
        if (!is_array($items)) {
            return [];
        }

        return $items;
    }

    /**
     * Get film staff (actors, directors, etc.) by KinoPoisk ID.
     *
     * @return array<int,array<string,mixed>>
     */
    public function getStaff(int|string $filmId): array
    {
        $res = Http::timeout($this->timeout)
            ->withHeaders([
                'X-API-KEY' => $this->apiKey,
            ])
            ->get($this->baseUrl . '/v1/staff', [
                'filmId' => $filmId,
            ]);

        if (!$res->ok()) {
            return [];
        }

        $json = $res->json();
        if (!is_array($json)) {
            return [];
        }

        return $json;
    }

    public static function toSeriesSlug(string $title, ?string $suffix = null): string
    {
        $slug = Str::slug($title);
        if ($suffix) {
            $slug .= '-' . Str::slug($suffix);
        }
        return $slug;
    }
}

