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
            'age_limit' => AgeLimitFormatter::normalize(
                is_array($details['content_ratings'] ?? null)
                    ? self::pickAgeLimit($details['content_ratings'])
                    : null,
            ),
            '_genre_names' => $genres,
            '_country_names' => $countries,
            'poster_source_url' => self::posterUrl($details['poster_path'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $details
     * @return array{year: ?int, start_year: ?int, end_year: ?int, premiere_date: ?string}
     */
    private static function resolveYears(array $details, bool $isTv): array
    {
        if ($isTv) {
            $start = self::yearFromDate($details['first_air_date'] ?? null);
            $end = self::yearFromDate($details['last_air_date'] ?? null);

            return [
                'year' => $start,
                'start_year' => $start,
                'end_year' => $end,
                'premiere_date' => self::dateFromString($details['first_air_date'] ?? null),
            ];
        }

        $year = self::yearFromDate($details['release_date'] ?? null);

        return [
            'year' => $year,
            'start_year' => $year,
            'end_year' => $year,
            'premiere_date' => self::dateFromString($details['release_date'] ?? null),
        ];
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
        if ($isTv) {
            $runtimes = $details['episode_run_time'] ?? [];
            if (!is_array($runtimes) || $runtimes === []) {
                return null;
            }
            $minutes = (int)($runtimes[0] ?? 0);

            return $minutes > 0 ? $minutes : null;
        }

        $runtime = (int)($details['runtime'] ?? 0);

        return $runtime > 0 ? $runtime : null;
    }

    /**
     * @param  array<string, mixed>  $contentRatings
     */
    private static function pickAgeLimit(array $contentRatings): ?string
    {
        $results = $contentRatings['results'] ?? [];
        if (!is_array($results)) {
            return null;
        }

        foreach ($results as $item) {
            if (!is_array($item)) {
                continue;
            }
            if (strtoupper((string)($item['iso_3166_1'] ?? '')) !== 'RU') {
                continue;
            }
            $rating = trim((string)($item['rating'] ?? ''));

            return $rating !== '' ? $rating : null;
        }

        return null;
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
