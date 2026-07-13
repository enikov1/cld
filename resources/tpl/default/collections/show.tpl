[meta-title]{seo.title}[/meta-title]
[meta-description]{seo.description}[/meta-description]
[meta-canonical]{seo.canonical|raw}[/meta-canonical]
[meta-robots]{seo.robots|raw}[/meta-robots]
[meta-prev]{seo.prev|raw}[/meta-prev]
[meta-next]{seo.next|raw}[/meta-next]

<main class="main">
    [collection.description]
        <div class="desc-text">
            <p>{collection.description}</p>
        </div>
    [/collection.description]

    <div class="sect" data-catalog-results>
        <div class="sect-header fx-row fx-middle fx-start">
            <span class="sect-title">{collection.title}<span class="fa fa-chevron-right"></span></span>
            [collection_has_items]
                <span class="catalog-results-count" aria-live="polite">
                    <span class="catalog-results-count__num">{collection_total}</span>
                    <span class="catalog-results-count__text">{collection_total_word}</span>
                </span>
            [/collection_has_items]
            [not-collection_has_items]
                <span class="catalog-results-count is-empty" aria-live="polite">
                    <span class="catalog-results-count__text">Ничего не найдено</span>
                </span>
            [/not-collection_has_items]
        </div>

        [collection.source_updated_at]
            <p class="desc-text collection-updated">Обновлено {collection.source_updated_at}</p>
        [/collection.source_updated_at]

        <div class="sect-cont sect-items clearfix">
            [loop collection_items]
                <div class="th-item">
                    <a class="th-in with-mask" href="{item.url|raw}">
                        <div class="th-img img-resp-vert">
                            <div class="th-card-badges th-card-badges--status">
                                [item.badge_new_episode]
                                    <span class="th-card-badge th-card-badge--new">{item.badge_new_episode_label}</span>
                                [/item.badge_new_episode]
                                [item.badge_popular]
                                    <span class="th-card-badge th-card-badge--popular">{item.badge_popular_label}</span>
                                [/item.badge_popular]
                            </div>
                            <div class="th-card-badges th-card-badges--meta">
                                [item.season_badge]
                                    <span class="th-card-badge th-card-badge--season">{item.season_badge}</span>
                                [/item.season_badge]
                                [item.episode_badge]
                                    <span class="th-card-badge th-card-badge--episode">{item.episode_badge}</span>
                                [/item.episode_badge]
                                [item.top_reaction_emoji]
                                    <span class="th-card-emoji" title="Топ-реакция">{item.top_reaction_emoji}</span>
                                [/item.top_reaction_emoji]
                            </div>
                            <img src="{item.poster_url|raw}" alt="{item.title}" loading="lazy">
                        </div>
                        <div class="th-desc">
                            <div class="th-title">{item.title}</div>
                            <div class="th-rates fx-row">
                                [item.kp_rating]
                                    <div class="th-rate th-rate-kp" data-text="КП"><span>{item.kp_rating}</span></div>
                                [/item.kp_rating]
                            </div>
                        </div>
                        <div class="th-mask fx-col fx-center fx-middle anim">
                            <span class="fa fa-play"></span>
                        </div>
                    </a>
                </div>
            [/loop]
        </div>
    </div>

    {pagination_block|raw}

    [collection_seo_html]
        <div class="desc-text clearfix home-seo">{collection_seo_html|raw}</div>
    [/collection_seo_html]
</main>
