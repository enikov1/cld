[meta-title]{seo.title}[/meta-title]
[meta-description]{seo.description}[/meta-description]
[meta-canonical]{seo.canonical|raw}[/meta-canonical]
[meta-prev]{seo.prev|raw}[/meta-prev]
[meta-next]{seo.next|raw}[/meta-next]
[meta-robots]{seo.robots|raw}[/meta-robots]

<main class="main expected-page" id="comingSoonRoot" data-sort="{sort|raw}">
    <div class="expected-hero">
        <div class="expected-hero__eyebrow"><span class="fa fa-clock-o"></span> Скоро</div>
        <h1>Самые ожидаемые премьеры</h1>
        <p>Рейтинг формируется голосами пользователей. Отметьте сериалы, которые планируете смотреть — так вы поможете сформировать топ ожиданий.</p>
    </div>

    <div class="expected-toolbar">
        <div class="expected-toolbar__title"><span class="fa fa-sort-amount-desc"></span> Сортировка</div>
        <div class="expected-sort" data-coming-soon-sort>
            <a href="{sort_most_url|raw}" class="[sort_most_active]is-active[/sort_most_active]">Сначала самые ожидаемые</a>
            <a href="{sort_least_url|raw}" class="[sort_least_active]is-active[/sort_least_active]">Сначала менее ожидаемые</a>
            <a href="{sort_release_url|raw}" class="[sort_release_active]is-active[/sort_release_active]">По дате выхода</a>
        </div>
    </div>

    [coming_soon_items]
        <div class="expected-list" data-coming-soon-list>
            {coming_soon_cards_html|raw}
        </div>
        {pagination_block|raw}
    [/coming_soon_items]

    [not-coming_soon_items]
        <div class="expected-list">
            <p class="desc-text">Пока нет сериалов в разделе «Скоро». Отметьте сериал в админке или добавьте премьеры.</p>
        </div>
    [/not-coming_soon_items]
</main>
