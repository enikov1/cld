<?php

namespace App\Http\Controllers;

use App\Models\Series;
use App\Services\EpisodeProgressService;
use App\Services\SeriesScheduleWriter;
use App\Services\TmdbBroadcastStatusMapper;
use App\Services\TmdbConfig;
use App\Services\TmdbScheduleImportService;
use App\Support\TplCache;
use Illuminate\Http\Request;
use Throwable;

class AdminScheduleController extends Controller
{
    public function show(string $kp_id)
    {
        $series = Series::query()->where('kp_id', $kp_id)->firstOrFail();

        return response()->json([
            'seasons' => EpisodeProgressService::scheduleForSeries($series),
            'tmdb_id' => $series->tmdb_id,
            'broadcast_status' => $series->broadcast_status,
            'tmdb_api_key_set' => TmdbConfig::isConfigured(),
        ]);
    }

    public function importFromTmdb(Request $request, string $kp_id, TmdbScheduleImportService $importService)
    {
        $series = Series::query()->where('kp_id', $kp_id)->firstOrFail();

        $data = $request->validate([
            'mode' => ['required', 'in:replace,merge'],
            'persist' => ['sometimes', 'boolean'],
            'update_broadcast_status' => ['sometimes', 'boolean'],
            'seasons' => ['nullable', 'array'],
            'seasons.*.season_number' => ['required', 'integer', 'min:1', 'max:999'],
            'seasons.*.title' => ['nullable', 'string', 'max:200'],
            'seasons.*.episodes' => ['nullable', 'array'],
            'seasons.*.episodes.*.episode_number' => ['required', 'integer', 'min:1', 'max:9999'],
            'seasons.*.episodes.*.title' => ['nullable', 'string', 'max:300'],
            'seasons.*.episodes.*.release_at' => ['nullable', 'date'],
            'seasons.*.episodes.*.release_at_iso' => ['nullable', 'date'],
            'seasons.*.episodes.*.status' => ['nullable', 'in:released,scheduled'],
            'seasons.*.episodes.*.voice' => ['nullable', 'string', 'max:120'],
        ]);

        try {
            $imported = $importService->fetchForSeries($series);
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }

        $mode = $data['mode'];
        $persist = (bool)($data['persist'] ?? false);
        $updateBroadcastStatus = (bool)($data['update_broadcast_status'] ?? true);

        if ($mode === 'merge') {
            $base = $data['seasons'] ?? EpisodeProgressService::scheduleForSeries($series);
            $seasons = $importService->mergeSchedules($base, $imported['seasons']);
        } else {
            $seasons = $imported['seasons'];
        }

        $broadcastStatus = $imported['broadcast_status'] ?? $series->broadcast_status;
        $statusChanged = false;

        if ($updateBroadcastStatus) {
            $applied = TmdbBroadcastStatusMapper::applyToSeries(
                $series,
                $imported['meta']['broadcast_status_mapped'] ?? null,
            );
            $broadcastStatus = $applied['broadcast_status'];
            $statusChanged = $applied['changed'];
            if ($statusChanged) {
                TplCache::forgetSeries($series->id);
            }
        }

        if ($persist) {
            SeriesScheduleWriter::replace($series, $seasons);
            $series->refresh();

            return response()->json([
                'ok' => true,
                'mode' => $mode,
                'persisted' => true,
                'seasons' => EpisodeProgressService::scheduleForSeries($series),
                'meta' => $imported['meta'],
                'broadcast_status' => $series->broadcast_status,
                'broadcast_status_changed' => $statusChanged,
                'season_number' => $series->season_number,
                'last_episode_number' => $series->last_episode_number,
            ]);
        }

        return response()->json([
            'ok' => true,
            'mode' => $mode,
            'persisted' => false,
            'seasons' => $this->toEditorSeasons($seasons),
            'meta' => $imported['meta'],
            'broadcast_status' => $broadcastStatus,
            'broadcast_status_changed' => $statusChanged,
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

        $this->persistSchedule($series, $data['seasons'] ?? []);

        return response()->json([
            'ok' => true,
            'seasons' => EpisodeProgressService::scheduleForSeries($series),
            'season_number' => $series->season_number,
            'last_episode_number' => $series->last_episode_number,
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $seasons
     */
    private function persistSchedule(Series $series, array $seasons): void
    {
        SeriesScheduleWriter::replace($series, $seasons);
    }

    /**
     * @param  list<array<string, mixed>>  $seasons
     * @return list<array<string, mixed>>
     */
    private function toEditorSeasons(array $seasons): array
    {
        return array_map(function (array $season) {
            return [
                'season_number' => (int)$season['season_number'],
                'title' => $season['title'] ?? null,
                'episodes' => array_map(function (array $ep) {
                    $releaseAt = $ep['release_at'] ?? $ep['release_at_iso'] ?? null;
                    $iso = is_string($releaseAt) && $releaseAt !== '' ? substr($releaseAt, 0, 10) : null;

                    return [
                        'episode_number' => (int)$ep['episode_number'],
                        'title' => $ep['title'] ?? null,
                        'release_at' => $iso ? date('d.m.Y', strtotime($iso)) : null,
                        'release_at_iso' => $iso,
                        'status' => $ep['status'] ?? \App\Models\Episode::STATUS_SCHEDULED,
                        'voice' => $ep['voice'] ?? null,
                    ];
                }, $season['episodes'] ?? []),
            ];
        }, $seasons);
    }
}
