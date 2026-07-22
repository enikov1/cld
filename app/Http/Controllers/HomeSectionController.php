<?php

namespace App\Http\Controllers;

use App\Models\HomeSection;
use App\Services\HomeBlockService;
use App\Services\HomeContentTypeSectionService;
use App\Services\HomeSectionService;
use App\Support\ContentTypes;
use App\Support\SiteConfig;
use App\Support\PaginationHelper;
use App\Support\TaxonomyRegistry;
use Illuminate\Http\Request;

class HomeSectionController extends TplController
{
    public function blockSeries(Request $request, int $id)
    {
        $block = HomeSection::query()
            ->where('is_active', true)
            ->findOrFail($id);

        $sort = HomeSectionService::normalizeSort(
            $request->query('sort', $block->default_sort ?? HomeSectionService::SORT_LATEST)
        );

        $items = HomeBlockService::seriesForBlock($block, $sort);
        $mapped = PaginationHelper::mapSeries($items);

        return response()->json([
            'html' => $this->renderPartial('partials/series_cards.tpl', [
                'series_list' => $mapped,
            ]),
            'count' => count($mapped),
            'sort' => $sort,
        ]);
    }

    public function contentTypeSeries(Request $request, string $contentType)
    {
        if (!ContentTypes::isValid($contentType) || !SiteConfig::bool('home_content_types_enabled')) {
            abort(404);
        }

        $sort = HomeSectionService::normalizeSort(
            $request->query('sort', SiteConfig::str('home_content_types_sort'))
        );

        $items = HomeContentTypeSectionService::seriesForContentType($contentType, $sort);
        $mapped = PaginationHelper::mapSeries($items);

        return response()->json([
            'html' => $this->renderPartial('partials/series_cards.tpl', [
                'series_list' => $mapped,
            ]),
            'count' => count($mapped),
            'sort' => $sort,
        ]);
    }

    public function series(Request $request, string $type, int $id)
    {
        if (!TaxonomyRegistry::isValidType($type)) {
            abort(404);
        }

        $item = TaxonomyRegistry::findPublicItem($type, $id);
        if (!(bool)($item->show_on_home ?? false)) {
            abort(404);
        }

        $sort = HomeSectionService::normalizeSort(
            $request->query('sort', $item->home_default_sort ?? HomeSectionService::SORT_LATEST)
        );

        $items = HomeSectionService::seriesForTaxonomy($type, $item, $sort);
        $mapped = PaginationHelper::mapSeries($items);

        return response()->json([
            'html' => $this->renderPartial('partials/series_cards.tpl', [
                'series_list' => $mapped,
            ]),
            'count' => count($mapped),
            'sort' => $sort,
        ]);
    }
}
