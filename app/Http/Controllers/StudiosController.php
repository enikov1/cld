<?php

namespace App\Http\Controllers;

use App\Models\Studio;
use App\Services\SeriesCardMapper;
use App\Support\PaginationHelper;
use App\Support\PluralRu;
use App\Support\SiteConfig;
use App\Support\Speedbar;

class StudiosController extends TplController
{
    public function index()
    {
        $studios = Studio::query()
            ->where('is_active', true)
            ->where('is_hidden', false)
            ->withCount(['items as items_count' => function ($query) {
                $query->whereHas('series', function ($q) {
                    $q->where('is_active', true)
                        ->where('is_hidden', false);
                });
            }])
            ->catalogOrder()
            ->limit(SiteConfig::int('studios_index_limit'))
            ->get();

        $studios_list = $studios->map(function (Studio $studio) {
            $itemsCount = (int)($studio->items_count ?? 0);

            return [
                'slug' => $studio->slug,
                'title' => $studio->title,
                'description' => $studio->description ?? '',
                'logo_url' => $studio->logo_url ?? '',
                'items_count' => $itemsCount,
                'items_count_word' => PluralRu::series($itemsCount),
            ];
        })->values()->all();

        $vars = [
            'page' => [
                'heading' => 'Студии',
            ],
            'studios_list' => $studios_list,
            'studios_total' => count($studios_list),
            'studios_total_word' => PluralRu::studios(count($studios_list)),
        ];

        $this->applySpeedbar(Speedbar::forStudiosIndex(), $vars);

        $meta = [
            'title' => 'Студии — каталог сериалов по студиям',
            'description' => 'Сериалы по студиям — смотреть онлайн бесплатно.',
        ];

        return $this->renderTplPage('studios/index.tpl', $vars, $meta);
    }

    public function show(string $slug, int $page = 1)
    {
        $studio = Studio::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->where('is_hidden', false)
            ->firstOrFail();

        $paginator = $studio->items()
            ->with(['series'])
            ->whereHas('series', function ($q) {
                $q->where('is_active', true)
                    ->where('is_hidden', false);
            })
            ->orderBy('rank_order')
            ->paginate(SiteConfig::int('studios_per_page'), ['*'], 'page', max(1, $page));

        $basePath = '/studios/' . $studio->slug;
        $pagination = PaginationHelper::buildMeta($paginator, $basePath);

        $seriesModels = collect($paginator->items())->map(fn ($item) => $item->series)->filter()->values();
        $studio_items = SeriesCardMapper::mapSeries($seriesModels);

        $total = $paginator->total();

        $vars = [
            'studio' => [
                'title' => $studio->title,
                'slug' => $studio->slug,
                'description' => $studio->description ?? '',
                'logo_url' => $studio->logo_url ?? '',
            ],
            'studio_items' => $studio_items,
            'studio_total' => $total,
            'studio_total_word' => PluralRu::series($total),
            'studio_has_items' => $total > 0,
            'studio_seo_html' => $studio->seo_html ?? '',
            'pagination' => $pagination,
            'pagination_block' => $pagination['has_pages']
                ? $this->renderPartial('partials/pagination.tpl', ['pagination' => $pagination])
                : '',
        ];

        $this->applySpeedbar(Speedbar::forStudio($studio, $page), $vars);

        $canonical = $pagination['current'] > 1
            ? url($basePath . '/page/' . $pagination['current'] . '/')
            : url($basePath . '/');

        $metaTitle = trim((string)($studio->meta_title ?? ''));
        if ($metaTitle === '') {
            $metaTitle = $studio->title . ' — студия';
        }

        $metaDescription = trim((string)($studio->meta_description ?? ''));
        if ($metaDescription === '') {
            $metaDescription = trim((string)($studio->description ?? ''));
        }
        if ($metaDescription === '') {
            $metaDescription = 'Сериалы студии «' . $studio->title . '» — смотреть онлайн бесплатно';
        }

        $meta = [
            'title' => $metaTitle,
            'description' => $metaDescription,
            'canonical' => $canonical,
            'prev' => $pagination['prev_url'] ? url($pagination['prev_url']) : '',
            'next' => $pagination['next_url'] ? url($pagination['next_url']) : '',
            'robots' => ($studio->noindex || $page > 1) ? 'noindex,follow' : '',
        ];

        return $this->renderTplPage('studios/show.tpl', $vars, $meta);
    }
}
