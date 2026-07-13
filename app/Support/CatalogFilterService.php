<?php

namespace App\Support;

use App\Services\HomeSectionService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class CatalogFilterService
{
    /**
     * @return array<string, string>
     */
    public static function parse(Request $request): array
    {
        return CatalogFilterRegistry::parse($request);
    }

    /**
     * @param array<string, string> $filters
     */
    public static function applyToQuery(Builder $query, array $filters): Builder
    {
        return CatalogFilterRegistry::applyToQuery($query, $filters);
    }

    /**
     * @param array<string, string> $filters
     */
    public static function catalogQuery(array $filters, ?Request $request = null): Builder
    {
        $request = $request ?? request();

        $query = \App\Models\Series::query()
            ->published()
            ->where('is_hidden', false);

        CatalogFilterRegistry::applyCatalogSorts($query, CatalogFilterRegistry::parseSorts($request));

        return self::applyToQuery($query, $filters);
    }

    /**
     * @param array<string, string> $filters
     */
    public static function paginateCatalog(array $filters, int $page, ?Request $request = null): LengthAwarePaginator
    {
        return self::catalogQuery($filters, $request)
            ->paginate(SiteConfig::int('catalog_per_page'), ['*'], 'page', max(1, $page));
    }

    /**
     * @param array<string, string> $filters
     */
    public static function taxonomyQuery(array $filters, ?Request $request = null): Builder
    {
        return self::catalogQuery($filters, $request);
    }

    /**
     * @param array<string, string> $filters
     */
    public static function paginateTaxonomy(array $filters, int $page, ?Request $request = null): LengthAwarePaginator
    {
        return self::paginateCatalog($filters, $page, $request);
    }

    /**
     * @param array<string, string> $filters
     * @param callable(string, array<string, mixed>): string $renderPartial
     * @return array<string, mixed>
     */
    public static function buildFilterVars(array $filters, callable $renderPartial): array
    {
        return CatalogFilterRegistry::buildViewData($filters, $renderPartial);
    }

    /**
     * @param array<string, string> $filters
     * @return array<string, mixed>
     */
    public static function buildPaginationMeta(
        LengthAwarePaginator $paginator,
        string $basePath,
        array $filters = [],
        bool $includeFiltersInUrl = false,
    ): array {
        $queryParams = $includeFiltersInUrl ? $filters : [];

        foreach (CatalogFilterRegistry::sortFieldKeys() as $key) {
            if (($queryParams[$key] ?? 'desc') === 'desc') {
                unset($queryParams[$key]);
            }
        }

        return PaginationHelper::buildMeta($paginator, $basePath, $queryParams);
    }
}
