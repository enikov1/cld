<?php

namespace App\Services;

use App\Models\Series;
use App\Support\AgeLimitFormatter;
use App\Support\SlugHelper;
use Illuminate\Support\Str;

class TmdbMapper
{
    /**
     * @param  array<string, mixed>  $details
     * @return array<string, mixed>
     */
    public static function toSeriesAttributes(array $details, bool $isTv): array
    {
        $tmdbId = $details['id'] ?? null;
        if ($tmdbId === null) {
            return [];
        }

        $title = $isTv
            ? ($details['name'] ?? null)
            : ($details['title'] ?? null);

        if (!$title || trim((string)$title) === '') {
            return [];
        }

        $genres = collect($details['genres'] ?? [])
            ->pluck('name')
            ->filter()
            ->values()
            ->all();

        $countries = self::extractCountries($details, $isTv);

        $externalIds = is_array($details['external_ids'] ?? null) ? $details['external_ids'] : [];
        $imdbId = isset($externalIds['imdb_id']) ? (string)$externalIds['imdb_id'] : null;
        if ($imdbId !== null && $imdbId !== '' && !str_starts_with($imdbId, 'tt')) {
            $imdbId = 'tt' . $imdbId;
        }

        $contentType = $isTv ? 'series' : 'film';
        $yearData = self::resolveYears($details, $isTv);

        return [
            'tmdb_id' => (string)$tmdbId,
            'imdb_id' => $imdbId ?: null,
            'title' => (string)$title,
            'title_en' => null,
            'title_original' => $isTv
                ? ($details['original_name'] ?? null)
                : ($details['original_title'] ?? null),
            'description' => isset($details['overview']) && trim((string)$details['overview']) !== ''
                ? (string)$details['overview']
                : null,
            'slogan' => isset($details['tagline']) && trim((string)$details['tagline']) !== ''
                ? (string)$details['tagline']
                : null,
            'year' => $yearData['year'],
            'start_year' => $yearData['start_year'],
            'end_year' => $yearData['end_year'],
            'duration_minutes' => self::resolveDuration($details, $isTv),
            'imdb_rating' => isset($details['vote_average']) ? round((float)$details['vote_average'], 1) : null,
            'imdb_votes_count' => isset($details['vote_count']) ? (int)$details['vote_count'] : null,
            'tmdb_popularity' => isset($details['popularity']) ? round((float)$details['popularity'], 4) : null,
            'content_type' => $contentType,
            'broadcast_status' => TmdbBroadcastStatusMapper::fromDetails($details, $contentType),
            'premiere_date' => $yearData['premiere_date'],
            'finale_date' => $yearData['finale_date'],
            'age_limit' => self::pickAgeLimitFromDetails($details),
            '_genre_names' => $genres,
            '_country_names' => $countries,
            'poster_source_url' => self::posterUrl($details['poster_path'] ?? null),
        ];
    }

    /**
     * Apply TMDB air dates, total runtime and age rating onto an existing series.
     *
     * @param  array<string, mixed>  $details
     * @param  array{first_air_date?: ?string, last_air_date?: ?string, total_runtime?: ?int}  $episodeStats
     */
    public static function applyAirMetadata(Series $series, array $details, bool $isTv, array $episodeStats = []): void
    {
        $attrs = self::toSeriesAttributes($details, $isTv);
        foreach (['year', 'start_year', 'premiere_date', 'duration_minutes', 'age_limit'] as $key) {
            if (!array_key_exists($key, $attrs)) {
                continue;
            }
            $value = $attrs[$key];
            if ($value === null || $value === '') {
                continue;
            }
            $series->{$key} = $value;
        }

        $mappedStatus = TmdbBroadcastStatusMapper::fromDetails($details, $isTv ? 'series' : 'film');
        if (array_key_exists('end_year', $attrs)) {
            $series->end_year = $attrs['end_year'];
        }
        if ($mappedStatus === 'completed' && !empty($attrs['finale_date'])) {
            $series->finale_date = $attrs['finale_date'];
        } elseif ($mappedStatus !== 'completed') {
            $series->finale_date = null;
        }

        $firstAir = self::dateFromString($episodeStats['first_air_date'] ?? null);
        if ($firstAir !== null) {
            $series->premiere_date = $firstAir;
            $year = self::yearFromDate($firstAir);
            if ($year !== null) {
                $series->year = $year;
                $series->start_year = $year;
            }
        }

        $lastAir = self::dateFromString($episodeStats['last_air_date'] ?? null);
        if ($lastAir !== null) {
            $endYear = self::laterEndYear(
                (int) ($series->start_year ?: $series->year ?: 0) ?: null,
                self::yearFromDate($lastAir),
            );
            $series->end_year = $endYear;
            if ($mappedStatus === 'completed') {
                $series->finale_date = $lastAir;
            }
        }

        $totalRuntime = isset($episodeStats['total_runtime']) ? (int)$episodeStats['total_runtime'] : 0;
        if ($totalRuntime > 0) {
            $series->duration_minutes = $totalRuntime;
        }
    }

    /**
     * First/last episode air dates and summed runtimes from TMDB season payloads.
     *
     * @param  array<int|string, array<string, mixed>>  $seasonPayloads
     * @return array{first_air_date: ?string, last_air_date: ?string, total_runtime: ?int}
     */
    public static function episodeAirStatsFromSeasonPayloads(array $seasonPayloads, ?int $typicalRuntime = null): array
    {
        $first = null;
        $last = null;
        $total = 0;
        $hasRuntime = false;
        $typical = $typicalRuntime !== null && $typicalRuntime > 0 ? $typicalRuntime : null;

        foreach ($seasonPayloads as $seasonNumber => $payload) {
            if ((int)$seasonNumber < 1 || !is_array($payload)) {
                continue;
            }

            foreach ($payload['episodes'] ?? [] as $episode) {
                if (!is_array($episode) || (int)($episode['episode_number'] ?? 0) < 1) {
                    continue;
                }

                $airDate = self::dateFromString($episode['air_date'] ?? null);
                if ($airDate !== null) {
                    if ($first === null || $airDate < $first) {
                        $first = $airDate;
                    }
                    if ($last === null || $airDate > $last) {
                        $last = $airDate;
                    }
                }

                $runtime = (int)($episode['runtime'] ?? 0);
                if ($runtime <= 0 && $typical !== null) {
                    $runtime = $typical;
                }
                if ($runtime > 0) {
                    $total += $runtime;
                    $hasRuntime = true;
                }
            }
        }

        return [
            'first_air_date' => $first,
            'last_air_date' => $last,
            'total_runtime' => $hasRuntime ? $total : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $details
     * @return array{year: ?int, start_year: ?int, end_year: ?int, premiere_date: ?string, finale_date: ?string}
     */
    private static function resolveYears(array $details, bool $isTv): array
    {
        if ($isTv) {
            $firstDate = self::firstEpisodeDate($details);
            $lastDate = self::lastEpisodeDate($details);
            $start = self::yearFromDate($firstDate);
            $isCompleted = TmdbBroadcastStatusMapper::fromDetails($details, 'series') === 'completed';
            $end = self::laterEndYear($start, self::yearFromDate($lastDate));

            return [
                'year' => $start,
                'start_year' => $start,
                'end_year' => $end,
                'premiere_date' => $firstDate,
                'finale_date' => $isCompleted ? $lastDate : null,
            ];
        }

        $year = self::yearFromDate($details['release_date'] ?? null);
        $releaseDate = self::dateFromString($details['release_date'] ?? null);

        return [
            'year' => $year,
            'start_year' => $year,
            'end_year' => $year,
            'premiere_date' => $releaseDate,
            'finale_date' => $releaseDate,
        ];
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private static function firstEpisodeDate(array $details): ?string
    {
        $fromEpisode = is_array($details['first_episode_to_air'] ?? null)
            ? self::dateFromString($details['first_episode_to_air']['air_date'] ?? null)
            : null;

        return $fromEpisode ?? self::dateFromString($details['first_air_date'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private static function lastEpisodeDate(array $details): ?string
    {
        $fromEpisode = is_array($details['last_episode_to_air'] ?? null)
            ? self::dateFromString($details['last_episode_to_air']['air_date'] ?? null)
            : null;

        return $fromEpisode ?? self::dateFromString($details['last_air_date'] ?? null);
    }

    private static function laterEndYear(?int $start, ?int $end): ?int
    {
        if ($start === null || $end === null || $end <= $start) {
            return null;
        }

        return $end;
    }

    /**
     * @param  array<string, mixed>  $details
     * @return list<string>
     */
    private static function extractCountries(array $details, bool $isTv): array
    {
        if ($isTv) {
            $codes = $details['origin_country'] ?? [];
            if (!is_array($codes)) {
                return [];
            }

            return collect($codes)
                ->map(fn ($code) => trim((string)$code))
                ->filter()
                ->values()
                ->all();
        }

        return collect($details['production_countries'] ?? [])
            ->map(fn ($item) => is_array($item) ? ($item['name'] ?? null) : null)
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private static function resolveDuration(array $details, bool $isTv): ?int
    {
        if (!$isTv) {
            $runtime = (int)($details['runtime'] ?? 0);

            return $runtime > 0 ? $runtime : null;
        }

        $typical = self::typicalEpisodeRuntime($details);
        $episodeCount = (int)($details['number_of_episodes'] ?? 0);
        if ($typical !== null && $episodeCount > 0) {
            return $typical * $episodeCount;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public static function typicalEpisodeRuntime(array $details): ?int
    {
        $runtimes = $details['episode_run_time'] ?? [];
        if (is_array($runtimes) && $runtimes !== []) {
            $minutes = (int)($runtimes[0] ?? 0);
            if ($minutes > 0) {
                return $minutes;
            }
        }

        $last = is_array($details['last_episode_to_air'] ?? null) ? $details['last_episode_to_air'] : null;
        if ($last !== null) {
            $minutes = (int)($last['runtime'] ?? 0);
            if ($minutes > 0) {
                return $minutes;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private static function pickAgeLimitFromDetails(array $details): ?string
    {
        $candidates = [];

        $contentRatings = is_array($details['content_ratings'] ?? null) ? $details['content_ratings'] : null;
        if ($contentRatings !== null) {
            foreach ($contentRatings['results'] ?? [] as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $iso = strtoupper(trim((string)($item['iso_3166_1'] ?? '')));
                $rating = trim((string)($item['rating'] ?? ''));
                if ($iso !== '' && $rating !== '') {
                    $candidates[$iso] = $rating;
                }
            }
        }

        $releaseDates = is_array($details['release_dates'] ?? null) ? $details['release_dates'] : null;
        if ($releaseDates !== null) {
            foreach ($releaseDates['results'] ?? [] as $country) {
                if (!is_array($country)) {
                    continue;
                }
                $iso = strtoupper(trim((string)($country['iso_3166_1'] ?? '')));
                if ($iso === '' || isset($candidates[$iso])) {
                    continue;
                }
                $best = '';
                foreach ($country['release_dates'] ?? [] as $release) {
                    if (!is_array($release)) {
                        continue;
                    }
                    $cert = trim((string)($release['certification'] ?? ''));
                    if ($cert === '') {
                        continue;
                    }
                    $best = $cert;
                    if ((int)($release['type'] ?? 0) === 3) {
                        break;
                    }
                }
                if ($best !== '') {
                    $candidates[$iso] = $best;
                }
            }
        }

        foreach (['RU', 'US', 'GB', 'DE'] as $prefer) {
            if (!isset($candidates[$prefer])) {
                continue;
            }
            $normalized = self::normalizeCertification($candidates[$prefer]);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        foreach ($candidates as $rating) {
            $normalized = self::normalizeCertification($rating);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        return null;
    }

    private static function normalizeCertification(string $rating): ?string
    {
        $mapped = match (strtoupper(trim($rating))) {
            'TV-MA', 'NC-17', 'R', 'X' => '18',
            'TV-14', 'PG-13' => '16',
            'TV-PG', 'PG' => '12',
            'TV-G', 'TV-Y', 'TV-Y7', 'G' => '0',
            default => null,
        };

        if ($mapped !== null) {
            return AgeLimitFormatter::normalize($mapped);
        }

        return AgeLimitFormatter::normalize($rating);
    }

    private static function posterUrl(mixed $posterPath): ?string
    {
        $path = trim((string)$posterPath);
        if ($path === '') {
            return null;
        }

        $base = rtrim((string)config('tmdb.image_base_url', 'https://image.tmdb.org/t/p'), '/');

        return $base . '/w500' . $path;
    }

    private static function yearFromDate(mixed $value): ?int
    {
        $date = self::dateFromString($value);
        if ($date === null) {
            return null;
        }

        $year = (int)substr($date, 0, 4);

        return ($year >= 1900 && $year <= 2100) ? $year : null;
    }

    private static function dateFromString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $value, $matches)) {
            return $matches[1];
        }

        return null;
    }

    public static function makeSlug(string $title, ?string $kpId, ?string $tmdbId): string
    {
        $slug = SlugHelper::make(null, $title);
        $suffix = $kpId ?: ($tmdbId ? 'tmdb-' . $tmdbId : Str::random(6));

        if (Series::query()->where('slug', $slug)->exists()) {
            return Str::slug($slug . '-' . $suffix);
        }

        return $slug;
    }
}
