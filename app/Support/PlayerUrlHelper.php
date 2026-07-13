<?php

namespace App\Support;

class PlayerUrlHelper
{
    public static function sanitize(?string $url): string
    {
        if (!$url) {
            return '';
        }

        $url = trim($url);

        if (!preg_match('#^https?://#i', $url)) {
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

        return (bool)preg_match('/^<(?:video-player|script|div|iframe)/i', $content);
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
            return $content;
        }

        return self::sanitize($content);
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

            $label = trim((string)$source->provider);
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
