[meta-title][query]Поиск: {query}[/query][not-query]{seo.title}[/not-query][/meta-title]
[meta-description][query]Результаты поиска по запросу «{query}»[/query][not-query]{seo.description}[/not-query][/meta-description]
[meta-canonical]{seo.canonical|raw}[/meta-canonical]
[meta-prev]{seo.prev|raw}[/meta-prev]
[meta-next]{seo.next|raw}[/meta-next]
[meta-robots]{seo.robots|raw}[/meta-robots]

<main class="main clearfix fx-col grid-list" id="grid">
    <div class="shorts-header flex-row">
        <h1>Поиск[query]: {query}[/query]</h1>
    </div>

    [taxonomy_groups]
        [loop taxonomy_groups]
            <div class="sect">
                <div class="shorts-header flex-row"><h2>{item.label}</h2></div>
                <div class="side-bc">
                    [loop item.items]
                        <a class="ls-search__item" href="{item.url|raw}" style="display:flex;gap:10px;padding:8px 0;align-items:center">
                            [item.image]<img src="{item.image|raw}" alt="" width="40" height="56" loading="lazy" style="border-radius:3px;object-fit:cover">[/item.image]
                            <span>
                                <strong>{item.title}</strong>
                                [item.subtitle]<br><small>{item.subtitle}</small>[/item.subtitle]
                            </span>
                        </a>
                    [/loop]
                </div>
            </div>
        [/loop]
    [/taxonomy_groups]

    [series_list]
    <div class="items clearfix">
        [loop series_list]
        <div class="short clearfix with-mask" data-series-id="{item.id}">
            <div class="short-img img-box">
                <img src="{item.poster_url|raw}" alt="{item.title}" loading="lazy">
                <div class="mask flex-col ps-link" data-href="{item.url|raw}"><span class="fa fa-play"></span></div>
            </div>
            <div class="short-text">
                <a class="short-title" href="{item.url|raw}">{item.title}</a>
                [item.short_description]
                <div class="short-desc"><div class="sd-line sd-text"><span>Описание:</span> {item.short_description}</div></div>
                [/item.short_description]
            </div>
            <div class="short-bottom flex-row">
                <div class="rating rs-rating">
                    [item.kp_rating]<span class="rs-rating__kp">КП {item.kp_rating}</span>[/item.kp_rating]
                </div>
                <a class="button" href="{item.url|raw}">Смотреть онлайн</a>
            </div>
        </div>
        [/loop]
    </div>
    {pagination_block|raw}
    [/series_list]

    [not-series_list]
    [not-taxonomy_groups]
    <p class="desc-text">Ничего не найдено. Попробуйте другой запрос.</p>
    [/not-taxonomy_groups]
    [/not-series_list]
</main>
