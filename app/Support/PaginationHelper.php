<?php

namespace App\Support;

use App\Services\SeriesCardMapper;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class PaginationHelper
{
    public static function robotsMeta(int $page, bool $entityNoindex = false): string
    {
        if ($entityNoindex || $page > 1) {
            return 'noindex,follow';
        }

        return '';
    }

    public static function isPaginatedRequest(int $page, ?string $path = null): bool
    {
        if ($page > 1) {
            return true;
        }

        if ($path !== null && preg_match('#/page/([2-9]\d*)/#', $path)) {
            return true;
        }

        return false;
    }

    /**
     * @param array<string, scalar|null> $queryParams
     * @return array<string,mixed>
     */
    public static function buildMeta(LengthAwarePaginator $paginator, string $basePath, array $queryParams = []): array
    {
        $current = $paginator->currentPage();
        $last = $paginator->lastPage();
        $queryParams = array_filter($queryParams, static fn ($v) => $v !== null && $v !== '');
        $queryString = $queryParams ? '?' . http_build_query($queryParams) : '';

        $pageUrl = function (int $page) use ($basePath, $queryString): string {
            if ($page <= 1) {
                return rtrim($basePath, '/') . '/' . $queryString;
            }

            return rtrim($basePath, '/') . '/page/' . $page . '/' . $queryString;
        };

        $pages = [];
        for ($i = 1; $i <= $last; $i++) {
            $pages[] = [
                'num' => $i,
                'url' => $pageUrl($i),
                'active' => $i === $current,
            ];
        }

        return [
            'current' => $current,
            'last' => $last,
            'has_pages' => $last > 1,
            'prev_url' => $current > 1 ? $pageUrl($current - 1) : '',
            'next_url' => $current < $last ? $pageUrl($current + 1) : '',
            'pages' => $pages,
        ];
    }

    /**
     * @return array<string, scalar|null>
     */
    public static function catalogFilterQuery(Request $request): array
    {
        $out = [];
        foreach (['genre', 'country'] as $key) {
            $value = trim((string)$request->query($key, ''));
            if ($value !== '') {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function mapSeries(iterable $items): array
    {
        return SeriesCardMapper::mapSeries($items);
    }
}
