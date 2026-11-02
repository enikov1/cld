<aside class="side">
    [nav_desktop_items]
    <div class="side-box">
        <div class="side-bt">Категории</div>
        <div class="side-bc" id="side-nav">
            [loop nav_desktop_items]
                [item.has_mega]
                    [loop item.mega_sections]
                    <div class="nav-title">{item.title}</div>
                    <ul class="nav-menu flex-row">
                        <li>
                            [loop item.links]
                            <a href="{item.url|raw}">{item.label}</a>
                            [/loop]
                        </li>
                    </ul>
                    [/loop]
                [/item.has_mega]
            [/loop]
        </div>
    </div>
    [/nav_desktop_items]

    [popular_list]
    <div class="side-box tabs-box">
        <div class="side-bt">{home_popular_title}</div>
        <div class="side-bc flex-row">
            [loop popular_list]
            <a class="side-item1" href="{item.url|raw}" title="{item.title}">
                <div class="si1-img img-box">
                    <img src="{item.poster_url|raw}" alt="{item.title}" loading="lazy">
                </div>
                <div class="si1-title">{item.title}</div>
            </a>
            [/loop]
        </div>
    </div>
    [/popular_list]
</aside>
