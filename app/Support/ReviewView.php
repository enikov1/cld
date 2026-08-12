<?php

namespace App\Support;

use App\Models\Review;

class ReviewView
{
    public static function countLabel(int $count): string
    {
        if ($count <= 0) {
            return '';
        }

        $mod10 = $count % 10;
        $mod100 = $count % 100;

        if ($mod100 >= 11 && $mod100 <= 14) {
            return $count . ' рецензий';
        }

        if ($mod10 === 1) {
            return $count . ' рецензия';
        }

        if ($mod10 >= 2 && $mod10 <= 4) {
            return $count . ' рецензии';
        }

        return $count . ' рецензий';
    }

    /**
     * @param array<string, mixed> $review
     * @return array<string, mixed>
     */
    public static function mapForTpl(array $review): array
    {
        $author = (string) ($review['author'] ?? '');
        $rating = max(1, min(10, (int) ($review['rating'] ?? 0)));

        return [
            'id' => $review['id'],
            'author' => $author,
            'created_at' => (string) ($review['created_at'] ?? ''),
            'created_at_iso' => (string) ($review['created_at_iso'] ?? ''),
            'body_html' => ReviewBody::renderHtml((string) ($review['body'] ?? '')),
            'body_plain' => CommentBody::effectiveBody((string) ($review['body'] ?? '')),
            'rating' => $rating,
            'rating_label' => $rating . '/10',
            'stars_html' => self::starsHtml($rating),
            'is_editorial' => !empty($review['is_editorial']),
            'author_type' => !empty($review['is_editorial']) ? 'Organization' : 'Person',
            'avatar_initial' => CommentView::authorInitial($author),
            'avatar_hue' => CommentView::authorHue($author),
        ];
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    public static function jsonLdNodes(array $items): array
    {
        $nodes = [];
        foreach ($items as $item) {
            $author = trim((string) ($item['author'] ?? ''));
            $rating = max(1, min(10, (int) ($item['rating'] ?? 0)));
            $body = CommentBody::effectiveBody((string) ($item['body'] ?? ''));
            if ($author === '' || $body === '') {
                continue;
            }

            $node = [
                '@type' => 'Review',
                'author' => [
                    '@type' => !empty($item['is_editorial']) ? 'Organization' : 'Person',
                    'name' => $author,
                ],
                'reviewBody' => mb_substr($body, 0, 5000),
                'reviewRating' => [
                    '@type' => 'Rating',
                    'ratingValue' => (string) $rating,
                    'bestRating' => '10',
                    'worstRating' => '1',
                ],
            ];

            $iso = trim((string) ($item['created_at_iso'] ?? ''));
            if ($iso !== '') {
                $node['datePublished'] = $iso;
            }

            $nodes[] = $node;
        }

        return $nodes;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function aggregateRatingJsonLd(int $seriesId): ?array
    {
        $query = Review::query()
            ->where('series_id', $seriesId)
            ->where('status', 'approved');

        $count = (clone $query)->count();
        if ($count <= 0) {
            return null;
        }

        $avg = (float) (clone $query)->avg('rating');

        return [
            '@type' => 'AggregateRating',
            'ratingValue' => (string) round($avg, 1),
            'bestRating' => '10',
            'worstRating' => '1',
            'ratingCount' => $count,
        ];
    }

    public static function starsHtml(int $rating): string
    {
        $rating = max(0, min(10, $rating));
        $html = '<span class="review-stars" aria-label="' . $rating . ' из 10">';
        for ($i = 1; $i <= 10; $i++) {
            $filled = $i <= $rating ? ' review-stars__star--filled' : '';
            $html .= '<span class="review-stars__star' . $filled . '" aria-hidden="true">★</span>';
        }
        $html .= '</span>';

        return $html;
    }
}
