[meta-title]{seo.title}[/meta-title]
[meta-description][page.lead]{page.lead}[/page.lead][not-page.lead]{seo.description}[/not-page.lead][/meta-description]
[meta-canonical]{seo.canonical|raw}[/meta-canonical]
[meta-prev]{seo.prev|raw}[/meta-prev]
[meta-next]{seo.next|raw}[/meta-next]
[meta-robots]{seo.robots|raw}[/meta-robots]

<main class="main clearfix fx-col grid-list" id="grid">
    [home_seo_html]
    <div class="site-desc clearfix fx-last">
        {home_seo_html|raw}
    </div>
    [/home_seo_html]

    <div class="shorts-header flex-row">
        <div class="grid-select clearfix" id="grid-select" data-name="Переключить вид">
            <div data-type="grid-list" class="current"><span class="fa fa-reorder"></span></div>
            <div data-type="grid-thumb"><span class="fa fa-th"></span></div>
        </div>
        <h1>{page.heading}</h1>
    </div>

    {new_episodes_block|raw}

    [has_watch_history]
    <div class="sect" id="watchHistorySection" data-watch-history-root hidden>
        <div class="sect-header"><span class="sect-title">{watch_history_ui_title}</span></div>
        <div class="items clearfix" data-watch-history-cards></div>
    </div>
    [/has_watch_history]

    [promo_collections]
    <div class="sect">
        <div class="sect-header"><span class="sect-title">Подборки</span></div>
        <div class="side-bc flex-row">
            [loop promo_collections]
            <a href="{item.url|raw}" class="side-item1">
                [item.banner_url]
                <div class="si1-img img-box"><img src="{item.banner_url|raw}" alt="{item.title}" loading="lazy"></div>
                [/item.banner_url]
                <div class="si1-title">{item.title}</div>
            </a>
            [/loop]
        </div>
    </div>
    [/promo_collections]

    [loop custom_home_sections]
    <div class="sect" data-home-block-id="{item.block_id}">
        <div class="shorts-header flex-row">
            [item.link_url]
            <h2><a href="{item.link_url|raw}">{item.title}</a></h2>
            [/item.link_url]
            [not-item.link_url]
            <h2>{item.title}</h2>
            [/not-item.link_url]
        </div>
        <div class="items clearfix" data-section-cards>{item.cards_html|raw}</div>
    </div>
    [/loop]

    [loop home_sections]
    <div class="sect" data-home-section-type="{item.taxonomy_type|raw}" data-home-section-id="{item.taxonomy_id}">
        <div class="shorts-header flex-row">
            <h2><a href="{item.url|raw}">{item.title}</a></h2>
        </div>
        <div class="items clearfix" data-section-cards>{item.cards_html|raw}</div>
    </div>
    [/loop]

    [not-home_sections]
    [not-custom_home_sections]
    [not-popular_list]
    <div class="items clearfix">
        <p class="desc-text">Пока нет сериалов в каталоге.</p>
    </div>
    [/not-popular_list]
    [/not-custom_home_sections]
    [/not-home_sections]
</main>
