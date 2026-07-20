<?php

namespace App\Http\Controllers;

use App\Models\Collection;
use App\Models\Series;
use App\Models\Studio;
use App\Services\HomeBlockService;
use App\Services\HomeSectionService;
use App\Support\CatalogFilterService;
use App\Support\PaginationHelper;
use App\Support\PluralRu;
use App\Support\SiteConfig;
use App\Support\Speedbar;
use Illuminate\Http\Request;

class HomeController extends TplController
{
    public function index(Request $request, int $page = 1)
    {
        $page = max(1, $page);

        if ($page === 1) {
            return $this->renderFirstPage();
        }

        return $this->renderCatalogPage($page);
    }

    private function renderFirstPage()
    {
        $popular = Series::query()
            ->published();
        HomeSectionService::applySort($popular, HomeSectionService::SORT_POPULAR);
        $popular = $popular
            ->limit(SiteConfig::int('home_popular_limit'))
            ->get();

        $promoCollections = Collection::query()
            ->catalogOrder()
            ->where('is_active', true)
            ->where('is_hidden', false)
            ->where('show_on_home', true)
            ->limit(2)
            ->get()
            ->map(fn (Collection $c) => [
                'slug' => $c->slug,
                'title' => $c->title,
                'cover_url' => $c->cover_url ?? '',
                'banner_url' => $c->home_banner_url ?: ($c->cover_url ?? ''),
                'url' => route('collections.show', ['slug' => $c->slug]),
            ])
            ->all();

        $sortMode = SiteConfig::str('home_studios_sort');
        $promoStudiosQuery = Studio::query()
            ->where('is_active', true)
            ->where('is_hidden', false)
            ->withCount(['items as items_count' => function ($query) {
                $query->whereHas('series', function ($q) {
                    $q->where('is_active', true)
                        ->where('is_hidden', false);
                });
            }])
            ;

        $promoStudiosQuery = match ($sortMode) {
            'items_desc' => $promoStudiosQuery
                ->orderByDesc('items_count')
                ->orderByDesc('is_pinned')
                ->orderBy('sort_order')
                ->orderByDesc('id'),
            'items_asc' => $promoStudiosQuery
                ->orderBy('items_count')
                ->orderByDesc('is_pinned')
                ->orderBy('sort_order')
                ->orderByDesc('id'),
            'title_asc' => $promoStudiosQuery
                ->orderBy('title')
                ->orderByDesc('is_pinned')
                ->orderBy('sort_order')
                ->orderByDesc('id'),
            'title_desc' => $promoStudiosQuery
                ->orderByDesc('title')
                ->orderByDesc('is_pinned')
                ->orderBy('sort_order')
                ->orderByDesc('id'),
            default => $promoStudiosQuery->catalogOrder(),
        };

        $promoStudios = $promoStudiosQuery
            ->limit(SiteConfig::int('home_studios_limit'))
            ->get()
            ->map(function (Studio $studio) {
                $itemsCount = (int)($studio->items_count ?? 0);

                return [
                    'slug' => $studio->slug,
                    'title' => $studio->title,
                    'description' => $studio->description ?? '',
                    'logo_url' => $studio->logo_url ?? '',
                    'url' => route('studios.show', ['slug' => $studio->slug]),
                    'items_count' => $itemsCount,
                    'items_count_word' => PluralRu::series($itemsCount),
                ];
            })
            ->all();

        $renderCards = fn (array $mapped) => $this->renderPartial('partials/series_cards.tpl', ['series_list' => $mapped]);

        $customSections = HomeBlockService::mapBlocksForHome(
            HomeBlockService::activeBlocks(),
            $renderCards,
        );

        $sections = HomeSectionService::mapSectionsForHome(
            HomeSectionService::activeSections(),
            $renderCards,
        );

        $popularMapped = PaginationHelper::mapSeries($popular);

        $vars = [
            'has_watch_history' => SiteConfig::bool('watch_history_enabled'),
            'is_home_first' => true,
            'popular_list' => $popularMapped,
            'popular_cards_html' => $this->renderPartial('partials/series_cards.tpl', ['series_list' => $popularMapped]),
            'promo_collections' => $promoCollections,
            'promo_studios' => $promoStudios,
            'custom_home_sections' => $customSections,
            'home_sections' => $sections,
            'category_sections' => $sections,
            'home_seo_html' => \App\Models\SiteSetting::get('home_seo_html', $this->defaultSeoHtml()),
            'pagination_block' => '',
        ];

        $this->applySpeedbar(Speedbar::forHome(1), $vars);

        $meta = [
            'title' => SiteConfig::str('home_meta_title'),
            'description' => SiteConfig::str('home_meta_description'),
            'canonical' => url('/'),
        ];

        return $this->renderTplPage('home.tpl', $vars, $meta);
    }

    private function renderCatalogPage(int $page)
    {
        $paginator = CatalogFilterService::paginateCatalog([], $page, request());

        $pagination = CatalogFilterService::buildPaginationMeta($paginator, '/', [], false);

        $vars = [
            'is_home_first' => false,
            'browse_api_path' => '/api/catalog/browse',
            'page' => [
                'heading' => \App\Models\SiteSetting::get('home_heading', 'Сериалы онлайн'),
            ],
            'series_list' => PaginationHelper::mapSeries($paginator->items()),
            'pagination' => $pagination,
            'pagination_block' => $pagination['has_pages']
                ? $this->renderPartial('partials/pagination.tpl', ['pagination' => $pagination])
                : '',
        ];

        $this->applySpeedbar(Speedbar::forHome($page), $vars);

        $meta = [
            'title' => SiteConfig::str('home_meta_title'),
            'description' => SiteConfig::str('home_meta_description'),
            'canonical' => url('/page/' . $pagination['current'] . '/'),
            'prev' => $pagination['prev_url'] ? url($pagination['prev_url']) : '',
            'next' => $pagination['next_url'] ? url($pagination['next_url']) : '',
        ];

        return $this->renderTplPage('home.tpl', $vars, $meta);
    }

    private function defaultSeoHtml(): string
    {
        return <<<'HTML'
<h1>Только лучшие сериалы онлайн</h1>
<p>Вы привыкли проводить свободное время за просмотром сериалов и постоянно ищете что-то увлекательное? Тогда добро пожаловать на наш сайт — здесь вы найдёте огромный выбор зарубежных сериалов, мультсериалов и аниме.</p>
<p>Мы поможем вам наслаждаться просмотром в любое удобное время: высокое качество изображения, профессиональное озвучивание и удобный каталог по жанрам и подборкам.</p>
HTML;
    }
}
