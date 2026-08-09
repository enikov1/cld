<?php

namespace App\Services;

use App\Models\PlayerSource;
use App\Models\Series;
use App\Support\PlayerUrlHelper;
use Illuminate\Support\Facades\Http;

class RutubeTrailerService
{
    public const SOURCE_KEY = 'rutube_trailer';

    private int $timeout = 20;

    /**
     * Convert a Rutube page / private / shorts URL (or raw video id) into an embed URL.
     */
    public static function toEmbedUrl(?string $input): string
    {
        if ($input === null) {
            return '';
        }

        $input = trim($input);
        if ($input === '') {
            return '';
        }

        if (preg_match('/^[a-f0-9]{32}$/i', $input) || preg_match('/^\d{5,12}$/', $input)) {
            return 'https://rutube.ru/play/embed/' . $input;
        }

        if (!preg_match('#^https?://#i', $input)) {
            return '';
        }

        $parts = parse_url($input);
        if (!is_array($parts) || empty($parts['host'])) {
            return '';
        }

        $host = strtolower((string) $parts['host']);
        if (!str_ends_with($host, 'rutube.ru')) {
            return '';
        }

        $path = (string) ($parts['path'] ?? '');
        $query = [];
        if (!empty($parts['query'])) {
            parse_str((string) $parts['query'], $query);
        }

        $id = '';
        if (preg_match('#/(?:play/)?embed/([a-zA-Z0-9]+)(?:/|$)#', $path, $m)) {
            $id = $m[1];
        } elseif (preg_match('#/video/(?:private/)?([a-zA-Z0-9]+)(?:/|$)#', $path, $m)) {
            $id = $m[1];
        } elseif (preg_match('#/shorts/([a-zA-Z0-9]+)(?:/|$)#', $path, $m)) {
            $id = $m[1];
        }

        if ($id === '') {
            return '';
        }

        $embed = 'https://rutube.ru/play/embed/' . $id;
        $accessKey = trim((string) ($query['p'] ?? ''));
        if ($accessKey !== '') {
            $embed .= '?p=' . rawurlencode($accessKey);
        }

        return $embed;
    }

    /**
     * @param 'skip'|'update' $existingMode
     * @return array{ok: bool, skipped?: bool, error?: string, trailer?: array{id: string, title: string, embed_url: string, video_url: string}}
     */
    public function addToSeries(Series $series, string $tabName = 'Трейлер', string $existingMode = 'update'): array
    {
        $tabName = trim($tabName) !== '' ? trim($tabName) : 'Трейлер';
        $existingMode = $existingMode === 'skip' ? 'skip' : 'update';

        $existing = PlayerSource::query()
            ->where('series_id', $series->id)
            ->where('source_key', self::SOURCE_KEY)
            ->first();

        if ($existing && $existingMode === 'skip') {
            return ['ok' => true, 'skipped' => true];
        }

        $found = $this->findBestTrailer($series);
        if ($found === null) {
            return ['ok' => false, 'error' => 'Трейлер на Rutube не найден по названию сериала'];
        }

        $embed = PlayerUrlHelper::normalizePlayerContent($found['embed_url']);
        if ($embed === '') {
            return ['ok' => false, 'error' => 'Некорректный embed URL Rutube'];
        }

        $activeCount = PlayerSource::query()
            ->where('series_id', $series->id)
            ->where('is_active', true)
            ->count();

        // Always place trailer as the last active tab.
        $position = ($existing?->is_active)
            ? max(1, $activeCount)
            : max(1, $activeCount + 1);

        $payload = [
            'provider' => $tabName,
            'iframe_url' => $embed,
            'is_active' => true,
            'source_key' => self::SOURCE_KEY,
        ];

        if ($existing) {
            $existing->update($payload);
            $playerId = (int) $existing->id;
        } else {
            $created = PlayerSource::query()->create(array_merge($payload, [
                'series_id' => $series->id,
                'priority' => 0,
            ]));
            $playerId = (int) $created->id;
        }

        PlayerUrlHelper::applyPlayerTabPosition($series, $playerId, $position);
        $series->update(['player_url' => PlayerUrlHelper::firstIframeUrlForSeries($series->refresh())]);

        return [
            'ok' => true,
            'skipped' => false,
            'trailer' => $found,
        ];
    }

    /**
     * @return array{id: string, title: string, embed_url: string, video_url: string}|null
     */
    public function findBestTrailer(Series $series): ?array
    {
        $title = trim((string) $series->title);
        if ($title === '') {
            return null;
        }

        $year = (int) ($series->year ?: $series->start_year ?: 0);
        $query = $title . ' трейлер';
        if ($year >= 1900 && $year <= 2100) {
            $query .= ' ' . $year;
        }

        $results = $this->search($query, 12);
        if ($results === []) {
            $results = $this->search($title . ' трейлер', 12);
        }

        if ($results === []) {
            return null;
        }

        $best = null;
        $bestScore = PHP_INT_MIN;

        foreach ($results as $item) {
            $score = $this->scoreResult($item, $title, $year > 0 ? $year : null);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $item;
            }
        }

        if ($best === null || $bestScore < 40) {
            return null;
        }

        $embed = self::toEmbedUrl((string) ($best['embed_url'] ?? $best['id'] ?? ''));
        if ($embed === '') {
            return null;
        }

        return [
            'id' => (string) ($best['id'] ?? ''),
            'title' => (string) ($best['title'] ?? ''),
            'embed_url' => $embed,
            'video_url' => (string) ($best['video_url'] ?? ''),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function search(string $query, int $limit = 10): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $res = Http::timeout($this->timeout)
            ->withHeaders([
                'User-Agent' => 'LordSerialBot/1.0',
                'Accept' => 'application/json',
            ])
            ->get('https://rutube.ru/api/search/video/', [
                'query' => $query,
                'limit' => max(1, min(30, $limit)),
            ]);

        if (!$res->ok()) {
            return [];
        }

        $json = $res->json();
        $results = $json['results'] ?? [];
        if (!is_array($results)) {
            return [];
        }

        $out = [];
        foreach ($results as $row) {
            if (!is_array($row)) {
                continue;
            }
            if (!empty($row['is_deleted']) || !empty($row['is_hidden']) || !empty($row['is_locked'])) {
                continue;
            }
            $out[] = $row;
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function scoreResult(array $item, string $seriesTitle, ?int $year): int
    {
        $title = mb_strtolower(trim((string) ($item['title'] ?? '')));
        $needle = mb_strtolower(trim($seriesTitle));
        $score = 0;

        if ($needle !== '' && str_contains($title, $needle)) {
            $score += 40;
        }

        if (preg_match('/трейлер|trailer|тизер|teaser/ui', $title)) {
            $score += 50;
        } else {
            $score -= 20;
        }

        if ($year !== null && preg_match('/\b' . preg_quote((string) $year, '/') . '\b/', $title)) {
            $score += 20;
        }

        $duration = (int) ($item['duration'] ?? 0);
        if ($duration >= 20 && $duration <= 300) {
            $score += 20;
        } elseif ($duration > 900) {
            $score -= 50;
        } elseif ($duration > 480) {
            $score -= 25;
        }

        if (!empty($item['is_official'])) {
            $score += 10;
        }

        $hits = (int) ($item['hits'] ?? 0);
        if ($hits > 0) {
            $score += min(15, (int) floor(log10($hits + 1) * 5));
        }

        return $score;
    }
}
