<?php

namespace App\Services;

use App\Models\Series;

class TmdbBroadcastStatusMapper
{
    /**
     * Map TMDB TV/movie details to local broadcast_status.
     * Returns null when status cannot be determined (leave existing value).
     *
     * @param  array<string, mixed>  $details
     */
    public static function fromDetails(array $details, ?string $contentType = null): ?string
    {
        $status = trim((string)($details['status'] ?? ''));
        if ($status === '') {
            return null;
        }

        $normalized = strtolower($status);

        // Movie statuses
        if (in_array($normalized, ['released', 'canceled', 'cancelled'], true)) {
            return 'completed';
        }
        if (in_array($normalized, ['rumored', 'planned', 'in production', 'post production'], true)) {
            // Films still in pipeline are not "ongoing series"; treat as completed only when released.
            // For films leave null unless clearly released/canceled above.
            if ($contentType === 'film') {
                return null;
            }
        }

        // TV statuses
        return match ($normalized) {
            'ended', 'canceled', 'cancelled' => 'completed',
            'returning series', 'planned', 'in production', 'pilot' => 'ongoing',
            default => null,
        };
    }

    /**
     * Decide whether to apply mapped status over the current one.
     * Manual "paused" is kept while TMDB still reports an ongoing-type status.
     */
    public static function resolve(?string $current, ?string $mapped): ?string
    {
        if ($mapped === null) {
            return $current;
        }

        if ($current === 'paused' && $mapped === 'ongoing') {
            return 'paused';
        }

        return $mapped;
    }

    /**
     * Apply mapped status to series when it actually changes.
     *
     * @return array{changed: bool, broadcast_status: string|null}
     */
    public static function applyToSeries(Series $series, ?string $mapped): array
    {
        $next = self::resolve($series->broadcast_status, $mapped);
        $changed = $next !== null && $next !== $series->broadcast_status;

        if ($changed) {
            $series->broadcast_status = $next;
            $series->save();
        }

        return [
            'changed' => $changed,
            'broadcast_status' => $series->broadcast_status,
        ];
    }
}
