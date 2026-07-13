<?php

namespace App\Http\Controllers;

use App\Models\Episode;
use App\Models\Season;
use App\Models\Series;
use App\Services\EpisodeNotifier;
use App\Services\EpisodeProgressService;
use App\Support\TplCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminScheduleController extends Controller
{
    public function show(string $kp_id)
    {
        $series = Series::query()->where('kp_id', $kp_id)->firstOrFail();

        return response()->json([
            'seasons' => EpisodeProgressService::scheduleForSeries($series),
        ]);
    }

    public function save(Request $request, string $kp_id)
    {
        $series = Series::query()->where('kp_id', $kp_id)->firstOrFail();

        $data = $request->validate([
            'seasons' => ['nullable', 'array'],
            'seasons.*.season_number' => ['required', 'integer', 'min:1', 'max:999'],
            'seasons.*.title' => ['nullable', 'string', 'max:200'],
            'seasons.*.episodes' => ['nullable', 'array'],
            'seasons.*.episodes.*.episode_number' => ['required', 'integer', 'min:1', 'max:9999'],
            'seasons.*.episodes.*.title' => ['nullable', 'string', 'max:300'],
            'seasons.*.episodes.*.release_at' => ['nullable', 'date'],
            'seasons.*.episodes.*.status' => ['required', 'in:released,scheduled'],
            'seasons.*.episodes.*.voice' => ['nullable', 'string', 'max:120'],
        ]);

        $oldSeason = $series->season_number;
        $oldEpisode = $series->last_episode_number;

        DB::transaction(function () use ($series, $data) {
            Episode::withoutEvents(function () use ($series, $data) {
                Season::query()->where('series_id', $series->id)->delete();

                foreach ($data['seasons'] ?? [] as $seasonRow) {
                    $season = Season::query()->create([
                        'series_id' => $series->id,
                        'season_number' => (int)$seasonRow['season_number'],
                        'title' => $seasonRow['title'] ?? null,
                    ]);

                    foreach ($seasonRow['episodes'] ?? [] as $epRow) {
                        Episode::query()->create([
                            'season_id' => $season->id,
                            'episode_number' => (int)$epRow['episode_number'],
                            'title' => $epRow['title'] ?? null,
                            'release_at' => !empty($epRow['release_at']) ? $epRow['release_at'] : null,
                            'status' => $epRow['status'],
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

        return response()->json([
            'ok' => true,
            'seasons' => EpisodeProgressService::scheduleForSeries($series),
            'season_number' => $series->season_number,
            'last_episode_number' => $series->last_episode_number,
        ]);
    }
}
