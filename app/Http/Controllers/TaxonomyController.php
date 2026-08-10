<?php

namespace App\Http\Controllers;

use App\Models\Country;
use App\Models\Genre;
use App\Models\Person;
use App\Models\Year;
use App\Support\CatalogFilterService;
use App\Support\PaginationHelper;
use App\Support\Speedbar;
use App\Support\TaxonomyRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class TaxonomyController extends TplController
{
    private const ROUTE_TYPES = [
        'genre' => ['model' => Genre::class, 'filter' => 'genre'],
        'country' => ['model' => Country::class, 'filter' => 'country'],
        'person' => ['model' => Person::class, 'filter' => 'actor'],
        'year' => ['model' => Year::class, 'filter' => 'year'],
    ];

    public function showYear(Request $request, string $slug, int $page = 1)
    {
        return $this->showTaxonomy($request, 'year', $slug, $page);
    }

    public function browseYear(Request $request, string $slug)
    {
        return $this->browseTaxonomy($request, 'year', $slug);
    }

    public function showGenre(Request $request, string $slug, int $page = 1)
    {
        return $this->showTaxonomy($request, 'genre', $slug, $page);
    }

    public function showCountry(Request $request, string $slug, int $page = 1)
    {
        return $this->showTaxonomy($request, 'country', $slug, $page);
    }

    public function showPerson(Request $request, string $slug, int $page = 1)
    {
        return $this->showTaxonomy($request, 'person', $slug, $page);
    }

    public function browseGenre(Request $request, string $slug)
    {
        return $this->browseTaxonomy($request, 'genre', $slug);
    }

    public function browseCountry(Request $request, string $slug)
    {
        return $this->browseTaxonomy($request, 'country', $slug);
    }

    public function browsePerson(Request $request, string $slug)
    {
        return $this->browseTaxonomy($request, 'person', $slug);
    }

    private function showTaxonomy(Request $request, string $type, string $slug, int $page = 1)
    {
        $config = $this->typeConfig($type);
        $item = $this->findPublicItem($config['model'], $slug);

        $page = max(1, $page);
        $filters = CatalogFilterService::parse($request);
        $this->applyTaxonomyFilters($filters, $type, $item->slug, $config['filter']);

        $paginationFilters = $filters;
        $this->removeTaxonomyPrimaryFilters($paginationFilters, $type, $config['filter']);

        $paginator = CatalogFilterService::paginateTaxonomy($filters, $page, $request);
        $basePath = '/' . $type . '/' . $item->slug;
        $pagination = CatalogFilterService::buildPaginationMeta($paginator, $basePath, $paginationFilters, true);

        $filterVars = CatalogFilterService::buildFilterVars(
            $filters,
            fn (string $tpl, array $vars) => $this->renderPartial($tpl, $vars),
        );
        $filterVars['taxonomy_type'] = $type;
        $filterVars['taxonomy_slug'] = $item->slug;
        $filterVars['browse_api_path'] = '/api/taxonomy/' . $type . '/' . $item->slug . '/browse';

        $vars = [
            'is_home_first' => false,
            'is_taxonomy_page' => true,
            'taxonomy_type' => $type,
            'taxonomy_slug' => $item->slug,
            'page' => [
                'heading' => TaxonomyRegistry::displayName($item),
                'lead' => $item->meta_description ?? '',
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
            'category_seo_html' => $item->seo_html ?? '',
            'browse_api_path' => '/api/taxonomy/' . $type . '/' . $item->slug . '/browse',
        ];

        $this->applySpeedbar(Speedbar::forTaxonomy($type, $item, $page), $vars);

        $canonical = $pagination['current'] > 1
            ? url($basePath . '/page/' . $pagination['current'] . '/')
            : url($basePath . '/');

        $extraFilters = $filters;
        $this->removeTaxonomyPrimaryFilters($extraFilters, $type, $config['filter']);
        if ($extraFilters) {
            $canonical .= '?' . http_build_query($extraFilters);
        }

        $metaTitle = trim((string)($item->meta_title ?? ''));
        if ($metaTitle === '') {
            $metaTitle = TaxonomyRegistry::displayName($item) . ' — смотреть онлайн бесплатно';
        }

        $metaDescription = trim((string)($item->meta_description ?? ''));
        if ($metaDescription === '') {
            $metaDescription = 'Сериалы: ' . TaxonomyRegistry::displayName($item);
        }

        $meta = [
            'title' => $metaTitle,
            'description' => $metaDescription,
            'canonical' => $canonical,
            'prev' => $pagination['prev_url'] ? url($pagination['prev_url']) : '',
            'next' => $pagination['next_url'] ? url($pagination['next_url']) : '',
            'robots' => $this->robotsMeta($item, $page),
        ];

        return $this->renderTplPage('catalog.tpl', $vars, $meta);
    }

    private function browseTaxonomy(Request $request, string $type, string $slug)
    {
        $config = $this->typeConfig($type);
        $item = $this->findPublicItem($config['model'], $slug);

        $page = max(1, (int)$request->query('page', 1));
        $filters = CatalogFilterService::parse($request);
        $this->applyTaxonomyFilters($filters, $type, $item->slug, $config['filter']);

        $paginator = CatalogFilterService::paginateTaxonomy($filters, $page, $request);
        $basePath = '/' . $type . '/' . $item->slug;
        $paginationFilters = $filters;
        $this->removeTaxonomyPrimaryFilters($paginationFilters, $type, $config['filter']);
        $pagination = CatalogFilterService::buildPaginationMeta($paginator, $basePath, $paginationFilters, true);

        $filterVars = CatalogFilterService::buildFilterVars(
            $filters,
            fn (string $tpl, array $vars) => $this->renderPartial($tpl, $vars),
        );
        $filterVars['taxonomy_type'] = $type;
        $filterVars['taxonomy_slug'] = $item->slug;
        $filterVars['browse_api_path'] = '/api/taxonomy/' . $type . '/' . $item->slug . '/browse';

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

    /**
     * @param class-string<Model> $modelClass
     */
    private function findPublicItem(string $modelClass, string $slug): Model
    {
        return $modelClass::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->where('is_hidden', false)
            ->firstOrFail();
    }

    /**
     * @return array{model: class-string<Model>, filter: string}
     */
    private function typeConfig(string $type): array
    {
        if (!isset(self::ROUTE_TYPES[$type])) {
            abort(404);
        }

        return self::ROUTE_TYPES[$type];
    }

    private function robotsMeta(Model $item, int $page): string
    {
        if ($item->noindex || $page > 1) {
            return 'noindex,follow';
        }

        return '';
    }

    /**
     * @param array<string, string> $filters
     */
    private function applyTaxonomyFilters(array &$filters, string $type, string $slug, string $filterKey): void
    {
        if ($type === 'year') {
            $filters['year_from'] = $slug;
            $filters['year_to'] = $slug;

            return;
        }

        $filters[$filterKey] = $slug;
    }

    /**
     * @param array<string, string> $filters
     */
    private function removeTaxonomyPrimaryFilters(array &$filters, string $type, string $filterKey): void
    {
        if ($type === 'year') {
            unset($filters['year_from'], $filters['year_to']);

            return;
        }

        unset($filters[$filterKey]);
    }
}
