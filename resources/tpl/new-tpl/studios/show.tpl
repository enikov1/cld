[meta-title]{seo.title}[/meta-title]
[meta-description]{seo.description}[/meta-description]
[meta-canonical]{seo.canonical|raw}[/meta-canonical]
[meta-robots]{seo.robots|raw}[/meta-robots]
[meta-prev]{seo.prev|raw}[/meta-prev]
[meta-next]{seo.next|raw}[/meta-next]

<main class="main clearfix fx-col grid-list" id="grid">
    [speedbar_block]
    <div class="speedbar nowrap">{speedbar_block|raw}</div>
    [/speedbar_block]

    <div class="sect" data-catalog-results>
        <div class="shorts-header flex-row">
            <div class="grid-select clearfix" id="grid-select" data-name="Переключить вид">
                <div data-type="grid-list" class="current"><span class="fa fa-reorder"></span></div>
                <div data-type="grid-thumb"><span class="fa fa-th"></span></div>
            </div>
            <h1>{studio.title}</h1>
            [studio_has_items]
                <span class="catalog-results-count" aria-live="polite">
                    <span class="catalog-results-count__num">{studio_total}</span>
                    <span class="catalog-results-count__text">{studio_total_word}</span>
                </span>
            [/studio_has_items]
            [not-studio_has_items]
                <span class="catalog-results-count is-empty" aria-live="polite">
                    <span class="catalog-results-count__text">Ничего не найдено</span>
                </span>
            [/not-studio_has_items]
        </div>

        [studio.description]
            <div class="desc-text">
                <p>{studio.description}</p>
            </div>
        [/studio.description]

        <div class="items clearfix">
            [loop studio_items]
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

        [not-studio_has_items]
            <p class="desc-text">У этой студии пока нет сериалов.</p>
        [/not-studio_has_items]
    </div>

    {pagination_block|raw}

    [studio_seo_html]
        <div class="site-desc clearfix fx-last">{studio_seo_html|raw}</div>
    [/studio_seo_html]
</main>
