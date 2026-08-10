<?php

namespace App\Http\Controllers;

use App\Support\CatalogFilterService;
use App\Support\PaginationHelper;
use App\Support\SiteConfig;
use App\Support\Speedbar;
use Illuminate\Http\Request;

class CatalogController extends TplController
{
    public function index(Request $request, int $page = 1)
    {
        $page = max(1, $page);
        $filters = CatalogFilterService::parse($request);

        $paginator = CatalogFilterService::paginateCatalog($filters, $page, $request);
        $basePath = '/catalog';
        $pagination = CatalogFilterService::buildPaginationMeta($paginator, $basePath, $filters, true);

        $filterVars = CatalogFilterService::buildFilterVars(
            $filters,
            fn (string $tpl, array $vars) => $this->renderPartial($tpl, $vars),
        );
        $filterVars['browse_api_path'] = '/api/catalog/browse';

        $heading = SiteConfig::str('catalog_heading');

        $vars = [
            'is_home_first' => false,
            'page' => [
                'heading' => $heading,
            ],
            'series_list' => PaginationHelper::mapSeries($paginator->items()),
            'pagination' => $pagination,
            'pagination_block' => $pagination['has_pages']
                ? $this->renderPartial('partials/pagination.tpl', ['pagination' => $pagination])
                : '',
            'catalog_filters' => $filterVars,
            'catalog_filters_block' => $this->renderPartial('partials/catalog_filters.tpl', $filterVars),
            'catalog_series_grid' => $this->renderPartial('partials/catalog_series_grid.tpl', [
                'series_list' => PaginationHelper::mapSeries($paginator->items()),
            ]),
            'catalog_total' => $paginator->total(),
            'browse_api_path' => '/api/catalog/browse',
        ];

        $this->applySpeedbar(Speedbar::forCatalog($page), $vars);

        $canonical = $pagination['current'] > 1
            ? url($basePath . '/page/' . $pagination['current'] . '/')
            : url($basePath . '/');

        if ($filters) {
            $canonical .= '?' . http_build_query($filters);
        }

        $meta = [
            'title' => SiteConfig::str('catalog_meta_title'),
            'description' => SiteConfig::str('catalog_meta_description'),
            'canonical' => $canonical,
            'prev' => $pagination['prev_url'] ? url($pagination['prev_url']) : '',
            'next' => $pagination['next_url'] ? url($pagination['next_url']) : '',
            'robots' => $page > 1 ? 'noindex,follow' : '',
        ];

        return $this->renderTplPage('catalog.tpl', $vars, $meta);
    }

    public function browse(Request $request)
    {
        $page = max(1, (int)$request->query('page', 1));
        $filters = CatalogFilterService::parse($request);
        $paginator = CatalogFilterService::paginateCatalog($filters, $page, $request);

        $pagination = CatalogFilterService::buildPaginationMeta($paginator, '/catalog', $filters, true);
        $filterVars = CatalogFilterService::buildFilterVars(
            $filters,
            fn (string $tpl, array $vars) => $this->renderPartial($tpl, $vars),
        );
        $filterVars['browse_api_path'] = '/api/catalog/browse';

        $seriesList = PaginationHelper::mapSeries($paginator->items());

        return response()->json([
            'ok' => true,
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'grid_html' => $this->renderPartial('partials/catalog_series_grid.tpl', [
                'series_list' => $seriesList,
            ]),
            'pagination_html' => $pagination['has_pages']
                ? $this->renderPartial('partials/pagination.tpl', ['pagination' => $pagination])
                : '',
            'filters_html' => $this->renderPartial('partials/catalog_filters.tpl', $filterVars),
        ]);
    }
}
