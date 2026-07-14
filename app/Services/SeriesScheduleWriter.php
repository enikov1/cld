<?php

namespace App\Services;

use App\Models\Episode;
use App\Models\Season;
use App\Models\Series;
use App\Support\TplCache;
use Illuminate\Support\Facades\DB;

class SeriesScheduleWriter
{
    /**
     * Replace series schedule and sync progress fields.
     *
     * @param  list<array<string, mixed>>  $seasons
     */
    public static function replace(Series $series, array $seasons): void
    {
        $oldSeason = $series->season_number;
        $oldEpisode = $series->last_episode_number;

        DB::transaction(function () use ($series, $seasons) {
            Episode::withoutEvents(function () use ($series, $seasons) {
                Season::query()->where('series_id', $series->id)->delete();

                foreach ($seasons as $seasonRow) {
                    $season = Season::query()->create([
                        'series_id' => $series->id,
                        'season_number' => (int)$seasonRow['season_number'],
                        'title' => $seasonRow['title'] ?? null,
                    ]);

                    foreach ($seasonRow['episodes'] ?? [] as $epRow) {
                        $releaseAt = $epRow['release_at'] ?? $epRow['release_at_iso'] ?? null;
                        Episode::query()->create([
                            'season_id' => $season->id,
                            'episode_number' => (int)$epRow['episode_number'],
                            'title' => $epRow['title'] ?? null,
                            'release_at' => !empty($releaseAt) ? $releaseAt : null,
                            'status' => $epRow['status'] ?? Episode::STATUS_SCHEDULED,
                            'voice' => $epRow['voice'] ?? null,
                        ]);
                    }
                }
            });

            EpisodeProgressService::syncSeries($series->fresh());
        });

        $series->refresh();
        EpisodeNotifier::fromSeriesProgress($series, $oldSeason, $oldEpisode);
        TplCache::forgetSeries($series->id);
    }
}
