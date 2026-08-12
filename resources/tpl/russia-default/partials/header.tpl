<div class="header flex-row">
    <a href="/" class="logotype" title="{site.name}">
        <img src="{site.logo|raw}" alt="{site.name}">
    </a>

    <div class="search-wrap">
        <form class="js-quick-search" id="quicksearch" action="/search" method="get" autocomplete="off">
            <div class="search-box">
                <input id="story" class="dontuseinputclass" type="text" name="q" placeholder="{search_placeholder_desktop}" value="{search_query}" autocomplete="off" role="combobox" aria-expanded="false" aria-controls="rsQuickSearchPanel" aria-autocomplete="list">
                <button type="submit" title="Найти"><span class="fa fa-search"></span></button>
                <div class="ls-search__panel" id="rsQuickSearchPanel" hidden></div>
            </div>
        </form>
    </div>

    <div class="login-btns icon-l">
        [auth.logged_in]
            [auth_profile_enabled]
            <a href="/profile/" title="{auth_ui_header_profile}"><span class="fa fa-user"></span><span>{auth_ui_header_profile}</span></a>
            [/auth_profile_enabled]
            [favourites_enabled]
            <a href="/favourites/" title="{favourites_ui_page_title}" id="headerFavouritesLink"><span class="fa fa-heart"></span></a>
            [/favourites_enabled]
            [has_notifications]
            <button class="dontusebuttonclass button js-notify-btn js-series-bell" type="button" title="Уведомления" id="headerNotifyBtn">
                <span class="fa fa-bell"></span>
                <span class="series-bell-count" id="headerNotifyCount" hidden></span>
            </button>
            [/has_notifications]
            [auth.is_admin]
            <a href="{admin_url|raw}/" title="Админ-панель" target="_blank" rel="noopener noreferrer"><span class="fa fa-cog"></span></a>
            [/auth.is_admin]
        [/auth.logged_in]
        [not-auth.logged_in]
            [auth_register_enabled]
            <a href="/?auth=register">Регистрация</a>
            [/auth_register_enabled]
            [auth_login_enabled]
            <div class="button show-login js-login-open" role="button" tabindex="0"><span class="fa fa-user"></span><span>Войти</span></div>
            [/auth_login_enabled]
        [/not-auth.logged_in]
    </div>
</div>

[nav_desktop_items]
<ul class="f-menu clearfix">
    [loop nav_desktop_items]
    <li><a href="{item.url|raw}">{item.title}</a></li>
    [/loop]
</ul>
[/nav_desktop_items]
