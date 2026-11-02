[meta-title][query]Поиск: {query}[/query][not-query]{seo.title}[/not-query][/meta-title]
[meta-description][query]Результаты поиска по запросу «{query}»[/query][not-query]{seo.description}[/not-query][/meta-description]
[meta-canonical]{seo.canonical|raw}[/meta-canonical]
[meta-prev]{seo.prev|raw}[/meta-prev]
[meta-next]{seo.next|raw}[/meta-next]
[meta-robots]{seo.robots|raw}[/meta-robots]

<main class="main clearfix fx-col grid-list" id="grid">
    <div class="shorts-header flex-row">
        <h1>Поиск[query]: {query}[/query]</h1>
    </div>

    [taxonomy_groups]
        [loop taxonomy_groups]
            <div class="sect">
                <div class="shorts-header flex-row"><h2>{item.label}</h2></div>
                <div class="side-bc">
                    [loop item.items]
                        <a class="ls-search__item" href="{item.url|raw}">
                            [item.image]<div class="ls-search__item-media"><img src="{item.image|raw}" alt="" width="40" height="56" loading="lazy"></div>[/item.image]
                            <span>
                                <strong class="ls-search__item-title">{item.title}</strong>
                                [item.subtitle]<small class="ls-search__item-sub">{item.subtitle}</small>[/item.subtitle]
                            </span>
                        </a>
                    [/loop]
                </div>
            </div>
        [/loop]
    [/taxonomy_groups]

    [series_list]
    <div class="items clearfix">
        [loop series_list]
        <div class="short clearfix with-mask" data-series-id="{item.id}">
            <div class="short-img img-box">
                <img src="{item.poster_url|raw}" alt="{item.title}" loading="lazy">
                <div class="mask flex-col ps-link" data-href="{item.url|raw}">
                    <span class="fa fa-play"></span>
                </div>
                [item.episode_badge]<div class="badge_tr">{item.episode_badge}</div>[/item.episode_badge]
                [not-item.episode_badge][item.season_badge]<div class="badge_tr">{item.season_badge}</div>[/item.season_badge][/not-item.episode_badge]
            </div>
            <div class="short-text">
                <a class="short-title" href="{item.url|raw}">{item.title}</a>
                <div class="to-fav">
                    [auth.logged_in]
                    [favourites_enabled]
                    <button type="button" class="dontusebuttonclass" data-favourite-toggle data-series-id="{item.id}" title="{favourites_ui_add_label}" aria-pressed="false">
                        <span class="fa fa-star"></span>
                    </button>
                    [/favourites_enabled]
                    [/auth.logged_in]
                    [not-auth.logged_in]
                    <span class="fa fa-star fav-guest" title="Добавить в закладки"></span>
                    [/not-auth.logged_in]
                </div>
                <div class="short-desc">
                    [item.short_description]
                    <div class="sd-line sd-text"><span>Описание:</span> {item.short_description}</div>
                    [/item.short_description]
                    [not-item.short_description]
                    [item.year]
                    <div class="sd-line"><span>Год:</span> {item.year}</div>
                    [/item.year]
                    [/not-item.short_description]
                </div>
            </div>
            <div class="short-bottom flex-row">
                <div class="rating rs-rating">[item.kp_rating]<span class="rs-rating__kp">КП {item.kp_rating}</span>[/item.kp_rating][item.imdb_rating]<span class="rs-rating__imdb">IMDb {item.imdb_rating}</span>[/item.imdb_rating]</div>
                <a class="button" href="{item.url|raw}">Смотреть онлайн</a>
            </div>
        </div>
        [/loop]
    </div>
    {pagination_block|raw}
    [/series_list]

    [not-has_results]
    <p class="desc-text">Ничего не найдено. Попробуйте другой запрос.</p>
    [/not-has_results]
</main>
