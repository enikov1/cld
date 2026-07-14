<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class AdminSeriesFilter
{
    /**
     * @return array<string, mixed>
     */
    public static function params(Request $request): array
    {
        return [
            'q' => trim((string)$request->query('q', '')),
            'kp_id' => self::nullableString($request, 'kp_id'),
            'with_trashed' => $request->boolean('with_trashed'),
            'content_type' => self::nullableString($request, 'content_type'),
            'broadcast_status' => self::nullableString($request, 'broadcast_status'),
            'studio_id' => self::nullableInt($request, 'studio_id'),
            'genre_id' => self::nullableInt($request, 'genre_id'),
            'country_id' => self::nullableInt($request, 'country_id'),
            'actor_id' => self::nullableInt($request, 'actor_id'),
            'director_id' => self::nullableInt($request, 'director_id'),
            'is_active' => self::nullableBool($request, 'is_active'),
            'is_hidden' => self::nullableBool($request, 'is_hidden'),
            'is_pinned' => self::nullableBool($request, 'is_pinned'),
            'is_coming_soon' => self::nullableBool($request, 'is_coming_soon'),
            'noindex' => self::nullableBool($request, 'noindex'),
            'popular_badge_active' => self::nullableBool($request, 'popular_badge_active'),
            'has_poster' => self::nullableBool($request, 'has_poster'),
            'has_tmdb_id' => self::nullableBool($request, 'has_tmdb_id'),
            'year_from' => self::nullableInt($request, 'year_from'),
            'year_to' => self::nullableInt($request, 'year_to'),
            'kp_rating_min' => self::nullableFloat($request, 'kp_rating_min'),
            'imdb_rating_min' => self::nullableFloat($request, 'imdb_rating_min'),
            'tmdb_popularity_min' => self::nullableFloat($request, 'tmdb_popularity_min'),
            'views_min' => self::nullableInt($request, 'views_min'),
            'sort' => self::normalizeSort((string)$request->query('sort', 'default')),
            'page' => max(1, (int)$request->query('page', 1)),
            'per_page' => max(10, min(100, (int)$request->query('per_page', 50))),
        ];
    }

    /**
     * @param array<string, mixed> $params
     */
    public static function apply(Builder $query, array $params): void
    {
        $q = (string)($params['q'] ?? '');
        if ($q !== '') {
            $query->where(function (Builder $sub) use ($q) {
                $sub->where('title', 'like', '%' . $q . '%')
                    ->orWhere('title_en', 'like', '%' . $q . '%')
                    ->orWhere('title_original', 'like', '%' . $q . '%')
                    ->orWhere('kp_id', 'like', '%' . $q . '%')
                    ->orWhere('slug', 'like', '%' . $q . '%')
                    ->orWhere('imdb_id', 'like', '%' . $q . '%')
                    ->orWhere('tmdb_id', 'like', '%' . $q . '%');
            });
        }

        $kpId = trim((string)($params['kp_id'] ?? ''));
        if ($kpId !== '') {
            $query->where('kp_id', $kpId);
        }

        if (!empty($params['content_type'])) {
            $query->where('content_type', $params['content_type']);
        }

        if (!empty($params['broadcast_status'])) {
            $query->where('broadcast_status', $params['broadcast_status']);
        }

        if (!empty($params['studio_id'])) {
            $query->where('studio_id', (int)$params['studio_id']);
        }

        if (!empty($params['genre_id'])) {
            $genreId = (int)$params['genre_id'];
            $query->whereHas('genres', fn (Builder $rel) => $rel->where('genres.id', $genreId));
        }

        if (!empty($params['country_id'])) {
            $countryId = (int)$params['country_id'];
            $query->whereHas('countries', fn (Builder $rel) => $rel->where('countries.id', $countryId));
        }

        if (!empty($params['actor_id'])) {
            $actorId = (int)$params['actor_id'];
            $query->whereHas('actors', fn (Builder $rel) => $rel->where('people.id', $actorId));
        }

        if (!empty($params['director_id'])) {
            $directorId = (int)$params['director_id'];
            $query->whereHas('directors', fn (Builder $rel) => $rel->where('people.id', $directorId));
        }

        self::applyNullableBoolFilter($query, 'is_active', $params['is_active'] ?? null);
        self::applyNullableBoolFilter($query, 'is_hidden', $params['is_hidden'] ?? null);
        self::applyNullableBoolFilter($query, 'is_pinned', $params['is_pinned'] ?? null);
        self::applyNullableBoolFilter($query, 'is_coming_soon', $params['is_coming_soon'] ?? null);
        self::applyNullableBoolFilter($query, 'noindex', $params['noindex'] ?? null);
        self::applyNullableBoolFilter($query, 'popular_badge_active', $params['popular_badge_active'] ?? null);

        if (($params['has_poster'] ?? null) === true) {
            $query->whereNotNull('poster_url')->where('poster_url', '!=', '');
        } elseif (($params['has_poster'] ?? null) === false) {
            $query->where(function (Builder $sub) {
                $sub->whereNull('poster_url')->orWhere('poster_url', '');
            });
        }

        if (($params['has_tmdb_id'] ?? null) === true) {
            $query->whereNotNull('tmdb_id')->where('tmdb_id', '!=', '');
        } elseif (($params['has_tmdb_id'] ?? null) === false) {
            $query->where(function (Builder $sub) {
                $sub->whereNull('tmdb_id')->orWhere('tmdb_id', '');
            });
        }

        if (!empty($params['year_from'])) {
            $query->whereRaw('COALESCE(NULLIF(year, 0), start_year) >= ?', [(int)$params['year_from']]);
        }

        if (!empty($params['year_to'])) {
            $query->whereRaw('COALESCE(NULLIF(year, 0), start_year) <= ?', [(int)$params['year_to']]);
        }

        if ($params['kp_rating_min'] !== null) {
            $query->where('kp_rating', '>=', (float)$params['kp_rating_min']);
        }

        if ($params['imdb_rating_min'] !== null) {
            $query->where('imdb_rating', '>=', (float)$params['imdb_rating_min']);
        }

        if ($params['tmdb_popularity_min'] !== null) {
            $query->where('tmdb_popularity', '>=', (float)$params['tmdb_popularity_min']);
        }

        if ($params['views_min'] !== null) {
            $query->where('views_count', '>=', (int)$params['views_min']);
        }
    }

    /**
     * @param array<string, mixed> $params
     */
    public static function applySort(Builder $query, array $params): void
    {
        match ((string)($params['sort'] ?? 'default')) {
            'title_asc' => $query->orderBy('title')->orderByDesc('id'),
            'title_desc' => $query->orderByDesc('title')->orderByDesc('id'),
            'year_desc' => $query->orderByRaw('COALESCE(NULLIF(year, 0), start_year) DESC')->orderByDesc('id'),
            'year_asc' => $query->orderByRaw('COALESCE(NULLIF(year, 0), start_year) ASC')->orderByDesc('id'),
            'kp_rating_desc' => $query->orderByRaw('kp_rating IS NULL')->orderByDesc('kp_rating')->orderByDesc('id'),
            'kp_rating_asc' => $query->orderByRaw('kp_rating IS NULL')->orderBy('kp_rating')->orderByDesc('id'),
            'imdb_rating_desc' => $query->orderByRaw('imdb_rating IS NULL')->orderByDesc('imdb_rating')->orderByDesc('id'),
            'tmdb_popularity_desc' => $query->orderByRaw('tmdb_popularity IS NULL')->orderByDesc('tmdb_popularity')->orderByDesc('id'),
            'tmdb_popularity_asc' => $query->orderByRaw('tmdb_popularity IS NULL')->orderBy('tmdb_popularity')->orderByDesc('id'),
            'views_desc' => $query->orderByDesc('views_count')->orderByDesc('id'),
            'views_asc' => $query->orderBy('views_count')->orderByDesc('id'),
            'kp_id_asc' => $query->orderByRaw('CAST(kp_id AS UNSIGNED) ASC')->orderBy('kp_id')->orderByDesc('id'),
            'kp_id_desc' => $query->orderByRaw('CAST(kp_id AS UNSIGNED) DESC')->orderByDesc('kp_id')->orderByDesc('id'),
            'content_type_asc' => $query->orderBy('content_type')->orderByDesc('id'),
            'content_type_desc' => $query->orderByDesc('content_type')->orderByDesc('id'),
            'broadcast_status_asc' => $query->orderByRaw('broadcast_status IS NULL')->orderBy('broadcast_status')->orderByDesc('id'),
            'broadcast_status_desc' => $query->orderByRaw('broadcast_status IS NULL')->orderByDesc('broadcast_status')->orderByDesc('id'),
            'popular_badge_desc' => $query->orderByDesc('popular_badge_active')->orderByDesc('id'),
            'popular_badge_asc' => $query->orderBy('popular_badge_active')->orderByDesc('id'),
            'is_active_desc' => $query->orderByDesc('is_active')->orderByDesc('id'),
            'is_active_asc' => $query->orderBy('is_active')->orderByDesc('id'),
            'created_desc' => $query->orderByDesc('id'),
            'created_asc' => $query->orderBy('id'),
            default => $query->orderByDesc('is_pinned')
                ->orderByDesc('pinned_at')
                ->orderBy('sort_order')
                ->orderByDesc('id'),
        };
    }

    private static function normalizeSort(string $sort): string
    {
        $allowed = [
            'default',
            'title_asc',
            'title_desc',
            'year_desc',
            'year_asc',
            'kp_rating_desc',
            'kp_rating_asc',
            'imdb_rating_desc',
            'tmdb_popularity_desc',
            'tmdb_popularity_asc',
            'views_desc',
            'views_asc',
            'kp_id_asc',
            'kp_id_desc',
            'content_type_asc',
            'content_type_desc',
            'broadcast_status_asc',
            'broadcast_status_desc',
            'popular_badge_desc',
            'popular_badge_asc',
            'is_active_desc',
            'is_active_asc',
            'created_desc',
            'created_asc',
        ];

        return in_array($sort, $allowed, true) ? $sort : 'default';
    }

    private static function nullableString(Request $request, string $key): ?string
    {
        $value = trim((string)$request->query($key, ''));

        return $value !== '' ? $value : null;
    }

    private static function nullableInt(Request $request, string $key): ?int
    {
        $raw = $request->query($key);
        if ($raw === null || $raw === '') {
            return null;
        }

        $value = (int)$raw;

        return $value > 0 ? $value : null;
    }

    private static function nullableFloat(Request $request, string $key): ?float
    {
        $raw = $request->query($key);
        if ($raw === null || $raw === '') {
            return null;
        }

        if (!is_numeric($raw)) {
            return null;
        }

        return (float)$raw;
    }

    private static function nullableBool(Request $request, string $key): ?bool
    {
        if (!$request->has($key)) {
            return null;
        }

        $raw = $request->query($key);
        if ($raw === null || $raw === '') {
            return null;
        }

        return filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }

    private static function applyNullableBoolFilter(Builder $query, string $column, ?bool $value): void
    {
        if ($value === null) {
            return;
        }

        $query->where($column, $value);
    }
}
