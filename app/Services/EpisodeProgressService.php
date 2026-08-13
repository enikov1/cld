<?php

namespace App\Services;

use App\Models\Episode;
use App\Models\Season;
use App\Models\Series;
use App\Support\PluralRu;
use Illuminate\Support\Facades\DB;

class EpisodeProgressService
{
    private static ?string $progressMemoRequestId = null;

    /** @var array<string, array<int, array{season_number: int, last_episode_number: int}>> */
    private static array $progressMemo = [];

    /**
     * Обновить season_number / last_episode_number на сериале по последней вышедшей серии.
     */
    public static function syncSeries(Series $series): void
    {
        $progress = self::latestReleasedProgressBySeriesIds([(int) $series->id])[(int) $series->id] ?? null;
        if ($progress === null) {
            return;
        }

        $series->update([
            'season_number' => $progress['season_number'],
            'last_episode_number' => $progress['last_episode_number'],
        ]);
    }

    /**
     * Текущий прогресс для вывода: из графика (последняя вышедшая), иначе из полей сериала.
     *
     * @return array{season_number: int|null, last_episode_number: int|null, from_schedule: bool, label: string}
     */
    public static function resolvedProgress(Series $series): array
    {
        $map = self::latestReleasedProgressBySeriesIds([(int) $series->id]);

        return self::progressFromMap($series, $map);
    }

    /**
     * @param  list<Series>  $seriesList
     * @return array<int, array{season_number: int|null, last_episode_number: int|null, from_schedule: bool, label: string}>
     */
    public static function resolvedProgressForSeries(array $seriesList): array
    {
        $ids = [];
        foreach ($seriesList as $series) {
            if ($series instanceof Series) {
                $ids[] = (int) $series->id;
            }
        }

        $map = self::latestReleasedProgressBySeriesIds($ids);
        $out = [];
        foreach ($seriesList as $series) {
            if (!$series instanceof Series) {
                continue;
            }
            $out[(int) $series->id] = self::progressFromMap($series, $map);
        }

        return $out;
    }

    /**
     * @param  array<int, array{season_number: int, last_episode_number: int}>  $map
     * @return array{season_number: int|null, last_episode_number: int|null, from_schedule: bool, label: string}
     */
    private static function progressFromMap(Series $series, array $map): array
    {
        $id = (int) $series->id;
        if (isset($map[$id])) {
            $season = $map[$id]['season_number'];
            $episode = $map[$id]['last_episode_number'];

            return [
                'season_number' => $season,
                'last_episode_number' => $episode,
                'from_schedule' => true,
                'label' => self::formatProgressLabel($season, $episode),
            ];
        }

        $season = $series->season_number ? (int) $series->season_number : null;
        $episode = $series->last_episode_number ? (int) $series->last_episode_number : null;

        return [
            'season_number' => $season,
            'last_episode_number' => $episode,
            'from_schedule' => false,
            'label' => self::formatProgressLabel($season, $episode),
        ];
    }

    public static function formatProgressLabel(?int $seasonNumber, ?int $episodeNumber): string
    {
        $parts = [];
        if ($seasonNumber) {
            $parts[] = $seasonNumber . ' сезон';
        }
        if ($episodeNumber) {
            $parts[] = $episodeNumber . ' серия';
        }

        return implode(', ', $parts);
    }

    /**
     * Последняя вышедшая серия в графике по каждому series_id.
     *
     * @param  list<int>  $seriesIds
     * @return array<int, array{season_number: int, last_episode_number: int}>
     */
    public static function latestReleasedProgressBySeriesIds(array $seriesIds): array
    {
        $seriesIds = array_values(array_unique(array_filter(array_map('intval', $seriesIds))));
        if ($seriesIds === []) {
            return [];
        }

        sort($seriesIds);
        $memoKey = implode(',', $seriesIds);
        $requestId = (string) spl_object_id(request());
        if (self::$progressMemoRequestId !== $requestId) {
            self::$progressMemoRequestId = $requestId;
            self::$progressMemo = [];
        }
        if (isset(self::$progressMemo[$memoKey])) {
            return self::$progressMemo[$memoKey];
        }

        $rows = DB::table('episodes')
            ->join('seasons', 'seasons.id', '=', 'episodes.season_id')
            ->whereIn('seasons.series_id', $seriesIds)
            ->where('episodes.status', Episode::STATUS_RELEASED)
            ->get([
                'seasons.series_id as series_id',
                'seasons.season_number as season_number',
                'episodes.episode_number as episode_number',
            ]);

        $best = [];
        foreach ($rows as $row) {
            $sid = (int) $row->series_id;
            $season = (int) $row->season_number;
            $episode = (int) $row->episode_number;
            $score = ($season * 10000) + $episode;
            if (!isset($best[$sid]) || $score > $best[$sid]['score']) {
                $best[$sid] = [
                    'score' => $score,
                    'season_number' => $season,
                    'last_episode_number' => $episode,
                ];
            }
        }

        $out = [];
        foreach ($best as $sid => $row) {
            $out[$sid] = [
                'season_number' => $row['season_number'],
                'last_episode_number' => $row['last_episode_number'],
            ];
        }

        return self::$progressMemo[$memoKey] = $out;
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
     * Ближайшая запланированная серия из графика (для напоминания на странице сериала).
     *
     * @return array{
     *     label: string,
     *     season_number: int,
     *     episode_number: int,
     *     release_at_iso: string,
     *     days_until: int
     * }|null
     */
    public static function nextUpcomingReminder(Series $series): ?array
    {
        $today = now()->startOfDay();

        $episode = Episode::query()
            ->where('status', Episode::STATUS_SCHEDULED)
            ->whereNotNull('release_at')
            ->whereDate('release_at', '>=', $today->toDateString())
            ->whereHas('season', fn ($q) => $q->where('series_id', $series->id))
            ->with('season')
            ->orderBy('release_at')
            ->orderBy('id')
            ->first();

        if (!$episode || !$episode->release_at || !$episode->season) {
            return null;
        }

        $releaseDay = $episode->release_at->copy()->startOfDay();
        $daysUntil = (int) round($today->diffInDays($releaseDay, false));
        if ($daysUntil < 0) {
            return null;
        }

        $seasonNumber = (int)$episode->season->season_number;
        $episodeNumber = (int)$episode->episode_number;
        $currentSeason = (int) (self::resolvedProgress($series)['season_number'] ?? 0);

        $subject = ($currentSeason > 0 && $seasonNumber === $currentSeason)
            ? $episodeNumber . ' серия'
            : $seasonNumber . ' сезон, ' . $episodeNumber . ' серия';

        $when = match (true) {
            $daysUntil === 0 => 'выходит сегодня',
            $daysUntil === 1 => 'выйдет завтра',
            default => 'выйдет через ' . $daysUntil . ' ' . PluralRu::days($daysUntil),
        };

        return [
            'label' => $subject . ' ' . $when,
            'season_number' => $seasonNumber,
            'episode_number' => $episodeNumber,
            'release_at_iso' => $releaseDay->toDateString(),
            'days_until' => $daysUntil,
        ];
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
