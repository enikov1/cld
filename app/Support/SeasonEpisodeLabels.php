<?php

namespace App\Support;

/**
 * Готовые подписи сезонов/серий для шаблонов {season-type-N} и {episode-type-N}.
 *
 * type-1 — только текущий: «5 сезон»
 * type-2 — список через запятую: «1, 2, 3, 4, 5 сезон»
 * type-3 — диапазон: «1-5 сезон»
 * type-4 — диапазон с текущим отдельно: «1-4, 5 сезон»
 * type-5 — диапазон с «по»: «1 по 5 сезон»
 */
class SeasonEpisodeLabels
{
    private const MAX_TYPE = 5;

    /**
     * @return array<string, string> keys like season-type-1, episode-type-1
     */
    public static function forSeries(?int $seasonNumber, ?int $episodeNumber): array
    {
        $out = [];

        for ($type = 1; $type <= self::MAX_TYPE; $type++) {
            $out['season-type-' . $type] = self::formatSeason($seasonNumber, $type);
            $out['episode-type-' . $type] = self::formatEpisode($episodeNumber, $type);
        }

        return $out;
    }

    public static function formatSeason(?int $number, int $type): string
    {
        if (!$number || $number < 1) {
            return '';
        }

        return self::formatNumberSet($number, $type, 'сезон');
    }

    public static function formatEpisode(?int $number, int $type): string
    {
        if (!$number || $number < 1) {
            return '';
        }

        return self::formatNumberSet($number, $type, 'серия');
    }

    private static function formatNumberSet(int $number, int $type, string $suffix): string
    {
        return match ($type) {
            1 => $number . ' ' . $suffix,
            2 => self::commaList($number) . ' ' . $suffix,
            3 => '1-' . $number . ' ' . $suffix,
            4 => $number === 1
                ? '1 ' . $suffix
                : ($number === 2
                    ? '1-2 ' . $suffix
                    : '1-' . ($number - 1) . ', ' . $number . ' ' . $suffix),
            5 => '1 по ' . $number . ' ' . $suffix,
            default => '',
        };
    }

    private static function commaList(int $max): string
    {
        $parts = [];
        for ($i = 1; $i <= $max; $i++) {
            $parts[] = (string)$i;
        }

        return implode(', ', $parts);
    }
}
