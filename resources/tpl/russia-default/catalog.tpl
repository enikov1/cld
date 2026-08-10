[meta-title][page.heading]{page.heading} — смотреть онлайн бесплатно[/page.heading][not-page.heading]{seo.title}[/not-page.heading][/meta-title]
[meta-description][page.lead]{page.lead}[/page.lead][not-page.lead]{seo.description}[/not-page.lead][/meta-description]
[meta-canonical]{seo.canonical|raw}[/meta-canonical]
[meta-prev]{seo.prev|raw}[/meta-prev]
[meta-next]{seo.next|raw}[/meta-next]
[meta-robots]{seo.robots|raw}[/meta-robots]

<main class="main clearfix fx-col grid-list" id="grid">
    <div class="catalog-root" id="catalogRoot" data-total="{catalog_total}" data-browse-api="{browse_api_path|raw}" [is_taxonomy_page] data-taxonomy-type="{taxonomy_type|raw}" data-taxonomy-slug="{taxonomy_slug|raw}" [/is_taxonomy_page]>
        [speedbar_block]
        <div class="speedbar nowrap">{speedbar_block|raw}</div>
        [/speedbar_block]

        [catalog_filters_block]
        <div data-catalog-filters-wrap>{catalog_filters_block|raw}</div>
        [/catalog_filters_block]

        [ad_catalog_grid_code]
        <div class="ad-catalog-grid">{ad_catalog_grid_code|raw}</div>
        [/ad_catalog_grid_code]

        <div class="shorts-header flex-row">
            <div class="grid-select clearfix" id="grid-select" data-name="Переключить вид">
                <div data-type="grid-list" class="current"><span class="fa fa-reorder"></span></div>
                <div data-type="grid-thumb"><span class="fa fa-th"></span></div>
            </div>
            <h1>{page.heading}</h1>
            <span class="catalog-results-count" data-catalog-count hidden aria-live="polite">
                <span class="catalog-results-count__num" data-catalog-count-num></span>
                <span class="catalog-results-count__text" data-catalog-count-text></span>
            </span>
        </div>

        <div data-catalog-grid-wrap>
            {catalog_series_grid|raw}
        </div>
        <div data-catalog-pagination-wrap>{pagination_block|raw}</div>

        [category_seo_html]
        <div class="site-desc clearfix">{category_seo_html|raw}</div>
        [/category_seo_html]
    </div>
</main>
