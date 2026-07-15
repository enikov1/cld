[meta-title]{seo.title}[/meta-title]
[meta-description]{seo.description}[/meta-description]
[meta-canonical]{seo.canonical|raw}[/meta-canonical]
[meta-robots]{seo.robots|raw}[/meta-robots]
[meta-prev]{seo.prev|raw}[/meta-prev]
[meta-next]{seo.next|raw}[/meta-next]

<main class="main">
    <div class="studio-page-header fx-row fx-middle">
        [studio.logo_url]
            <div class="studio-page-header__logo">
                <img src="{studio.logo_url|raw}" alt="{studio.title}" loading="lazy">
            </div>
        [/studio.logo_url]
        <div class="studio-page-header__info">
            <h1 class="studio-page-header__title">{studio.title}</h1>
            [studio.description]
                <div class="desc-text studio-page-header__desc">
                    <p>{studio.description}</p>
                </div>
            [/studio.description]
        </div>
    </div>

    <div class="sect" data-catalog-results>
        <div class="sect-header fx-row fx-middle fx-start">
            <span class="sect-title">Сериалы студии<span class="fa fa-chevron-right"></span></span>
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

        <div class="sect-cont sect-items clearfix">
            [loop studio_items]
                <div class="th-item" data-series-id="{item.id}">
                    <a class="th-in with-mask" href="{item.url|raw}">
                        <div class="th-img img-resp-vert">
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
                    <button type="button" class="th-info-btn dontusebuttonclass" data-series-info aria-label="Информация о сериале">
                        <span class="th-info-btn__icon" aria-hidden="true">i</span>
                    </button>
                </div>
            [/loop]
        </div>
    </div>

    {pagination_block|raw}

    [studio_seo_html]
        <div class="desc-text clearfix home-seo">{studio_seo_html|raw}</div>
    [/studio_seo_html]
</main>
