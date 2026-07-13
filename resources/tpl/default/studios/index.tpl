[meta-title]{seo.title}[/meta-title]
[meta-description]{seo.description}[/meta-description]
[meta-canonical]{seo.canonical|raw}[/meta-canonical]

<main class="main">
    <div class="sect" data-catalog-results>
        <div class="sect-header fx-row fx-middle fx-start">
            <span class="sect-title">{page.heading}<span class="fa fa-chevron-right"></span></span>
            [studios_total]
                <span class="catalog-results-count" aria-live="polite">
                    <span class="catalog-results-count__num">{studios_total}</span>
                    <span class="catalog-results-count__text">{studios_total_word}</span>
                </span>
            [/studios_total]
        </div>

        <div class="sect-cont studios-grid clearfix">
            [loop studios_list]
                <a class="studio-card" href="/studios/{item.slug}/">
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
                    <div class="studio-card__body">
                        <div class="studio-card__title">{item.title}</div>
                        [item.description]
                            <div class="studio-card__desc">{item.description}</div>
                        [/item.description]
                    </div>
                </a>
            [/loop]
            [not-studios_list]
                <p class="desc-text">Студий пока нет.</p>
            [/not-studios_list]
        </div>
    </div>
</main>
