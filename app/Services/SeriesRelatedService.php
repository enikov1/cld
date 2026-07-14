<?php

namespace App\Services;

use App\Models\Series;
use Illuminate\Database\Eloquent\Collection;

class SeriesRelatedService
{
    public const DEFAULT_LIMIT = 12;

    /**
     * Related series by shared genres (overlap score), then popularity.
     * Falls back to popular catalog items when genres are missing.
     *
     * @return Collection<int, Series>
     */
    public static function forSeries(Series $series, int $limit = self::DEFAULT_LIMIT): Collection
    {
        $limit = max(1, min(24, $limit));
        $excludeId = (int)$series->id;

        $genreIds = $series->relationLoaded('genres')
            ? $series->genres->pluck('id')->map(fn ($id) => (int)$id)->filter()->values()->all()
            : $series->genres()->pluck('genres.id')->map(fn ($id) => (int)$id)->filter()->values()->all();

        if ($genreIds !== []) {
            $related = Series::query()
                ->published()
                ->where('id', '!=', $excludeId)
                ->whereHas('genres', fn ($q) => $q->whereIn('genres.id', $genreIds))
                ->withCount([
                    'genres as shared_genres_count' => fn ($q) => $q->whereIn('genres.id', $genreIds),
                ])
                ->orderByDesc('shared_genres_count')
                ->orderByRaw('tmdb_popularity IS NULL')
                ->orderByDesc('tmdb_popularity')
                ->orderByDesc('kp_votes_count')
                ->orderByDesc('id')
                ->limit($limit)
                ->get();

            if ($related->isNotEmpty()) {
                return $related;
            }
        }

        return Series::query()
            ->published()
            ->where('id', '!=', $excludeId)
            ->orderByDesc('is_pinned')
            ->orderByDesc('pinned_at')
            ->orderByRaw('tmdb_popularity IS NULL')
            ->orderByDesc('tmdb_popularity')
            ->orderByDesc('kp_votes_count')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }
}
