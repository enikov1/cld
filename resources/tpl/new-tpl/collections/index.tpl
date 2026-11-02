[meta-title]{seo.title}[/meta-title]
[meta-description]{seo.description}[/meta-description]
[meta-canonical]{seo.canonical|raw}[/meta-canonical]

<main class="main clearfix fx-col grid-list" id="grid">
    [speedbar_block]
    <div class="speedbar nowrap">{speedbar_block|raw}</div>
    [/speedbar_block]
    <div class="sect" data-catalog-results>
        <div class="shorts-header flex-row">
            <h1>{page.heading}</h1>
            [collections_total]
                <span class="catalog-results-count" aria-live="polite">
                    <span class="catalog-results-count__num">{collections_total}</span>
                    <span class="catalog-results-count__text">{collections_total_word}</span>
                </span>
            [/collections_total]
        </div>

        <div class="items clearfix short-grid-items">
            [loop collections_list]
                <div class="short-grid with-mask">
                    <div class="short-img img-box">
                        [item.cover_url]
                            <img src="{item.cover_url|raw}" alt="{item.title}" loading="lazy">
                        [/item.cover_url]
                        <a class="mask flex-col ps-link" href="/collections/{item.slug}/">
                            <span class="fa fa-folder-open"></span>
                        </a>
                    </div>
                    <div class="short-text">
                        <a class="short-title" href="/collections/{item.slug}/">{item.title}</a>
                    </div>
                </div>
            [/loop]
            [not-collections_list]
                <p class="desc-text">Подборок пока нет.</p>
            [/not-collections_list]
        </div>
    </div>
</main>
