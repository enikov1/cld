<?php

namespace App\Observers;

use App\Models\Episode;
use App\Services\EpisodeNotifier;
use App\Services\EpisodeProgressService;
use App\Services\SitemapService;
use App\Support\TplCache;

class EpisodeObserver
{
    public function saved(Episode $episode): void
    {
        $episode->loadMissing('season.series');
        $series = $episode->season?->series;
        if (!$series) {
            return;
        }

        $oldSeason = $series->season_number;
        $oldEpisode = $series->last_episode_number;

        EpisodeProgressService::syncSeries($series);
        $series->refresh();

        EpisodeNotifier::fromSeriesProgress($series, $oldSeason, $oldEpisode);

        TplCache::forgetSeries($series->id);
        app(SitemapService::class)->markDirty();
    }
}
