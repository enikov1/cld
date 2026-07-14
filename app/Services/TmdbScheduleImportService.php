<?php

namespace App\Services;

use App\Models\Episode;
use App\Models\Series;
use Carbon\Carbon;

class TmdbScheduleImportService
{
    private const APPEND_BATCH_SIZE = 20;

    public function __construct(
        private readonly TmdbClient $client,
    ) {
    }

    /**
     * Fetch schedule from TMDB and map to admin schedule payload shape.
     *
     * @return array{
     *     seasons: list<array{season_number: int, title: string|null, episodes: list<array<string, mixed>>}>,
     *     broadcast_status: string|null,
     *     meta: array{
     *         tmdb_id: string,
     *         tmdb_status: string|null,
     *         broadcast_status_mapped: string|null,
     *         seasons_count: int,
     *         episodes_count: int,
     *         skipped_specials: bool
     *     }
     * }
     */
    public function fetchForSeries(Series $series, ?array $prefetchedDetails = null): array
    {
        if (!$this->client->isConfigured()) {
            throw new \RuntimeException('API-ключ TMDB не настроен');
        }

        $tmdbId = trim((string)$series->tmdb_id);
        if ($tmdbId === '') {
            throw new \RuntimeException('У сериала не указан TMDB ID');
        }

        $details = is_array($prefetchedDetails) && $prefetchedDetails !== []
            ? $prefetchedDetails
            : $this->client->getTvDetails($tmdbId);

        if ($details === []) {
            throw new \RuntimeException('Не удалось получить данные сериала из TMDB');
        }

        $seasonNumbers = $this->seasonNumbersFromDetails($details);
        $seasonPayloads = $this->fetchSeasonPayloads($tmdbId, $seasonNumbers);

        $today = Carbon::today()->toDateString();
        $seasons = [];
        $episodesCount = 0;
        $skippedSpecials = false;

        foreach ($seasonNumbers as $seasonNumber) {
            if ($seasonNumber < 1) {
                $skippedSpecials = true;
                continue;
            }

            $payload = $seasonPayloads[$seasonNumber] ?? [];
            if ($payload === []) {
                continue;
            }

            $mapped = $this->mapSeasonPayload($payload, $seasonNumber, $today);
            if ($mapped['episodes'] === []) {
                continue;
            }

            $seasons[] = $mapped;
            $episodesCount += count($mapped['episodes']);
        }

        if ($seasons === []) {
            throw new \RuntimeException('В TMDB нет сезонов с сериями для этого ID');
        }

        $mappedBroadcast = TmdbBroadcastStatusMapper::fromDetails($details, 'series');

        return [
            'seasons' => $seasons,
            'broadcast_status' => TmdbBroadcastStatusMapper::resolve(
                $series->broadcast_status,
                $mappedBroadcast,
            ),
            'meta' => [
                'tmdb_id' => $tmdbId,
                'tmdb_status' => isset($details['status']) ? (string)$details['status'] : null,
                'broadcast_status_mapped' => $mappedBroadcast,
                'seasons_count' => count($seasons),
                'episodes_count' => $episodesCount,
                'skipped_specials' => $skippedSpecials,
            ],
        ];
    }

    /**
     * Fetch TMDB schedule, merge with local (preserve voices), and persist.
     *
     * @param  array<string, mixed>|null  $prefetchedDetails
     * @return array{ok: bool, seasons_count: int, episodes_count: int, error?: string}
     */
    public function syncMergedToDatabase(Series $series, ?array $prefetchedDetails = null): array
    {
        try {
            $imported = $this->fetchForSeries($series, $prefetchedDetails);
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'seasons_count' => 0,
                'episodes_count' => 0,
                'error' => $e->getMessage(),
            ];
        }

        $existing = EpisodeProgressService::scheduleForSeries($series);
        $merged = $this->mergeSchedules($existing, $imported['seasons']);
        SeriesScheduleWriter::replace($series, $merged);

        return [
            'ok' => true,
            'seasons_count' => (int)($imported['meta']['seasons_count'] ?? 0),
            'episodes_count' => (int)($imported['meta']['episodes_count'] ?? 0),
        ];
    }

    /**
     * Merge imported seasons into existing schedule. Preserves local voice and custom episodes.
     *
     * @param  list<array{season_number: int, title?: string|null, episodes?: list<array<string, mixed>>}>  $existing
     * @param  list<array{season_number: int, title?: string|null, episodes?: list<array<string, mixed>>}>  $imported
     * @return list<array{season_number: int, title: string|null, episodes: list<array<string, mixed>>}>
     */
    public function mergeSchedules(array $existing, array $imported): array
    {
        $bySeason = [];

        foreach ($existing as $season) {
            $num = (int)($season['season_number'] ?? 0);
            if ($num < 1) {
                continue;
            }
            $bySeason[$num] = [
                'season_number' => $num,
                'title' => $season['title'] ?? null,
                'episodes' => [],
            ];
            foreach ($season['episodes'] ?? [] as $ep) {
                $epNum = (int)($ep['episode_number'] ?? 0);
                if ($epNum < 1) {
                    continue;
                }
                $bySeason[$num]['episodes'][$epNum] = $this->normalizeEpisode($ep);
            }
        }

        foreach ($imported as $season) {
            $num = (int)($season['season_number'] ?? 0);
            if ($num < 1) {
                continue;
            }

            if (!isset($bySeason[$num])) {
                $bySeason[$num] = [
                    'season_number' => $num,
                    'title' => $season['title'] ?? null,
                    'episodes' => [],
                ];
            } elseif (!empty($season['title'])) {
                $bySeason[$num]['title'] = $season['title'];
            }

            foreach ($season['episodes'] ?? [] as $ep) {
                $epNum = (int)($ep['episode_number'] ?? 0);
                if ($epNum < 1) {
                    continue;
                }

                $incoming = $this->normalizeEpisode($ep);
                $current = $bySeason[$num]['episodes'][$epNum] ?? null;

                if ($current === null) {
                    $bySeason[$num]['episodes'][$epNum] = $incoming;
                    continue;
                }

                $bySeason[$num]['episodes'][$epNum] = [
                    'episode_number' => $epNum,
                    'title' => $incoming['title'] ?: $current['title'],
                    'release_at' => $incoming['release_at'] ?? $current['release_at'],
                    'status' => $incoming['status'] ?? $current['status'],
                    'voice' => $current['voice'] ?? $incoming['voice'],
                ];
            }
        }

        ksort($bySeason);

        return array_values(array_map(function (array $season) {
            ksort($season['episodes']);

            return [
                'season_number' => $season['season_number'],
                'title' => $season['title'],
                'episodes' => array_values($season['episodes']),
            ];
        }, $bySeason));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{season_number: int, title: string|null, episodes: list<array<string, mixed>>}
     */
    public function mapSeasonPayload(array $payload, int $seasonNumber, ?string $today = null): array
    {
        $today ??= Carbon::today()->toDateString();
        $episodes = [];

        foreach ($payload['episodes'] ?? [] as $ep) {
            if (!is_array($ep)) {
                continue;
            }
            $mapped = $this->mapEpisode($ep, $today);
            if ($mapped === null) {
                continue;
            }
            $episodes[] = $mapped;
        }

        usort($episodes, fn (array $a, array $b) => $a['episode_number'] <=> $b['episode_number']);

        return [
            'season_number' => $seasonNumber,
            'title' => $this->seasonTitle($payload, $seasonNumber),
            'episodes' => $episodes,
        ];
    }

    /**
     * @param  array<string, mixed>  $episode
     * @return array{episode_number: int, title: string|null, release_at: string|null, status: string, voice: null}|null
     */
    public function mapEpisode(array $episode, ?string $today = null): ?array
    {
        $today ??= Carbon::today()->toDateString();
        $number = (int)($episode['episode_number'] ?? 0);
        if ($number < 1) {
            return null;
        }

        $airDate = $this->normalizeDate($episode['air_date'] ?? null);
        $name = trim((string)($episode['name'] ?? ''));

        return [
            'episode_number' => $number,
            'title' => $name !== '' ? $name : null,
            'release_at' => $airDate,
            'status' => $this->statusFromAirDate($airDate, $today),
            'voice' => null,
        ];
    }

    public function statusFromAirDate(?string $airDate, ?string $today = null): string
    {
        $today ??= Carbon::today()->toDateString();

        if ($airDate === null || $airDate === '') {
            return Episode::STATUS_SCHEDULED;
        }

        return $airDate <= $today
            ? Episode::STATUS_RELEASED
            : Episode::STATUS_SCHEDULED;
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
        $chunks = array_chunk($seasonNumbers, self::APPEND_BATCH_SIZE);

        foreach ($chunks as $chunk) {
            $append = array_map(fn (int $n) => 'season/' . $n, $chunk);
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

    /**
     * @param  array<string, mixed>  $payload
     */
    private function seasonTitle(array $payload, int $seasonNumber): ?string
    {
        $name = trim((string)($payload['name'] ?? ''));
        if ($name === '') {
            return 'Сезон ' . $seasonNumber;
        }

        // Drop generic English defaults like "Season 1"
        if (preg_match('/^Season\s+\d+$/i', $name) === 1) {
            return 'Сезон ' . $seasonNumber;
        }

        return $name;
    }

    private function normalizeDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $raw = trim((string)$value);
        if ($raw === '' || $raw === '0000-00-00') {
            return null;
        }

        try {
            return Carbon::parse($raw)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $ep
     * @return array{episode_number: int, title: string|null, release_at: string|null, status: string, voice: string|null}
     */
    private function normalizeEpisode(array $ep): array
    {
        $releaseAt = $ep['release_at'] ?? $ep['release_at_iso'] ?? null;
        if (is_string($releaseAt) && $releaseAt !== '') {
            $releaseAt = $this->normalizeDate($releaseAt);
        } else {
            $releaseAt = null;
        }

        $status = (string)($ep['status'] ?? Episode::STATUS_SCHEDULED);
        if (!in_array($status, [Episode::STATUS_RELEASED, Episode::STATUS_SCHEDULED], true)) {
            $status = Episode::STATUS_SCHEDULED;
        }

        $voice = $ep['voice'] ?? null;
        if (is_string($voice)) {
            $voice = trim($voice);
            $voice = $voice === '' ? null : $voice;
        } else {
            $voice = null;
        }

        $title = $ep['title'] ?? null;
        if (is_string($title)) {
            $title = trim($title);
            $title = $title === '' ? null : $title;
        } else {
            $title = null;
        }

        return [
            'episode_number' => (int)$ep['episode_number'],
            'title' => $title,
            'release_at' => $releaseAt,
            'status' => $status,
            'voice' => $voice,
        ];
    }
}
