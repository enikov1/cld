<?php

namespace App\Http\Controllers;

use App\Models\Series;
use App\Support\PaginationHelper;
use App\Support\QuickSearchService;
use App\Support\SearchQueryStatService;
use App\Support\SiteConfig;
use App\Support\Speedbar;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SearchController extends TplController
{
    public function suggest(Request $request): JsonResponse
    {
        $query = trim((string)$request->query('q', ''));
        $result = QuickSearchService::suggest($query);

        if ($query !== '') {
            $resultsCount = self::countSuggestResults($result['groups']);
            SearchQueryStatService::tryRecord(
                $request,
                $query,
                'suggest',
                $resultsCount > 0,
                $resultsCount
            );
        }

        return response()->json($result);
    }

    public function search(Request $request, int $page = 1)
    {
        $q = trim((string)$request->query('q', ''));
        $page = max(1, $page);

        $series = collect();
        $taxonomyGroups = [];
        $pagination = [
            'current' => 1,
            'last' => 1,
            'has_pages' => false,
            'prev_url' => '',
            'next_url' => '',
            'pages' => [],
        ];

        if ($q !== '') {
            $paginator = Series::query()
                ->where('is_active', true)
                ->where('is_hidden', false)
                ->where(function ($query) use ($q) {
                    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
                    $query->where('title', 'like', $like)
                        ->orWhere('title_en', 'like', $like)
                        ->orWhere('title_original', 'like', $like)
                        ->orWhere('description', 'like', $like)
                        ->orWhere('short_description', 'like', $like);
                })
                ->orderByDesc('id')
                ->paginate(SiteConfig::int('search_per_page'), ['*'], 'page', $page);

            $pagination = PaginationHelper::buildMeta($paginator, '/search', ['q' => $q]);
            $series = $paginator->items();
            $taxonomyGroups = QuickSearchService::taxonomyGroups($q);
        }

        $seriesList = PaginationHelper::mapSeries($series);
        $hasResults = $seriesList !== [] || $taxonomyGroups !== [];

        if ($q !== '' && $page === 1) {
            $resultsCount = count($seriesList);
            foreach ($taxonomyGroups as $group) {
                $resultsCount += count($group['items'] ?? []);
            }
            SearchQueryStatService::tryRecord($request, $q, 'full', $hasResults, $resultsCount);
        }

        $vars = [
            'query' => $q,
            'series_list' => $seriesList,
            'taxonomy_groups' => $taxonomyGroups,
            'has_results' => $hasResults,
            'popular_searches' => SearchQueryStatService::popular(),
            'pagination' => $pagination,
            'pagination_block' => $pagination['has_pages']
                ? $this->renderPartial('partials/pagination.tpl', ['pagination' => $pagination])
                : '',
        ];

        $this->applySpeedbar(Speedbar::forSearch($q, $page), $vars);

        $queryParams = $q !== '' ? ['q' => $q] : [];
        $canonical = $pagination['current'] > 1
            ? url('/search/page/' . $pagination['current'] . '/' . ($queryParams ? '?' . http_build_query($queryParams) : ''))
            : url('/search' . ($queryParams ? '?' . http_build_query($queryParams) : ''));

        $meta = [
            'title' => $q !== '' ? ('Поиск: ' . $q) : 'Поиск по сериалам',
            'description' => $q !== ''
                ? ('Результаты поиска по запросу «' . $q . '»')
                : 'Поиск сериалов и фильмов по названию и описанию',
            'canonical' => $canonical,
            'prev' => $pagination['prev_url'] ? url($pagination['prev_url']) : '',
            'next' => $pagination['next_url'] ? url($pagination['next_url']) : '',
            'robots' => $page > 1 ? 'noindex,follow' : '',
        ];

        return $this->renderTplPage('search.tpl', $vars, $meta);
    }

    /**
     * @param list<array{type: string, label: string, items: list<array<string, string>>}> $groups
     */
    private static function countSuggestResults(array $groups): int
    {
        $count = 0;
        foreach ($groups as $group) {
            $count += count($group['items'] ?? []);
        }

        return $count;
    }
}
