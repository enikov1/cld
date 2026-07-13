<?php

namespace App\Http\Controllers;

use App\Models\Collection;
use App\Services\SeriesCardMapper;
use App\Support\PaginationHelper;
use App\Support\PluralRu;
use App\Support\SiteConfig;
use App\Support\Speedbar;

class CollectionsController extends TplController
{
    public function index()
    {
        $collections = Collection::query()
            ->where('is_active', true)
            ->where('is_hidden', false)
            ->orderByDesc('id')
            ->limit(SiteConfig::int('collections_index_limit'))
            ->get();

        $collections_list = $collections->map(function (Collection $c) {
            return [
                'slug' => $c->slug,
                'title' => $c->title,
                'cover_url' => $c->cover_url ?? '',
                'source_updated_at' => $c->source_updated_at?->format('d.m.Y H:i'),
            ];
        })->values()->all();

        $vars = [
            'page' => [
                'heading' => 'Подборки сериалов',
            ],
            'collections_list' => $collections_list,
            'collections_total' => count($collections_list),
            'collections_total_word' => PluralRu::series(count($collections_list)),
        ];

        $this->applySpeedbar(Speedbar::forCollectionsIndex(), $vars);

        $meta = [
            'title' => 'Подборки сериалов по жанрам и тематикам смотреть онлайн бесплатно',
            'description' => 'Подборки сериалов по тематикам — смотреть онлайн бесплатно.',
        ];

        return $this->renderTplPage('collections/index.tpl', $vars, $meta);
    }

    public function show(string $slug, int $page = 1)
    {
        $collection = Collection::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->where('is_hidden', false)
            ->firstOrFail();

        $paginator = $collection->items()
            ->with(['series'])
            ->whereHas('series', function ($q) {
                $q->where('is_active', true)
                    ->where('is_hidden', false);
            })
            ->orderBy('rank_order')
            ->paginate(SiteConfig::int('collections_per_page'), ['*'], 'page', max(1, $page));

        $basePath = '/collections/' . $collection->slug;
        $pagination = PaginationHelper::buildMeta($paginator, $basePath);

        $seriesModels = collect($paginator->items())->map(fn ($item) => $item->series)->filter()->values();
        $collection_items = SeriesCardMapper::mapSeries($seriesModels);

        $total = $paginator->total();

        $vars = [
            'collection' => [
                'title' => $collection->title,
                'slug' => $collection->slug,
                'description' => $collection->description ?? '',
                'source_updated_at' => $collection->source_updated_at?->format('d.m.Y H:i'),
            ],
            'collection_items' => $collection_items,
            'collection_total' => $total,
            'collection_total_word' => PluralRu::series($total),
            'collection_has_items' => $total > 0,
            'collection_seo_html' => $collection->seo_html ?? '',
            'pagination' => $pagination,
            'pagination_block' => $pagination['has_pages']
                ? $this->renderPartial('partials/pagination.tpl', ['pagination' => $pagination])
                : '',
        ];

        $this->applySpeedbar(Speedbar::forCollection($collection, $page), $vars);

        $canonical = $pagination['current'] > 1
            ? url($basePath . '/page/' . $pagination['current'] . '/')
            : url($basePath . '/');

        $metaTitle = trim((string)($collection->meta_title ?? ''));
        if ($metaTitle === '') {
            $metaTitle = $collection->title . ' — подборка сериалов';
        }

        $metaDescription = trim((string)($collection->meta_description ?? ''));
        if ($metaDescription === '') {
            $metaDescription = trim((string)($collection->description ?? ''));
        }
        if ($metaDescription === '') {
            $metaDescription = 'Сериалы из подборки «' . $collection->title . '» — смотреть онлайн бесплатно';
        }

        $meta = [
            'title' => $metaTitle,
            'description' => $metaDescription,
            'canonical' => $canonical,
            'prev' => $pagination['prev_url'] ? url($pagination['prev_url']) : '',
            'next' => $pagination['next_url'] ? url($pagination['next_url']) : '',
            'robots' => ($collection->noindex || $page > 1) ? 'noindex,follow' : '',
        ];

        return $this->renderTplPage('collections/show.tpl', $vars, $meta);
    }
}
