<?php

namespace App\Http\Controllers;

use App\Models\Series;
use App\Services\AnticipationService;
use App\Support\PaginationHelper;
use App\Support\SiteConfig;
use App\Support\Speedbar;
use Illuminate\Http\Request;

class ComingSoonController extends TplController
{
    public function index(Request $request, int $page = 1)
    {
        $sort = AnticipationService::normalizeSort($request->query('sort'));
        $page = max(1, $page);

        $paginator = AnticipationService::applySort(AnticipationService::comingSoonQuery(), $sort)
            ->paginate(SiteConfig::int('coming_soon_per_page'), ['*'], 'page', $page);

        $basePath = '/skoro/';
        $pagination = PaginationHelper::buildMeta($paginator, $basePath, ['sort' => $sort !== AnticipationService::SORT_MOST ? $sort : null]);

        $rankBase = ($paginator->currentPage() - 1) * $paginator->perPage();
        $items = [];
        foreach ($paginator->items() as $index => $series) {
            $items[] = AnticipationService::mapCard($series, $rankBase + $index + 1, $request);
        }

        $vars = [
            'page' => [
                'heading' => 'Самые ожидаемые премьеры',
            ],
            'sort' => $sort,
            'sort_most_active' => $sort === AnticipationService::SORT_MOST,
            'sort_least_active' => $sort === AnticipationService::SORT_LEAST,
            'sort_release_active' => $sort === AnticipationService::SORT_RELEASE,
            'sort_most_url' => $this->sortUrl(AnticipationService::SORT_MOST),
            'sort_least_url' => $this->sortUrl(AnticipationService::SORT_LEAST),
            'sort_release_url' => $this->sortUrl(AnticipationService::SORT_RELEASE),
            'coming_soon_items' => $items,
            'coming_soon_cards_html' => $this->renderPartial('partials/coming_soon_cards.tpl', [
                'coming_soon_items' => $items,
            ]),
            'pagination' => $pagination,
            'pagination_block' => $pagination['has_pages']
                ? $this->renderPartial('partials/pagination.tpl', ['pagination' => $pagination])
                : '',
            'coming_soon_total' => $paginator->total(),
        ];

        $this->applySpeedbar(Speedbar::forComingSoon($page), $vars);

        $meta = [
            'title' => 'Скоро — самые ожидаемые премьеры сериалов',
            'description' => 'Рейтинг ожидания новых сериалов и премьер. Отметьте, что будете смотреть, и помогите сформировать топ.',
            'canonical' => $page > 1 ? url('/skoro/page/' . $page . '/') : url('/skoro/'),
            'prev' => $pagination['prev_url'] ? url($pagination['prev_url']) : '',
            'next' => $pagination['next_url'] ? url($pagination['next_url']) : '',
        ];

        return $this->renderTplPage('coming_soon/index.tpl', $vars, $meta);
    }

    public function browse(Request $request)
    {
        $sort = AnticipationService::normalizeSort($request->query('sort'));
        $page = max(1, (int)$request->query('page', 1));

        $paginator = AnticipationService::applySort(AnticipationService::comingSoonQuery(), $sort)
            ->paginate(SiteConfig::int('coming_soon_per_page'), ['*'], 'page', $page);

        $rankBase = ($paginator->currentPage() - 1) * $paginator->perPage();
        $items = [];
        foreach ($paginator->items() as $index => $series) {
            $items[] = AnticipationService::mapCard($series, $rankBase + $index + 1, $request);
        }

        $basePath = '/skoro/';
        $pagination = PaginationHelper::buildMeta($paginator, $basePath, ['sort' => $sort !== AnticipationService::SORT_MOST ? $sort : null]);

        return response()->json([
            'ok' => true,
            'html' => $this->renderPartial('partials/coming_soon_cards.tpl', [
                'coming_soon_items' => $items,
            ]),
            'pagination_html' => $pagination['has_pages']
                ? $this->renderPartial('partials/pagination.tpl', ['pagination' => $pagination])
                : '',
            'total' => $paginator->total(),
        ]);
    }

    private function sortUrl(string $sort): string
    {
        if ($sort === AnticipationService::SORT_MOST) {
            return '/skoro/';
        }

        return '/skoro/?sort=' . rawurlencode($sort);
    }
}
