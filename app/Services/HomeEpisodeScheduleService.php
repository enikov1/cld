<?php

namespace App\Services;

use App\Models\Episode;
use App\Models\Series;
use App\Support\SeriesUrl;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;

class HomeEpisodeScheduleService
{
    private const MONTH_NAMES = [
        1 => 'Январь',
        2 => 'Февраль',
        3 => 'Март',
        4 => 'Апрель',
        5 => 'Май',
        6 => 'Июнь',
        7 => 'Июль',
        8 => 'Август',
        9 => 'Сентябрь',
        10 => 'Октябрь',
        11 => 'Ноябрь',
        12 => 'Декабрь',
    ];

    /**
     * Published series with at least one released episode in the given window,
     * ordered by latest such release_at DESC.
     *
     * @return EloquentCollection<int, Series>
     */
    public static function recentReleasedSeries(int $days = 14, int $limit = 16): EloquentCollection
    {
        $days = max(1, $days);
        $limit = max(1, $limit);
        $from = now()->subDays($days)->startOfDay();
        $to = now();

        $sub = DB::table('episodes')
            ->join('seasons', 'seasons.id', '=', 'episodes.season_id')
            ->where('episodes.status', Episode::STATUS_RELEASED)
            ->whereBetween('episodes.release_at', [$from, $to])
            ->groupBy('seasons.series_id')
            ->select('seasons.series_id', DB::raw('MAX(episodes.release_at) as last_release_at'));

        return Series::query()
            ->published()
            ->joinSub($sub, 'ep_rel', 'ep_rel.series_id', '=', 'series.id')
            ->orderByDesc('ep_rel.last_release_at')
            ->orderByDesc('series.id')
            ->select('series.*')
            ->limit($limit)
            ->get();
    }

    /**
     * Episodes with release_at in the given month, grouped by date.
     *
     * @return array{
     *     year: int,
     *     month: int,
     *     month_label: string,
     *     today: string,
     *     days_with_episodes: list<int>,
     *     days: array<string, list<array<string, mixed>>>
     * }
     */
    public static function calendarMonth(int $year, int $month): array
    {
        $year = max(1970, min(2100, $year));
        $month = max(1, min(12, $month));

        $start = Carbon::create($year, $month, 1)->startOfDay();
        $end = $start->copy()->endOfMonth();

        $episodes = Episode::query()
            ->whereNotNull('release_at')
            ->whereBetween('release_at', [$start, $end])
            ->whereHas('season.series', fn ($q) => $q->published())
            ->with(['season.series'])
            ->orderBy('release_at')
            ->orderBy('id')
            ->get();

        /** @var array<string, list<array<string, mixed>>> $days */
        $days = [];

        foreach ($episodes as $episode) {
            $season = $episode->season;
            $series = $season?->series;
            if (!$series || !$episode->release_at) {
                continue;
            }

            $date = $episode->release_at->toDateString();
            $days[$date] ??= [];
            $days[$date][] = [
                'series_url' => SeriesUrl::path($series),
                'series_title' => $series->title,
                'poster_url' => $series->poster_url ?? '',
                'season_number' => (int) $season->season_number,
                'episode_number' => (int) $episode->episode_number,
                'episode_title' => $episode->displayTitle(),
                'release_at' => $episode->release_at->format('d.m.Y'),
                'release_at_iso' => $date,
                'status' => $episode->status,
                'is_released' => $episode->isReleased(),
            ];
        }

        ksort($days);

        $daysWithEpisodes = array_map(
            static fn (string $date): int => (int) substr($date, 8, 2),
            array_keys($days)
        );

        return [
            'year' => $year,
            'month' => $month,
            'month_label' => (self::MONTH_NAMES[$month] ?? '') . ' ' . $year,
            'today' => now()->toDateString(),
            'days_with_episodes' => array_values(array_unique($daysWithEpisodes)),
            'days' => $days,
        ];
    }
}
