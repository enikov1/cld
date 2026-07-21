<?php

namespace App\Support;

use App\Models\PlayerSource;
use App\Models\Series;

class PlayerUrlHelper
{
    /** @var list<string> */
    private const TRUSTED_SCRIPT_HOSTS = [
        'player.cdnvideohub.com',
    ];

    public static function sanitize(?string $url): string
    {
        if (!$url) {
            return '';
        }

        $url = trim($url);

        if (!preg_match('#^https?://#i', $url)) {
            return '';
        }

        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['host'])) {
            return '';
        }

        return $url;
    }

    public static function isEmbedHtml(?string $content): bool
    {
        if (!$content) {
            return false;
        }

        $content = trim($content);

        // Allow iframe / custom video-player embeds only — never raw leading <script>/<div>.
        return (bool) preg_match('/^<(?:video-player|iframe)\b/i', $content);
    }

    public static function normalizePlayerContent(?string $content): string
    {
        if (!$content) {
            return '';
        }

        $content = trim($content);
        if ($content === '') {
            return '';
        }

        if (self::isEmbedHtml($content)) {
            if (self::hasDisallowedScript($content)) {
                return '';
            }

            return $content;
        }

        return self::sanitize($content);
    }

    /**
     * Place a player at the Nth tab (1 = leftmost) and renumber all active players.
     */
    public static function applyPlayerTabPosition(Series $series, int $playerSourceId, int $position): void
    {
        $position = max(1, $position);

        $players = PlayerSource::query()
            ->where('series_id', $series->id)
            ->where('is_active', true)
            ->orderByDesc('priority')
            ->orderBy('id')
            ->get();

        $target = null;
        $others = [];

        foreach ($players as $player) {
            if ((int) $player->id === $playerSourceId) {
                $target = $player;
                continue;
            }

            $others[] = $player;
        }

        if ($target === null) {
            return;
        }

        $insertAt = min($position - 1, count($others));
        $ordered = array_merge(
            array_slice($others, 0, $insertAt),
            [$target],
            array_slice($others, $insertAt),
        );

        $total = count($ordered);
        foreach ($ordered as $index => $player) {
            $newPriority = ($total - $index) * 10;
            if ((int) $player->priority !== $newPriority) {
                $player->update(['priority' => $newPriority]);
            }
        }
    }

    private static function hasDisallowedScript(string $content): bool
    {
        if (!preg_match_all('/<script\b([^>]*)>(.*?)<\/script>/is', $content, $matches, PREG_SET_ORDER)) {
            return false;
        }

        foreach ($matches as $match) {
            $attrs = $match[1];
            $body = trim($match[2]);

            if ($body !== '') {
                return true;
            }

            if (!preg_match('/\bsrc\s*=\s*(["\'])(https?:\/\/[^"\']+)\1/i', $attrs, $srcMatch)) {
                return true;
            }

            $host = strtolower((string) parse_url($srcMatch[2], PHP_URL_HOST));
            if (!in_array($host, self::TRUSTED_SCRIPT_HOSTS, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array{id: int|null, label: string, url: string, html: string, is_embed: bool, index: int, is_first: bool}>
     */
    public static function activePlayersForSeries(\App\Models\Series $series): array
    {
        $sources = $series->playerSources()
            ->where('is_active', true)
            ->orderByDesc('priority')
            ->orderBy('id')
            ->get();

        if ($sources->isEmpty()) {
            $fallback = self::normalizePlayerContent($series->player_url);
            if ($fallback === '') {
                return [];
            }

            $isEmbed = self::isEmbedHtml($fallback);

            return [[
                'id' => null,
                'label' => 'Смотреть онлайн',
                'url' => $isEmbed ? '' : $fallback,
                'html' => $isEmbed ? $fallback : '',
                'is_embed' => $isEmbed,
                'index' => 0,
                'is_first' => true,
            ]];
        }

        $players = [];
        $index = 0;

        foreach ($sources as $source) {
            $content = self::normalizePlayerContent($source->iframe_url);
            if ($content === '') {
                continue;
            }

            $isEmbed = self::isEmbedHtml($content);

            $label = trim((string) $source->provider);
            if ($label === '') {
                $label = 'Плеер ' . ($index + 1);
            }

            $players[] = [
                'id' => $source->id,
                'label' => $label,
                'url' => $isEmbed ? '' : $content,
                'html' => $isEmbed ? $content : '',
                'is_embed' => $isEmbed,
                'index' => $index,
                'is_first' => $index === 0,
            ];
            $index++;
        }

        return $players;
    }

    public static function firstIframeUrlForSeries(\App\Models\Series $series): ?string
    {
        foreach (self::activePlayersForSeries($series) as $player) {
            if (!($player['is_embed'] ?? false) && !empty($player['url'])) {
                return $player['url'];
            }
        }

        return null;
    }
}
