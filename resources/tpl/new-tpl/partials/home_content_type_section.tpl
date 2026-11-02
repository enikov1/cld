<div class="sect" data-home-content-type="{content_type|raw}">
    <div class="shorts-header flex-row">
        <h2 class="sect-title">{title}</h2>
        [show_tabs]
        <div class="sect-tabs" data-section-tabs>
            <span class="sect-tab[tab_latest_active] is-active[/tab_latest_active]" data-sort="latest" role="button" tabindex="0">Последние</span>
            <span class="sect-tab[tab_popular_active] is-active[/tab_popular_active]" data-sort="popular" role="button" tabindex="0">Популярные</span>
            <span class="sect-tab[tab_rating_active] is-active[/tab_rating_active]" data-sort="rating" role="button" tabindex="0">По рейтингу</span>
        </div>
        [/show_tabs]
    </div>
    <div class="items clearfix" data-section-cards>
        {cards_html|raw}
    </div>
</div>
