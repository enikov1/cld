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
            [studios_total]
                <span class="catalog-results-count" aria-live="polite">
                    <span class="catalog-results-count__num">{studios_total}</span>
                    <span class="catalog-results-count__text">{studios_total_word}</span>
                </span>
            [/studios_total]
        </div>

        <div class="items clearfix short-grid-items">
            [loop studios_list]
                <div class="short-grid with-mask">
                    <div class="short-img img-box">
                        [item.logo_url]
                            <img src="{item.logo_url|raw}" alt="{item.title}" loading="lazy">
                        [/item.logo_url]
                        <a class="mask flex-col ps-link" href="/studios/{item.slug}/">
                            <span class="fa fa-building"></span>
                        </a>
                    </div>
                    <div class="short-text">
                        <a class="short-title" href="/studios/{item.slug}/">{item.title}</a>
                    </div>
                    [item.items_count]
                        <div class="short-bottom flex-row">
                            <span>{item.items_count} {item.items_count_word}</span>
                        </div>
                    [/item.items_count]
                </div>
            [/loop]
            [not-studios_list]
                <p class="desc-text">Студий пока нет.</p>
            [/not-studios_list]
        </div>
    </div>
</main>
