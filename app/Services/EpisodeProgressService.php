<?php

namespace App\Services;

use App\Models\Episode;
use App\Models\Season;
use App\Models\Series;

class EpisodeProgressService
{
    /**
     * Обновить season_number / last_episode_number на сериале по последней вышедшей серии.
     */
    public static function syncSeries(Series $series): void
    {
        $latest = Episode::query()
            ->where('status', Episode::STATUS_RELEASED)
            ->whereHas('season', fn ($q) => $q->where('series_id', $series->id))
            ->with('season')
            ->get()
            ->sortByDesc(fn (Episode $ep) => ((int)$ep->season->season_number * 10000) + (int)$ep->episode_number)
            ->first();

        if (!$latest) {
            return;
        }

        $series->update([
            'season_number' => (int)$latest->season->season_number,
            'last_episode_number' => (int)$latest->episode_number,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function scheduleForSeries(Series $series): array
    {
        return Season::query()
            ->where('series_id', $series->id)
            ->with('episodes')
            ->orderBy('season_number')
            ->get()
            ->map(fn (Season $season) => [
                'id' => $season->id,
                'season_number' => $season->season_number,
                'title' => $season->displayTitle(),
                'episodes' => $season->episodes->map(fn (Episode $ep) => [
                    'id' => $ep->id,
                    'episode_number' => $ep->episode_number,
                    'title' => $ep->displayTitle(),
                    'raw_title' => $ep->title,
                    'release_at' => $ep->release_at?->format('d.m.Y'),
                    'release_at_iso' => $ep->release_at?->toDateString(),
                    'status' => $ep->status,
                    'is_released' => $ep->isReleased(),
                    'voice' => $ep->voice,
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{name: string}>
     */
    public static function voicesForSeries(Series $series): array
    {
        $voices = Episode::query()
            ->whereNotNull('voice')
            ->where('voice', '!=', '')
            ->whereHas('season', fn ($q) => $q->where('series_id', $series->id))
            ->distinct()
            ->orderBy('voice')
            ->pluck('voice');

        return $voices->map(fn (string $v) => ['name' => $v])->values()->all();
    }
}
