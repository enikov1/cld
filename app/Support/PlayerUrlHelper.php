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

    /**
     * @return list<array{id: int|null, label: string, url: string, index: int, is_first: bool}>
     */
    public static function activePlayersForSeries(\App\Models\Series $series): array
    {
        $sources = $series->playerSources()
            ->where('is_active', true)
            ->orderByDesc('priority')
            ->orderBy('id')
            ->get();

        if ($sources->isEmpty()) {
            $fallback = self::sanitize($series->player_url);
            if ($fallback === '') {
                return [];
            }

            return [[
                'id' => null,
                'label' => 'Смотреть онлайн',
                'url' => $fallback,
                'index' => 0,
                'is_first' => true,
            ]];
        }

        $players = [];
        $index = 0;

        foreach ($sources as $source) {
            $url = self::sanitize($source->iframe_url);
            if ($url === '') {
                continue;
            }

            $label = trim((string)$source->provider);
            if ($label === '') {
                $label = 'Плеер ' . ($index + 1);
            }

            $players[] = [
                'id' => $source->id,
                'label' => $label,
                'url' => $url,
                'index' => $index,
                'is_first' => $index === 0,
            ];
            $index++;
        }

        return $players;
    }
}
