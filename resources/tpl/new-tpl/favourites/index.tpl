[meta-title]{seo.title}[/meta-title]
[meta-description]{seo.description}[/meta-description]
[meta-canonical]{seo.canonical|raw}[/meta-canonical]
[meta-robots]{seo.robots|raw}[/meta-robots]

<main class="main clearfix fx-col grid-list" id="grid">
    [speedbar_block]
    <div class="speedbar nowrap">{speedbar_block|raw}</div>
    [/speedbar_block]
    <div class="sect" data-favourites-page data-empty-text="{favourites_empty_text}">
        <div class="shorts-header flex-row">
            <div class="grid-select clearfix" id="grid-select" data-name="Переключить вид">
                <div data-type="grid-list" class="current"><span class="fa fa-reorder"></span></div>
                <div data-type="grid-thumb"><span class="fa fa-th"></span></div>
            </div>
            <h1>{page.heading}</h1>
            <span class="catalog-results-count" aria-live="polite" data-favourites-count [not-favourites_has_items]hidden[/not-favourites_has_items]>
                <span class="catalog-results-count__num" data-favourites-count-num>{favourites_total}</span>
                <span class="catalog-results-count__text" data-favourites-count-word>{favourites_total_word}</span>
            </span>
        </div>

        <div class="items clearfix" data-favourites-grid [not-favourites_has_items]hidden[/not-favourites_has_items]>
            {favourites_cards_html|raw}
        </div>

        <p class="desc-text" data-favourites-empty [favourites_has_items]hidden[/favourites_has_items]>{favourites_empty_text}</p>
    </div>
</main>
