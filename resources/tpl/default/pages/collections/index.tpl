[meta-title]{seo.title}[/meta-title]
[meta-description]{seo.description}[/meta-description]
[meta-canonical]{seo.canonical|raw}[/meta-canonical]

<main class="main">
    <div class="sect" data-catalog-results>
        <div class="sect-header fx-row fx-middle fx-start">
            <span class="sect-title">{page.heading}<span class="fa fa-chevron-right"></span></span>
            [collections_total]
                <span class="catalog-results-count" aria-live="polite">
                    <span class="catalog-results-count__num">{collections_total}</span>
                    <span class="catalog-results-count__text">{collections_total_word}</span>
                </span>
            [/collections_total]
        </div>

        <div class="sect-cont sect-items collections-index-grid clearfix">
            [loop collections_list]
                <div class="th-item">
                    <a class="th-in with-mask" href="/collections/{item.slug}/">
                        [item.cover_url]
                            <div class="th-img img-resp-4x3">
                                <img src="{item.cover_url|raw}" alt="{item.title}" loading="lazy">
                            </div>
                        [/item.cover_url]
                        [not-item.cover_url]
                            <div class="th-img img-resp-4x3 th-img--placeholder"></div>
                        [/not-item.cover_url]
                        <div class="th-desc">
                            <div class="th-title">{item.title}</div>
                        </div>
                        <div class="th-mask fx-col fx-center fx-middle anim">
                            <span class="fa fa-folder-open"></span>
                        </div>
                    </a>
                </div>
            [/loop]
            [not-collections_list]
                <p class="desc-text">Подборок пока нет.</p>
            [/not-collections_list]
        </div>
    </div>
</main>
