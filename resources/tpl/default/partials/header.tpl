<header class="ls-header" id="header">
    <div class="ls-header__inner wrap-center">
        <a href="/" class="ls-logo logo">
            <img src="{site.logo|raw}" alt="{site.name}" width="158" height="70">
        </a>

        <nav class="ls-nav">
            <ul class="ls-nav__list">
                [loop nav_desktop_items]
                    [item.has_mega]
                        <li class="ls-nav__item has-mega">
                            <a href="{item.url|raw}" class="ls-nav__link">{item.title}</a>
                            <div class="ls-mega">
                                [item.mega_buttons]
                                <div class="ls-mega__top">
                                    [loop item.mega_buttons]
                                        <a class="ls-mega__main" href="{item.url|raw}">
                                            <span class="ls-mega__main-body">
                                                <span class="ls-mega__main-title">{item.title}</span>
                                                [item.subtitle]
                                                    <span class="ls-mega__main-sub">{item.subtitle}</span>
                                                [/item.subtitle]
                                            </span>
                                            <span class="ls-mega__main-arrow fa fa-angle-right" aria-hidden="true"></span>
                                        </a>
                                    [/loop]
                                </div>
                                [/item.mega_buttons]
                                [item.mega_sections]
                                <div class="ls-mega__grid">
                                    [loop item.mega_sections]
                                        <div class="ls-mega__section {item.css_class|raw}">
                                            <div class="ls-mega__title">{item.title}</div>
                                            <div class="ls-mega__links columns">
                                                [loop item.links]
                                                    <a href="{item.url|raw}">{item.label}</a>
                                                [/loop]
                                            </div>
                                        </div>
                                    [/loop]
                                </div>
                                [/item.mega_sections]
                            </div>
                        </li>
                    [/item.has_mega]
                    [not-item.has_mega]
                        <li class="ls-nav__item">
                            <a href="{item.url|raw}" class="ls-nav__link">{item.title}</a>
                        </li>
                    [/not-item.has_mega]
                [/loop]
            </ul>
        </nav>

        <form class="ls-search js-quick-search" action="/search" method="get" autocomplete="off">
            <button class="dontusebuttonclass ls-search__close js-header-search-close" type="button" aria-label="Закрыть поиск" hidden>
                <span class="fa fa-times"></span>
            </button>
            <input type="text" class="dontuseinputclass" name="q" placeholder="{search_placeholder_desktop}" value="{search_query}" autocomplete="off" role="combobox" aria-expanded="false" aria-controls="lsQuickSearchPanel" aria-autocomplete="list">
            <button class="dontusebuttonclass" type="submit" aria-label="Найти">
                <span class="fa fa-search"></span>
            </button>
            <div class="ls-search__panel" id="lsQuickSearchPanel" hidden role="listbox"></div>
        </form>

        <div class="ls-actions">
            <button class="dontusebuttonclass ls-action ls-action--search js-header-search-open" type="button" title="Поиск" aria-label="Поиск">
                <span class="fa fa-search"></span>
            </button>
            [favourites_enabled]
                <a class="dontusebuttonclass ls-action ls-action--favourites" href="/favourites/" title="{favourites_ui_page_title}" id="headerFavouritesLink">
                    <span class="fa fa-heart"></span>
                    <span class="series-bell-count ls-fav-count" id="headerFavCount" hidden></span>
                </a>
            [/favourites_enabled]
            [auth.is_admin]
                <a class="dontusebuttonclass ls-action ls-action--admin" href="{admin_url|raw}/" title="Админ-панель" id="headerAdminLink" target="_blank" rel="noopener noreferrer">
                    <span class="fa fa-cog"></span>
                </a>
            [/auth.is_admin]
            [auth.logged_in]
            [auth_profile_enabled]
                <a class="dontusebuttonclass ls-action" href="/profile/" title="{auth_ui_header_profile}">
                    <span class="fa fa-user"></span>
                </a>
            [/auth_profile_enabled]
            [/auth.logged_in]
            [has_notifications]
                <button class="dontusebuttonclass ls-action js-notify-btn js-series-bell" type="button" title="Уведомления" id="headerNotifyBtn">
                    <span class="fa fa-bell"></span>
                    <span class="series-bell-count" id="headerNotifyCount" hidden></span>
                </button>
            [/has_notifications]
            [not-auth.logged_in]
            [auth_login_enabled]
                <button class="dontusebuttonclass ls-action js-login-open" type="button" title="{auth_ui_header_login}">
                    <span class="fa fa-user"></span>
                </button>
            [/auth_login_enabled]
            [/not-auth.logged_in]

            <button class="dontusebuttonclass ls-action js-theme-toggle fa fa-moon-o" type="button" title="Сменить тему"></button>

            <button class="dontusebuttonclass ls-burger js-mobile-menu-open" type="button" aria-label="Меню">
                <span></span><span></span><span></span>
            </button>
        </div>
    </div>
</header>

<div class="ls-mobile-overlay js-mobile-menu-close" aria-hidden="true" inert></div>
<aside class="ls-mobile-menu" id="lsMobileMenu" aria-hidden="true" inert>
    <div class="ls-mobile-menu__head">
        <a href="/" class="ls-mobile-menu__logo logo">
            <img src="{site.logo|raw}" alt="{site.name}" width="158" height="70">
        </a>
        <button class="dontusebuttonclass ls-mobile-menu__close js-mobile-menu-close" type="button" aria-label="Закрыть">
            <span class="fa fa-times"></span>
        </button>
    </div>
    <form class="ls-mobile-search js-quick-search" action="/search" method="get" autocomplete="off">
        <input type="text" class="dontuseinputclass" name="q" placeholder="Что ищем?" value="{search_query}" autocomplete="off" role="combobox" aria-expanded="false" aria-controls="lsQuickSearchPanelMobile" aria-autocomplete="list">
        <button class="dontusebuttonclass" type="submit"><span class="fa fa-search"></span></button>
        <div class="ls-search__panel ls-search__panel--mobile" id="lsQuickSearchPanelMobile" hidden role="listbox"></div>
    </form>
    <div class="ls-mobile-accordion">
        [loop nav_mobile_items]
            [item.has_mega]
                <div class="ls-mobile-section js-mobile-section">
                    <button class="dontusebuttonclass ls-mobile-section__toggle js-mobile-section-toggle" type="button" aria-expanded="false">
                        <span>{item.title}</span>
                        <span class="fa fa-angle-down" aria-hidden="true"></span>
                    </button>
                    <div class="ls-mobile-section__body">
                        [item.mega_buttons]
                            <div class="ls-mobile-section__highlights">
                                [loop item.mega_buttons]
                                    <a class="ls-mobile-section__main" href="{item.url|raw}">
                                        <span class="ls-mobile-section__main-title">{item.title}</span>
                                        [item.subtitle]
                                            <span class="ls-mobile-section__main-sub">{item.subtitle}</span>
                                        [/item.subtitle]
                                    </a>
                                [/loop]
                            </div>
                        [/item.mega_buttons]
                        [item.mega_sections]
                            [loop item.mega_sections]
                                <div class="ls-mobile-mega-group">
                                    <div class="ls-mobile-mega-group__title">{item.title}</div>
                                    <div class="ls-mobile-mega-group__links two-columns">
                                        [loop item.links]
                                            <a href="{item.url|raw}">{item.label}</a>
                                        [/loop]
                                    </div>
                                </div>
                            [/loop]
                        [/item.mega_sections]
                        <a class="ls-mobile-section__catalog" href="{item.url|raw}">Перейти в раздел</a>
                    </div>
                </div>
            [/item.has_mega]
            [not-item.has_mega]
                <a class="ls-mobile-link" href="{item.url|raw}">{item.title}</a>
            [/not-item.has_mega]
        [/loop]
        [favourites_enabled]
            <a class="ls-mobile-link ls-mobile-link--favourites" href="/favourites/" id="mobileFavouritesLink">
                <span class="fa fa-heart" aria-hidden="true"></span>
                {favourites_ui_page_title}
                <span class="ls-mobile-fav-count" id="mobileFavCount" hidden></span>
            </a>
        [/favourites_enabled]
        [auth.is_admin]
            <a class="ls-mobile-link ls-mobile-link--admin" href="{admin_url|raw}/" id="mobileAdminLink" target="_blank" rel="noopener noreferrer">
                <span class="fa fa-cog" aria-hidden="true"></span>
                Админ-панель
            </a>
        [/auth.is_admin]
    </div>
</aside>
