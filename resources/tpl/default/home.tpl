[meta-title][is_home_first]{seo.title}[/is_home_first][not-is_home_first][page.heading]{page.heading} — смотреть онлайн бесплатно[/page.heading][not-page.heading]{seo.title}[/not-page.heading][/not-is_home_first][/meta-title] [meta-description][page.lead]{page.lead}[/page.lead][not-page.lead]{seo.description}[/not-page.lead][/meta-description]
[meta-canonical]{seo.canonical|raw}[/meta-canonical] [meta-prev]{seo.prev|raw}[/meta-prev] [meta-next]{seo.next|raw}[/meta-next] [meta-robots]{seo.robots|raw}[/meta-robots]

<main class="main">
    [is_home_first] [popular_list]
    <div class="carou-sect">
        <div class="carou-title">Популярное за месяц</div>
        <div class="carou-content" data-carou>
            <button class="carou-nav carou-nav--prev dontusebuttonclass" type="button" aria-label="Назад">
                        <span class="fa fa-angle-left"></span>
                    </button>
            <div class="carou-track" id="owl-popular">
                {popular_cards_html|raw}
            </div>
            <button class="carou-nav carou-nav--next dontusebuttonclass" type="button" aria-label="Вперёд">
                        <span class="fa fa-angle-right"></span>
                    </button>
        </div>
    </div>
    [/popular_list] {new_episodes_block|raw} {schedule_calendar_block|raw} [has_watch_history]
    <div class="carou-sect" id="watchHistorySection" data-watch-history-root hidden>
        <div class="carou-title">{watch_history_ui_title}</div>
        <div class="carou-content" data-carou>
            <button class="carou-nav carou-nav--prev dontusebuttonclass" type="button" aria-label="Назад">
                        <span class="fa fa-angle-left"></span>
                    </button>
            <div class="carou-track" data-watch-history-cards></div>
            <button class="carou-nav carou-nav--next dontusebuttonclass" type="button" aria-label="Вперёд">
                        <span class="fa fa-angle-right"></span>
                    </button>
        </div>
    </div>
    [/has_watch_history] [promo_collections]
    <div class="b-collections__newest">
        <div class="b-collections__newest_slider carou-content" data-carou data-carou-type="collections" data-carou-space="14">
            <button class="carou-nav carou-nav--prev dontusebuttonclass" type="button" aria-label="Назад">
                <span class="fa fa-angle-left"></span>
            </button>
            <div class="b-collections__newest_inner carou-track">
                [loop promo_collections]
                <a href="{item.url|raw}" class="b-collections__newest_card[item.banner_url] has-cover[/item.banner_url]">
                    [item.banner_url]
                        <img src="{item.banner_url|raw}" alt="{item.title}" loading="lazy">
                    [/item.banner_url]
                    <span><strong>{item.title}</strong></span>
                </a> [/loop]
            </div>
            <button class="carou-nav carou-nav--next dontusebuttonclass" type="button" aria-label="Вперёд">
                <span class="fa fa-angle-right"></span>
            </button>
        </div>
    </div>
    [/promo_collections] [promo_studios]
    <div class="sect sect-studios">
        <div class="sect-header fx-row fx-middle fx-start">
            <a href="/studios/" class="sect-title">Студии<span class="fa fa-chevron-right"></span></a>
        </div>
        <div class="sect-cont studios-grid">
            [loop promo_studios]
            <a href="{item.url|raw}" class="studio-card">
                            [item.logo_url]
                                <div class="studio-card__logo">
                                    <img src="{item.logo_url|raw}" alt="{item.title}" loading="lazy">
                                </div>
                            [/item.logo_url]
                            [not-item.logo_url]
                                <div class="studio-card__logo studio-card__logo--placeholder">
                                    <span class="fa fa-building"></span>
                                </div>
                            [/not-item.logo_url]
                            [item.items_count]
                                <div class="studio-card__count" aria-label="{item.items_count} {item.items_count_word}">
                                    <span class="studio-card__count-num">{item.items_count}</span>
                                    <span class="studio-card__count-text">{item.items_count_word}</span>
                                </div>
                            [/item.items_count]
                            [studios_card_show_title]
                                <div class="studio-card__body">
                                    <div class="studio-card__title">{item.title}</div>
                                    [studios_card_show_description]
                                        [item.description]
                                            <div class="studio-card__desc">{item.description}</div>
                                        [/item.description]
                                    [/studios_card_show_description]
                                </div>
                            [/studios_card_show_title]
                        </a> [/loop]
        </div>
    </div>
    [/promo_studios][loop custom_home_sections]
    <div class="sect" data-home-block-id="{item.block_id}">
        <div class="sect-header fx-row fx-middle fx-start">
            [item.link_url]
            <a href="{item.link_url|raw}" class="sect-title">{item.title}<span class="fa fa-chevron-right"></span></a> [/item.link_url] [not-item.link_url]
            <span class="sect-title">{item.title}</span> [/not-item.link_url] [item.show_tabs]
            <div class="sect-tabs" data-section-tabs>
                <span class="sect-tab[item.tab_latest_active] is-active[/item.tab_latest_active]" data-sort="latest" role="button" tabindex="0">Последние</span>
                <span class="sect-tab[item.tab_popular_active] is-active[/item.tab_popular_active]" data-sort="popular" role="button" tabindex="0">Популярные</span>
                <span class="sect-tab[item.tab_rating_active] is-active[/item.tab_rating_active]" data-sort="rating" role="button" tabindex="0">По рейтингу</span>
            </div>
            [/item.show_tabs]
        </div>
        <div class="sect-cont sect-items clearfix" data-section-cards>
            {item.cards_html|raw}
        </div>
    </div>
    [/loop] [loop home_sections]
    <div class="sect" data-home-section-type="{item.taxonomy_type|raw}" data-home-section-id="{item.taxonomy_id}">
        <div class="sect-header fx-row fx-middle fx-start">
            <a href="{item.url|raw}" class="sect-title">{item.title}<span class="fa fa-chevron-right"></span></a> [item.show_tabs]
            <div class="sect-tabs" data-section-tabs>
                <span class="sect-tab[item.tab_latest_active] is-active[/item.tab_latest_active]" data-sort="latest" role="button" tabindex="0">Последние</span>
                <span class="sect-tab[item.tab_popular_active] is-active[/item.tab_popular_active]" data-sort="popular" role="button" tabindex="0">Популярные</span>
                <span class="sect-tab[item.tab_rating_active] is-active[/item.tab_rating_active]" data-sort="rating" role="button" tabindex="0">По рейтингу</span>
            </div>
            [/item.show_tabs]
        </div>
        <div class="sect-cont sect-items clearfix" data-section-cards>
            {item.cards_html|raw}
        </div>
    </div>
    [/loop] [not-home_sections] [not-popular_list]
    <div class="sect">
        <div class="sect-cont sect-items clearfix">
            <p class="desc-text">Пока нет сериалов в каталоге.</p>
        </div>
    </div>
    [/not-popular_list] [/not-home_sections] [home_seo_html]
    <div class="desc-text clearfix home-seo">{home_seo_html|raw}</div>
    [/home_seo_html] [/is_home_first] [not-is_home_first]
    <div class="catalog-root" id="catalogRoot" data-total="{catalog_total}" data-browse-api="{browse_api_path|raw}" [is_taxonomy_page] data-taxonomy-type="{taxonomy_type|raw}" data-taxonomy-slug="{taxonomy_slug|raw}" [/is_taxonomy_page]>
        [catalog_filters_block]
        <div data-catalog-filters-wrap>{catalog_filters_block|raw}</div>
        [/catalog_filters_block]

        <div class="sect" data-catalog-results>
            <div class="sect-header fx-row fx-middle fx-start">
                <span class="sect-title">{page.heading}<span class="fa fa-chevron-right"></span></span>
                <span class="catalog-results-count" data-catalog-count hidden aria-live="polite">
                    <span class="catalog-results-count__num" data-catalog-count-num></span>
                <span class="catalog-results-count__text" data-catalog-count-text></span>
                </span>
            </div>
            <div data-catalog-grid-wrap>
                {catalog_series_grid|raw}
            </div>
        </div>
        <div data-catalog-pagination-wrap>{pagination_block|raw}</div>

        [category_seo_html]
        <div class="desc-text clearfix home-seo">{category_seo_html|raw}</div>
        [/category_seo_html]
    </div>
    [/not-is_home_first]
</main>