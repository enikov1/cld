<?php

namespace App\Support;

use App\Models\Series;

class SeriesItemResolver
{
    /**
     * Resolve a series from admin collection/studio item payload.
     *
     * @param array{series_id?: int|string|null, kp_id?: string|null, tmdb_id?: string|null} $item
     */
    public static function fromItem(array $item): ?Series
    {
        if (!empty($item['series_id'])) {
            return Series::query()->find((int) $item['series_id']);
        }

        $kpId = trim((string) ($item['kp_id'] ?? ''));
        if ($kpId !== '') {
            $series = Series::query()->where('kp_id', $kpId)->first();
            if ($series) {
                return $series;
            }
        }

        $tmdbId = trim((string) ($item['tmdb_id'] ?? ''));
        if ($tmdbId !== '') {
            return Series::query()->where('tmdb_id', $tmdbId)->first();
        }

        return null;
    }
}
