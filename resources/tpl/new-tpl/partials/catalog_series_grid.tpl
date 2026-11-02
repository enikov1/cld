<div class="items clearfix" data-catalog-grid>
    [loop series_list]
    <div class="short clearfix with-mask" data-series-id="{item.id}">
        <div class="short-img img-box">
            <img src="{item.poster_url|raw}" alt="{item.title}" loading="lazy">
            <div class="mask flex-col ps-link" data-href="{item.url|raw}">
                <span class="fa fa-play"></span>
            </div>
        </div>
        <div class="short-text">
            <a class="short-title" href="{item.url|raw}">{item.title}</a>
            <div class="to-fav">
                [auth.logged_in]
                [favourites_enabled]
                <button type="button" class="dontusebuttonclass" data-favourite-toggle data-series-id="{item.id}" title="{favourites_ui_add_label}" aria-pressed="false">
                    <span class="fa fa-star"></span>
                </button>
                [/favourites_enabled]
                [/auth.logged_in]
                [not-auth.logged_in]
                <span class="fa fa-star fav-guest" title="Добавить в закладки"></span>
                [/not-auth.logged_in]
            </div>
            <div class="short-desc">
                [item.short_description]
                <div class="sd-line sd-text"><span>Описание:</span> {item.short_description}</div>
                [/item.short_description]
            </div>
        </div>
        <div class="short-bottom flex-row">
            <div class="rating rs-rating">
                [item.kp_rating]<span class="rs-rating__kp">КП {item.kp_rating}</span>[/item.kp_rating]
                [item.imdb_rating]<span class="rs-rating__imdb">IMDb {item.imdb_rating}</span>[/item.imdb_rating]
            </div>
            <a class="button" href="{item.url|raw}">Смотреть онлайн</a>
        </div>
    </div>
    [/loop]
    [not-series_list]
    <p class="desc-text catalog-empty">По выбранным фильтрам ничего не найдено.</p>
    [/not-series_list]
</div>
