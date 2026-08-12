<?php

namespace App\Support;

use App\Models\Review;

class ReviewList
{
    public static function normalizeSort(mixed $sort): string
    {
        return $sort === 'rating' ? 'rating' : 'date';
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public static function page(int $seriesId, string $sort = 'date'): array
    {
        $limit = max(1, min(200, SiteConfig::int('reviews_list_limit')));

        $base = Review::query()
            ->where('series_id', $seriesId)
            ->where('status', 'approved');

        $total = (clone $base)->count();

        $query = (clone $base)->with('user:id,name');

        if ($sort === 'rating') {
            $query->orderByDesc('rating')->orderByDesc('created_at')->orderByDesc('id');
        } else {
            $query->orderByDesc('created_at')->orderByDesc('id');
        }

        $items = $query->limit($limit)->get()->map(static fn (Review $review) => self::serialize($review))->all();

        return [
            'items' => $items,
            'total' => $total,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function forSeries(int $seriesId, string $sort = 'date'): array
    {
        return self::page($seriesId, $sort)['items'];
    }

    /**
     * @return array<string, mixed>
     */
    public static function serialize(Review $review): array
    {
        return [
            'id' => $review->id,
            'author' => $review->displayName(),
            'rating' => (int) $review->rating,
            'body' => $review->body,
            'created_at' => optional($review->created_at)?->timezone(config('app.timezone'))->format('d.m.Y H:i') ?? '',
            'created_at_iso' => optional($review->created_at)?->toAtomString() ?? '',
            'is_editorial' => (bool) $review->is_editorial,
        ];
    }
}
