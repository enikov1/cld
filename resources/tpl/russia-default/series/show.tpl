[meta-title]{series.title} ([type-1]Фильм[/type-1][type-2]сериал[/type-2][type-3]Мультфильм[/type-3][type-4]Мультсериал[/type-4][type-5]Аниме[/type-5][type-6]Дорама[/type-6][type-7]ТВ-шоу[/type-7], {series.year}) смотреть онлайн бесплатно[/meta-title]
[meta-description][series.short_description]{series.short_description}[/series.short_description][not-series.short_description][series.description]{series.description}[/series.description][/not-series.short_description][/meta-description]
[meta-image]{series.poster_url|raw}[/meta-image]
[meta-canonical]{seo.canonical|raw}[/meta-canonical]
[meta-robots]{seo.robots|raw}[/meta-robots]

<main class="main clearfix fx-col grid-list" data-series-id="{series.id}">
    [auth.logged_in]<span hidden data-logged-in="1"></span>[/auth.logged_in]

    [speedbar_block]
    <div class="speedbar nowrap">{speedbar_block|raw}</div>
    [/speedbar_block]

    [ad_vpaid_code]
    <div class="vpad">{ad_vpaid_code|raw}</div>
    [/ad_vpaid_code]

    <article class="full ignore-select">
        <div class="fpage">
            <div class="ftitle">
                <h1>{series.title}</h1>
                <div class="to-fav">
                    [has_favourites]
                    <button type="button" class="dontusebuttonclass" data-favourite-toggle data-series-id="{series.id}" aria-pressed="false" title="{favourites_ui_add_label}">
                        <span class="fa fa-star"></span>
                    </button>
                    [/has_favourites]
                </div>
                [auth.is_admin]
                [series.kp_id]
                <div class="usp-edit"><a href="{admin_url|raw}/series?kp_id={series.kp_id}">Редактировать</a></div>
                [/series.kp_id]
                [/auth.is_admin]
            </div>

            <div class="fcols clearfix">
                <div class="fposter">
                    <img src="{series.poster_url|raw}" alt="{series.title}">
                    <div class="frating rs-rating rs-rating--poster">
                        [series.kp_rating]<span class="rs-rating__kp">КП {series.kp_rating}</span>[/series.kp_rating]
                        [series.imdb_rating]<span class="rs-rating__imdb">IMDb {series.imdb_rating}</span>[/series.imdb_rating]
                        [series.user_rating]<span class="rs-rating__user" data-user-rating>{series.user_rating}</span>[/series.user_rating]
                    </div>
                </div>
                <div class="finfo">
                    [series.countries]
                    <div class="sd-line"><span>Страна:</span>
                        [loop series.countries]
                        <a href="{item.url|raw}">{item.name}</a>[not-item.is_last], [/not-item.is_last]
                        [/loop]
                    </div>
                    [/series.countries]
                    [series.year_label]
                    <div class="sd-line"><span>Год выпуска:</span>
                        [series.year_url]<a href="{series.year_url|raw}">{series.year_label}</a>[/series.year_url]
                        [not-series.year_url]{series.year_label}[/not-series.year_url]
                    </div>
                    [/series.year_label]
                    [series.genres]
                    <div class="sd-line"><span>Жанр:</span>
                        [loop series.genres]
                        <a href="{item.url|raw}">{item.name}</a>[not-item.is_last], [/not-item.is_last]
                        [/loop]
                    </div>
                    [/series.genres]
                    [series.episode_progress_label]
                    <div class="sd-line"><span>Количество серий:</span> {series.episode_progress_label}</div>
                    [/series.episode_progress_label]
                    [series.duration_minutes]
                    <div class="sd-line"><span>Продолжительность:</span> {series.duration_minutes} минут</div>
                    [/series.duration_minutes]
                    [series.directors]
                    <div class="sd-line"><span>Режиссер:</span>
                        [loop series.directors]
                        <span class="serial-detail__link serial-detail__link--person" [item.photo_url] data-person-photo="{item.photo_url|raw}" [/item.photo_url]>{item.name}</span>[not-item.is_last], [/not-item.is_last]
                        [/loop]
                    </div>
                    [/series.directors]
                    [series.actors]
                    <div class="sd-line"><span>В ролях:</span>
                        [loop series.actors]
                        <span class="serial-detail__link serial-detail__link--person" [item.photo_url] data-person-photo="{item.photo_url|raw}" [/item.photo_url]>{item.name}</span>[not-item.is_last], [/not-item.is_last]
                        [/loop]
                    </div>
                    [/series.actors]
                    [series.studios]
                    <div class="sd-line"><span>Студии:</span>
                        [loop series.studios]
                        <a href="{item.url|raw}">{item.title}</a>[not-item.is_last], [/not-item.is_last]
                        [/loop]
                    </div>
                    [/series.studios]
                    [series.status_badge_label]
                    <div class="sd-line"><span>Статус:</span> {series.status_badge_label}</div>
                    [/series.status_badge_label]
                </div>
            </div>

            [series.description]
            <div class="fdesc full-text clearfix">
                <div class="fdesc-title">Описание:</div>
                <span>{series.description}</span>
            </div>
            [/series.description]

            [has_series_vote]
            <div class="serial-vote" data-series-id="{series.id}" style="margin-top:15px">
                <button type="button" class="button dontusebuttonclass vote-btn" data-vote="1">Нравится <span data-likes>{series.likes}</span></button>
                <button type="button" class="button dontusebuttonclass vote-btn" data-vote="-1">Не нравится <span data-dislikes>{series.dislikes}</span></button>
            </div>
            [/has_series_vote]

            [has_coming_soon]{anticipation_widget|raw}[/has_coming_soon]
        </div>

        <div class="fplayer-title">
            <h2>смотреть {series.title} онлайн бесплатно в хорошем качестве HD 1080</h2>
        </div>

        [has_notifications]
        <div class="series-subscribe-box[notification_subscribed] is-subscribed[/notification_subscribed]" id="seriesSubscribeBox" data-subscribed="{notification_subscribed}">
            <div class="series-subscribe-box__icon"><span class="fa fa-bell"></span></div>
            <div class="series-subscribe-box__content">
                <div class="series-subscribe-box__title">{notifications_ui_subscribe_title}</div>
                <div class="series-subscribe-box__text">{notifications_ui_subscribe_text}</div>
            </div>
            <button class="dontusebuttonclass button" type="button" id="notifyOpenBtn" data-action="subscribe">{notifications_ui_subscribe_btn}</button>
        </div>
        [/has_notifications]

        <div class="fplayer tabs-box">
            <div class="tabs-b video-box visible">
                [has_players]
                <div class="trailer-tabs" role="tablist" data-player-tabs style="padding:8px;background:#222532">
                    [loop players]
                    [item.is_first]
                    <button type="button" class="trailer-tabs__btn dontusebuttonclass is-active button" data-player-index="{item.index}" style="margin:2px">{item.label}</button>
                    [/item.is_first]
                    [not-item.is_first]
                    <button type="button" class="trailer-tabs__btn dontusebuttonclass button" data-player-index="{item.index}" style="margin:2px">{item.label}</button>
                    [/not-item.is_first]
                    [/loop]
                </div>
                [loop players]
                [item.is_first]
                <div class="trailer-panel is-active" data-player-panel="{item.index}">
                    <div class="trailer-frame">
                        [item.is_embed]<div class="player-embed">{item.html|raw}</div>[/item.is_embed]
                        [not-item.is_embed]
                        <iframe class="player-iframe" src="{item.url|raw}" data-player-url="{item.url|raw}" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>
                        [/not-item.is_embed]
                    </div>
                </div>
                [/item.is_first]
                [not-item.is_first]
                <div class="trailer-panel" data-player-panel="{item.index}" hidden>
                    <div class="trailer-frame">
                        [item.is_embed]<div class="player-embed">{item.html|raw}</div>[/item.is_embed]
                        [not-item.is_embed]
                        <iframe class="player-iframe" data-player-url="{item.url|raw}" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>
                        [/not-item.is_embed]
                    </div>
                </div>
                [/not-item.is_first]
                [/loop]
                [/has_players]
                [not-has_players]
                <div class="trailer-frame__empty" style="color:#fff;padding:40px;text-align:center">{series_ui_player_empty}</div>
                [/not-has_players]
            </div>

            [series_share_widget_code]
            <div style="padding:10px">{series_share_widget_code|raw}</div>
            [/series_share_widget_code]
        </div>

        [has_reactions]{reactions_widget|raw}[/has_reactions]
        [has_schedule]{episodes_modal|raw}[/has_schedule]

        [has_related]
        <div class="rels">
            <div class="rels-t">Рекомендуем посмотреть</div>
            <div class="rels-c owl-carousel" id="owl-rels">
                {related_cards_html|raw}
            </div>
        </div>
        [/has_related]
    </article>

    [has_comments]
    <div class="full-comms ignore-select" id="commentsSection" data-series-id="{series.id}">
        [auth.logged_in]<span hidden data-logged-in="1"></span>[/auth.logged_in]
        <div class="comms-t">{comments_ui_title} [comments_count]({comments_count_label})[/comments_count]</div>

        <div class="comments-notice" data-comments-notice hidden></div>
        <div class="add-comm-form clearfix comments-compose">
            [auth.logged_in]
            <form class="comment-form" data-comment-form="root" action="#" novalidate>
                <div class="ac-textarea"><textarea name="body" placeholder="{comments_ui_placeholder}" rows="4"></textarea></div>
                <div class="ac-submit clearfix">
                    <button type="button" class="dontusebuttonclass" data-comment-submit>{comments_ui_submit}</button>
                </div>
            </form>
            [/auth.logged_in]
            [not-auth.logged_in]
            [comments_guest_enabled]
            <form class="comment-form comment-form--guest" data-comment-form="root" action="#" novalidate>
                <div class="ac-inputs flex-row">
                    <input type="text" name="guest_name" placeholder="{comments_ui_guest_name}" maxlength="120" autocomplete="nickname">
                </div>
                <div class="ac-textarea"><textarea name="body" placeholder="{comments_ui_placeholder}" rows="4"></textarea></div>
                <div class="ac-submit clearfix">
                    <button type="button" class="dontusebuttonclass" data-comment-submit>{comments_ui_submit}</button>
                </div>
            </form>
            [/comments_guest_enabled]
            [/not-auth.logged_in]
        </div>

        <div class="comments-list" data-comments-list data-comments-ssr="1">
            [comments_empty]<p class="comment-empty">{comments_ui_empty}</p>[/comments_empty]
            [not-comments_empty]{comments_list_html|raw}[/not-comments_empty]
        </div>
    </div>
    [/has_comments]

    [has_notifications]
    <div class="notify-overlay" id="notifyOverlay" hidden>
        <div class="notify-modal">
            <button class="dontusebuttonclass notify-close" type="button" data-notify-close aria-label="Закрыть">×</button>
            <h3 class="notify-title">{notifications_ui_title}</h3>
            <form class="notify-form" id="notifyForm">
                <label class="notify-item"><input type="checkbox" name="notify_any" value="1" checked><span></span>На новую серию</label>
                [loop notify_voices]
                <label class="notify-item"><input type="checkbox" name="voices[]" value="{item.name}"><span></span>{item.name}</label>
                [/loop]
                <div class="notify-actions">
                    <button type="button" class="dontusebuttonclass notify-unsubscribe" id="notifyUnsubscribeBtn" hidden>Отключить</button>
                    <button type="button" class="dontusebuttonclass notify-cancel" data-notify-close>Отменить</button>
                    <button type="submit" class="dontusebuttonclass notify-save">Сохранить</button>
                </div>
            </form>
        </div>
    </div>
    [/has_notifications]
</main>
