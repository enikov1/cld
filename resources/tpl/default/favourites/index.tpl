[meta-title]{seo.title}[/meta-title]
[meta-description]{seo.description}[/meta-description]
[meta-canonical]{seo.canonical|raw}[/meta-canonical]
[meta-robots]{seo.robots|raw}[/meta-robots]

<main class="main">
    <div class="sect" data-favourites-page data-empty-text="{favourites_empty_text}">
        <div class="sect-header fx-row fx-middle fx-start">
            <span class="sect-title">{page.heading}<span class="fa fa-chevron-right"></span></span>
            <span class="catalog-results-count" aria-live="polite" data-favourites-count [not-favourites_has_items]hidden[/not-favourites_has_items]>
                <span class="catalog-results-count__num" data-favourites-count-num>{favourites_total}</span>
                <span class="catalog-results-count__text" data-favourites-count-word>{favourites_total_word}</span>
            </span>
        </div>

        <div class="sect-cont sect-items clearfix" data-favourites-grid [not-favourites_has_items]hidden[/not-favourites_has_items]>
            {favourites_cards_html|raw}
        </div>

        <div class="sect-cont" data-favourites-empty [favourites_has_items]hidden[/favourites_has_items]>
            <p class="desc-text profile-empty">{favourites_empty_text}</p>
        </div>
    </div>
</main>
