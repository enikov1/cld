[meta-title][query]Поиск: {query}[/query][not-query]{seo.title}[/not-query][/meta-title]
[meta-description][query]Результаты поиска по запросу «{query}»[/query][not-query]{seo.description}[/not-query][/meta-description]
[meta-canonical]{seo.canonical|raw}[/meta-canonical]
[meta-prev]{seo.prev|raw}[/meta-prev]
[meta-next]{seo.next|raw}[/meta-next]
[meta-robots]{seo.robots|raw}[/meta-robots]

<main class="main">
    <div class="desc-text"><h1>Поиск[query]: {query}[/query][not-query] по сайту[/not-query]</h1></div>

    [taxonomy_groups]
        [loop taxonomy_groups]
            <div class="sect ls-search-page__sect">
                <div class="sect-header fx-row fx-middle fx-start">
                    <span class="sect-title">{item.label}<span class="fa fa-chevron-right"></span></span>
                </div>
                <div class="sect-cont ls-search-page__group-list">
                    [loop item.items]
                        <a class="ls-search__item ls-search__item--{item.type}" href="{item.url|raw}">
                            [item.image]
                                <span class="ls-search__item-media"><img src="{item.image|raw}" alt="" loading="lazy"></span>
                            [/item.image]
                            [not-item.image]
                                <span class="ls-search__item-media ls-search__item-media--icon"><span class="fa ls-search-page__icon--{item.type}"></span></span>
                            [/not-item.image]
                            <span class="ls-search__item-body">
                                <span class="ls-search__item-title">{item.title}</span>
                                [item.subtitle]
                                    <span class="ls-search__item-sub">{item.subtitle}</span>
                                [/item.subtitle]
                            </span>
                        </a>
                    [/loop]
                </div>
            </div>
        [/loop]
    [/taxonomy_groups]

    [series_list]
        <div class="sect">
            <div class="sect-header fx-row fx-middle fx-start">
                <span class="sect-title">Сериалы<span class="fa fa-chevron-right"></span></span>
            </div>
            <div class="sect-cont sect-items clearfix">
                [loop series_list]
                    <div class="th-item" data-series-id="{item.id}">
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
                            </div>
                            <div class="th-mask fx-col fx-center fx-middle anim">
                                <span class="fa fa-play"></span>
                            </div>
                        </a>
                        <button type="button" class="th-info-btn dontusebuttonclass" data-series-info aria-label="Информация о сериале">
                            <span class="th-info-btn__icon" aria-hidden="true">i</span>
                        </button>
                    </div>
                [/loop]
            </div>
        </div>
    [/series_list]

    [not-has_results]
        [query]
            <div class="sect">
                <div class="sect-cont">
                    <p class="desc-text">По запросу «{query}» ничего не найдено.</p>
                </div>
            </div>
        [/query]
        [not-query]
            <div class="sect">
                <div class="sect-cont">
                    <p class="desc-text">Введите запрос в строке поиска в шапке сайта.</p>
                </div>
            </div>
        [/not-query]
    [/not-has_results]

    [popular_searches]
        <div class="sect ls-search-page__popular">
            <div class="sect-header fx-row fx-middle fx-start">
                <span class="sect-title">Что ищут<span class="fa fa-chevron-right"></span></span>
            </div>
            <div class="sect-cont ls-search-page__popular-list">
                [loop popular_searches]
                    <a class="ls-search-page__popular-item" href="{item.url|raw}">{item.query}</a>
                [/loop]
            </div>
        </div>
    [/popular_searches]

    {pagination_block|raw}
</main>
