[meta-title]{seo.title}[/meta-title]
[meta-description]{seo.description}[/meta-description]
[meta-canonical]{seo.canonical|raw}[/meta-canonical]
[meta-prev]{seo.prev|raw}[/meta-prev]
[meta-next]{seo.next|raw}[/meta-next]
[meta-robots]{seo.robots|raw}[/meta-robots]

<main class="main clearfix fx-col grid-list" id="grid" data-sort="{sort|raw}">
    [speedbar_block]
    <div class="speedbar nowrap">{speedbar_block|raw}</div>
    [/speedbar_block]

    <div class="shorts-header flex-row">
        <h1>{page.heading}</h1>
        [coming_soon_total]
            <span class="catalog-results-count" aria-live="polite">
                <span class="catalog-results-count__num">{coming_soon_total}</span>
                <span class="catalog-results-count__text">премьер</span>
            </span>
        [/coming_soon_total]
    </div>

    <div class="sect-tabs" data-coming-soon-sort>
        <span class="[sort_most_active]is-active[/sort_most_active]"><a href="{sort_most_url|raw}">Сначала самые ожидаемые</a></span>
        <span class="[sort_least_active]is-active[/sort_least_active]"><a href="{sort_least_url|raw}">Сначала менее ожидаемые</a></span>
        <span class="[sort_release_active]is-active[/sort_release_active]"><a href="{sort_release_url|raw}">По дате выхода</a></span>
    </div>

    [coming_soon_items]
        <div class="items clearfix" data-coming-soon-list>
            {coming_soon_cards_html|raw}
        </div>
        {pagination_block|raw}
    [/coming_soon_items]

    [not-coming_soon_items]
        <p class="desc-text">Пока нет сериалов в разделе «Скоро». Отметьте сериал в админке или добавьте премьеры.</p>
    [/not-coming_soon_items]
</main>
