<?php

namespace App\Services;

use App\Models\Episode;
use App\Models\Series;
use App\Support\AgeLimitFormatter;
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

    private const MONTH_NAMES_GENITIVE = [
        1 => 'января',
        2 => 'февраля',
        3 => 'марта',
        4 => 'апреля',
        5 => 'мая',
        6 => 'июня',
        7 => 'июля',
        8 => 'августа',
        9 => 'сентября',
        10 => 'октября',
        11 => 'ноября',
        12 => 'декабря',
    ];

    private const WEEKDAYS = [
        0 => 'воскресенье',
        1 => 'понедельник',
        2 => 'вторник',
        3 => 'среда',
        4 => 'четверг',
        5 => 'пятница',
        6 => 'суббота',
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
     *     days: array<string, list<array<string, mixed>>>,
     *     timeline: list<array<string, mixed>>,
     *     episode_count: int
     * }
     */
    public static function calendarMonth(int $year, int $month, bool $withDetails = false): array
    {
        $year = max(1970, min(2100, $year));
        $month = max(1, min(12, $month));

        $start = Carbon::create($year, $month, 1)->startOfDay();
        $end = $start->copy()->endOfMonth();

        $with = ['season.series'];
        if ($withDetails) {
            $with = ['season.series.genres', 'season.series.countries'];
        }

        $episodes = Episode::query()
            ->whereNotNull('release_at')
            ->whereBetween('release_at', [$start, $end])
            ->whereHas('season.series', fn ($q) => $q->published())
            ->with($with)
            ->orderBy('release_at')
            ->orderBy('id')
            ->get();

        /** @var array<string, list<array<string, mixed>>> $days */
        $days = [];

        foreach ($episodes as $episode) {
            $mapped = self::mapCalendarEpisode($episode, $withDetails);
            if ($mapped === null) {
                continue;
            }

            $date = $mapped['release_at_iso'];
            $days[$date] ??= [];
            $days[$date][] = $mapped;
        }

        ksort($days);

        $today = now()->toDateString();
        $timeline = [];
        foreach ($days as $date => $items) {
            $timeline[] = self::timelineDay($date, $items, $today);
        }

        $daysWithEpisodes = array_map(
            static fn (string $date): int => (int) substr($date, 8, 2),
            array_keys($days)
        );

        return [
            'year' => $year,
            'month' => $month,
            'month_label' => (self::MONTH_NAMES[$month] ?? '') . ' ' . $year,
            'today' => $today,
            'days_with_episodes' => array_values(array_unique($daysWithEpisodes)),
            'days' => $days,
            'timeline' => $timeline,
            'episode_count' => array_sum(array_map('count', $days)),
        ];
    }

    public static function normalizeYear(mixed $year, ?Carbon $now = null): int
    {
        $now ??= now();
        $value = (int) $year;

        return $value >= 1970 && $value <= 2100 ? $value : (int) $now->year;
    }

    public static function normalizeMonth(mixed $month, ?Carbon $now = null): int
    {
        $now ??= now();
        $value = (int) $month;

        return $value >= 1 && $value <= 12 ? $value : (int) $now->month;
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return array<string, mixed>
     */
    public static function timelineDay(string $date, array $items, ?string $today = null): array
    {
        $carbon = Carbon::parse($date);
        $today ??= now()->toDateString();

        return [
            'date' => $date,
            'date_label' => $carbon->day . ' ' . (self::MONTH_NAMES_GENITIVE[(int) $carbon->month] ?? ''),
            'weekday' => self::WEEKDAYS[(int) $carbon->dayOfWeek] ?? '',
            'is_today' => $date === $today,
            'count' => count($items),
            'episodes' => $items,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function mapCalendarEpisode(Episode $episode, bool $withDetails): ?array
    {
        $season = $episode->season;
        $series = $season?->series;
        if (!$series || !$episode->release_at) {
            return null;
        }

        $date = $episode->release_at->toDateString();
        $item = [
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

        if (!$withDetails) {
            return $item;
        }

        $genres = $series->relationLoaded('genres')
            ? $series->genres->pluck('name')->filter()->take(3)->values()->all()
            : [];
        $countries = $series->relationLoaded('countries')
            ? $series->countries->pluck('name')->filter()->take(2)->values()->all()
            : [];

        $year = (int) ($series->year ?: $series->start_year ?: 0);

        return array_merge($item, [
            'title_original' => (string) ($series->title_original ?: $series->title_en ?: ''),
            'year' => $year >= 1900 ? $year : '',
            'age_label' => AgeLimitFormatter::label($series->age_limit) ?? '',
            'genres' => $genres,
            'genres_label' => implode(' / ', $genres),
            'countries' => $countries,
            'countries_label' => implode(', ', $countries),
            'kp_rating' => self::formatRating($series->kp_rating),
            'imdb_rating' => self::formatRating($series->imdb_rating),
            'channel_name' => (string) ($series->channel_name ?? ''),
        ]);
    }

    private static function formatRating(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $number = (float) $value;
        if ($number <= 0) {
            return '';
        }

        return rtrim(rtrim(number_format($number, 1, '.', ''), '0'), '.');
    }
}
