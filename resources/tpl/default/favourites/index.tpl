[meta-title]{seo.title}[/meta-title]
[meta-description]{seo.description}[/meta-description]
[meta-canonical]{seo.canonical|raw}[/meta-canonical]
[meta-robots]{seo.robots|raw}[/meta-robots]

<main class="main">
    <div class="sect" data-favourites-page>
        <div class="sect-header fx-row fx-middle fx-start">
            <span class="sect-title">{page.heading}<span class="fa fa-chevron-right"></span></span>
            [favourites_has_items]
                <span class="catalog-results-count" aria-live="polite">
                    <span class="catalog-results-count__num">{favourites_total}</span>
                    <span class="catalog-results-count__text">{favourites_total_word}</span>
                </span>
            [/favourites_has_items]
        </div>

        [favourites_has_items]
            <div class="sect-cont sect-items clearfix" data-favourites-grid>
                {favourites_cards_html|raw}
            </div>
        [/favourites_has_items]

        [not-favourites_has_items]
            <div class="sect-cont">
                <p class="desc-text profile-empty">{favourites_empty_text}</p>
            </div>
        [/not-favourites_has_items]
    </div>
</main>
