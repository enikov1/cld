<?php

namespace App\Services;

use App\Jobs\DispatchEpisodeNotifications;
use App\Models\NotificationEvent;
use App\Models\Series;

class EpisodeNotifier
{
    /**
     * Уведомление только при росте прогресса сериала (сезон / номер последней серии).
     * Не срабатывает при добавлении озвучки к уже вышедшей серии.
     */
    public static function fromSeriesProgress(Series $series, ?int $oldSeason, ?int $oldEpisode): void
    {
        $newSeason = $series->season_number;
        $newEpisode = $series->last_episode_number;

        if (!$newSeason || !$newEpisode) {
            return;
        }

        if ($oldSeason === $newSeason && $oldEpisode === $newEpisode) {
            return;
        }

        if ($oldSeason !== null && $oldEpisode !== null) {
            if ($newSeason < $oldSeason || ($newSeason === $oldSeason && $newEpisode <= $oldEpisode)) {
                return;
            }
        }

        self::createEventAndDispatch($series, null, $newSeason, $newEpisode, null);
    }

    private static function createEventAndDispatch(
        Series $series,
        ?int $episodeId,
        int $seasonNumber,
        int $episodeNumber,
        ?string $voice
    ): void {
        $now = now();

        $event = NotificationEvent::query()->create([
            'series_id' => $series->id,
            'episode_id' => $episodeId,
            'season_number' => $seasonNumber,
            'episode_number' => $episodeNumber,
            'voice' => $voice,
            'event_type' => 'new_episode',
            'created_at' => $now,
        ]);

        $series->forceFill(['last_episode_changed_at' => $now])->saveQuietly();

        if (app()->runningInConsole()) {
            DispatchEpisodeNotifications::dispatchSync($event->id);
            return;
        }

        // After HTTP response so admin requests are not blocked by email loop.
        dispatch(static function () use ($event): void {
            (new DispatchEpisodeNotifications($event->id))->handle();
        })->afterResponse();
    }
}
