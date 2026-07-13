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
        return $this->getJson('/v2/movies/kp/' . $kpId);
    }

    /**
     * @return array{exists: bool, iframe?: string|null}
     */
    public function movieExists(int|string $kpId): array
    {
        $json = $this->getJson('/v2/movies/exists', ['kp' => $kpId]);
        if ($json === []) {
            return ['exists' => false];
        }

        return [
            'exists' => (bool)($json['exists'] ?? $json['data']['exists'] ?? false),
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
    private function getJson(string $path, array $query = []): array
    {
        if (!$this->isConfigured()) {
            return [];
        }

        $url = $this->baseUrl . $path;
        $request = Http::timeout($this->timeout)
            ->withHeaders([
                'Authorization' => 'Bearer ' . $this->apiToken,
                'Accept' => 'application/json',
            ]);

        $res = $query === [] ? $request->get($url) : $request->get($url, $query);

        if (!$res->ok()) {
            return [];
        }

        $json = $res->json();

        return is_array($json) ? $json : [];
    }
}
