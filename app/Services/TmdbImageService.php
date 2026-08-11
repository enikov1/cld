<?php

namespace App\Services;

use App\Models\Series;

class TmdbImageService
{
    public function __construct(
        private readonly TmdbClient $client,
    ) {
    }

    /**
     * @return array{ok: bool, error?: string, target?: string, source?: string, media_type?: string, candidates?: list<array<string, mixed>>}
     */
    public function candidatesForSeries(Series $series, string $target, string $source = 'backdrops', ?int $seasonFilter = null): array
    {
        $target = strtolower(trim($target));
        if (!in_array($target, ['poster', 'gallery', 'brand'], true)) {
            return ['ok' => false, 'error' => 'Некорректный target (poster|gallery|brand)'];
        }

        $source = strtolower(trim($source));
        if (!in_array($source, ['backdrops', 'episodes'], true)) {
            return ['ok' => false, 'error' => 'Некорректный source (backdrops|episodes)'];
        }

        if ($source === 'episodes') {
            if ($target === 'poster') {
                return ['ok' => false, 'error' => 'Кадры серий недоступны для постера'];
            }

            return $this->episodeStillsForSeries($series, $seasonFilter);
        }

        if (!$this->client->isConfigured()) {
            return ['ok' => false, 'error' => 'TMDB API ключ не настроен'];
        }

        $tmdbId = trim((string)($series->tmdb_id ?? ''));
        if ($tmdbId === '') {
            return ['ok' => false, 'error' => 'У контента не указан TMDB ID'];
        }

        $isMovie = ($series->content_type ?? '') === 'film';
        $mediaType = $isMovie ? 'movie' : 'tv';

        $payload = $isMovie
            ? $this->client->getMovieImages($tmdbId)
            : $this->client->getTvImages($tmdbId);

        if ($payload === []) {
            return ['ok' => false, 'error' => 'Не удалось получить изображения из TMDB'];
        }

        $bucket = $target === 'poster' ? 'posters' : 'backdrops';
        $raw = is_array($payload[$bucket] ?? null) ? $payload[$bucket] : [];
        $candidates = $this->normalizeList($raw, $bucket);

        return [
            'ok' => true,
            'target' => $target,
            'source' => 'backdrops',
            'media_type' => $mediaType,
            'candidates' => $candidates,
        ];
    }

    /**
     * Episode stills from TMDB season details (TV only).
     *
     * @return array{ok: bool, error?: string, target?: string, source?: string, media_type?: string, seasons?: list<int>, candidates?: list<array<string, mixed>>}
     */
    public function episodeStillsForSeries(Series $series, ?int $seasonFilter = null): array
    {
        if (!$this->client->isConfigured()) {
            return ['ok' => false, 'error' => 'TMDB API ключ не настроен'];
        }

        if (($series->content_type ?? '') === 'film') {
            return ['ok' => false, 'error' => 'Кадры серий доступны только для сериалов (не фильмов)'];
        }

        $tmdbId = trim((string)($series->tmdb_id ?? ''));
        if ($tmdbId === '') {
            return ['ok' => false, 'error' => 'У контента не указан TMDB ID'];
        }

        $details = $this->client->getTvDetails($tmdbId);
        if ($details === []) {
            return ['ok' => false, 'error' => 'Не удалось получить данные сериала из TMDB'];
        }

        $seasonNumbers = $this->seasonNumbersFromDetails($details);
        if ($seasonFilter !== null && $seasonFilter >= 1) {
            $seasonNumbers = in_array($seasonFilter, $seasonNumbers, true) ? [$seasonFilter] : [];
        }

        if ($seasonNumbers === []) {
            return [
                'ok' => true,
                'source' => 'episodes',
                'media_type' => 'tv',
                'seasons' => $this->seasonNumbersFromDetails($details),
                'candidates' => [],
            ];
        }

        $candidates = [];
        foreach ($this->fetchSeasonPayloads($tmdbId, $seasonNumbers) as $seasonNumber => $payload) {
            foreach ($payload['episodes'] ?? [] as $episode) {
                if (!is_array($episode)) {
                    continue;
                }

                $filePath = trim((string)($episode['still_path'] ?? ''));
                if ($filePath === '' || !str_starts_with($filePath, '/')) {
                    continue;
                }

                $episodeNumber = (int)($episode['episode_number'] ?? 0);
                if ($episodeNumber < 1) {
                    continue;
                }

                $title = trim((string)($episode['name'] ?? ''));
                $candidates[] = [
                    'id' => 's' . $seasonNumber . 'e' . $episodeNumber . '-' . ltrim($filePath, '/'),
                    'kind' => 'stills',
                    'file_path' => $filePath,
                    'season_number' => $seasonNumber,
                    'episode_number' => $episodeNumber,
                    'episode_title' => $title !== '' ? $title : null,
                    'width' => isset($episode['width']) ? (int)$episode['width'] : null,
                    'height' => isset($episode['height']) ? (int)$episode['height'] : null,
                    'aspect_ratio' => null,
                    'iso_639_1' => null,
                    'vote_average' => isset($episode['vote_average']) ? (float)$episode['vote_average'] : 0.0,
                    'vote_count' => isset($episode['vote_count']) ? (int)$episode['vote_count'] : 0,
                    'preview_url' => $this->imageUrl($filePath, 'w500'),
                    'download_url' => $this->imageUrl($filePath, 'w780'),
                ];
            }
        }

        usort($candidates, static function (array $a, array $b): int {
            $sa = (int)($a['season_number'] ?? 0);
            $sb = (int)($b['season_number'] ?? 0);
            if ($sa !== $sb) {
                return $sb <=> $sa;
            }

            return ((int)($b['episode_number'] ?? 0)) <=> ((int)($a['episode_number'] ?? 0));
        });

        return [
            'ok' => true,
            'source' => 'episodes',
            'media_type' => 'tv',
            'seasons' => $this->seasonNumbersFromDetails($details),
            'candidates' => array_values($candidates),
        ];
    }

    /**
     * @param  array<string, mixed>  $details
     * @return list<int>
     */
    private function seasonNumbersFromDetails(array $details): array
    {
        $numbers = [];
        foreach ($details['seasons'] ?? [] as $season) {
            if (!is_array($season)) {
                continue;
            }
            $num = (int)($season['season_number'] ?? -1);
            if ($num >= 1) {
                $numbers[] = $num;
            }
        }

        $numbers = array_values(array_unique($numbers));
        sort($numbers);

        return $numbers;
    }

    /**
     * @param  list<int>  $seasonNumbers
     * @return array<int, array<string, mixed>>
     */
    private function fetchSeasonPayloads(string $tmdbId, array $seasonNumbers): array
    {
        $result = [];
        $chunks = array_chunk($seasonNumbers, 20);

        foreach ($chunks as $chunk) {
            $append = array_map(static fn (int $n) => 'season/' . $n, $chunk);
            $details = $this->client->getTvDetails($tmdbId, $append);

            foreach ($chunk as $seasonNumber) {
                $key = 'season/' . $seasonNumber;
                $payload = $details[$key] ?? null;

                if (!is_array($payload) || !isset($payload['episodes'])) {
                    $payload = $this->client->getTvSeasonDetails($tmdbId, $seasonNumber);
                }

                if (is_array($payload) && $payload !== []) {
                    $result[$seasonNumber] = $payload;
                }
            }
        }

        return $result;
    }

    private function imageUrl(string $filePath, string $size): string
    {
        $base = rtrim((string)config('tmdb.image_base_url', 'https://image.tmdb.org/t/p'), '/');

        return $base . '/' . $size . $filePath;
    }

    /**
     * @param  list<mixed>  $items
     * @return list<array<string, mixed>>
     */
    private function normalizeList(array $items, string $kind): array
    {
        $base = rtrim((string)config('tmdb.image_base_url', 'https://image.tmdb.org/t/p'), '/');
        $out = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $filePath = trim((string)($item['file_path'] ?? ''));
            if ($filePath === '' || !str_starts_with($filePath, '/')) {
                continue;
            }

            $lang = $item['iso_639_1'] ?? null;
            $lang = is_string($lang) && $lang !== '' ? $lang : null;

            // Avoid /original — backdrops often exceed upload limits (5–15MB+).
            $downloadSize = $kind === 'posters' ? 'w780' : 'w1280';

            $out[] = [
                'id' => ltrim($filePath, '/'),
                'kind' => $kind,
                'file_path' => $filePath,
                'width' => isset($item['width']) ? (int)$item['width'] : null,
                'height' => isset($item['height']) ? (int)$item['height'] : null,
                'aspect_ratio' => isset($item['aspect_ratio']) ? (float)$item['aspect_ratio'] : null,
                'iso_639_1' => $lang,
                'vote_average' => isset($item['vote_average']) ? (float)$item['vote_average'] : 0.0,
                'vote_count' => isset($item['vote_count']) ? (int)$item['vote_count'] : 0,
                'preview_url' => $base . '/w500' . $filePath,
                'download_url' => $base . '/' . $downloadSize . $filePath,
            ];
        }

        usort($out, static function (array $a, array $b): int {
            $va = (float)($a['vote_average'] ?? 0);
            $vb = (float)($b['vote_average'] ?? 0);
            if ($va !== $vb) {
                return $vb <=> $va;
            }

            return ((int)($b['vote_count'] ?? 0)) <=> ((int)($a['vote_count'] ?? 0));
        });

        return array_values($out);
    }
}
