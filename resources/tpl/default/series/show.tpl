[meta-title]{series.title} (сериал {series.year}) {season-type-4} смотреть онлайн в хорошем HD качестве бесплатно[/meta-title]
[meta-description][series.short_description]{series.short_description}[/series.short_description][not-series.short_description][series.description]{series.description}[/series.description][/not-series.short_description][/meta-description]
[meta-image]{series.poster_url|raw}[/meta-image]
[meta-canonical]{seo.canonical|raw}[/meta-canonical]
[meta-robots]{seo.robots|raw}[/meta-robots]
<div class="fmain" data-series-id="{series.id}">
    [auth.logged_in]<span hidden data-logged-in="1"></span>[/auth.logged_in]
    <section class="serial-view">
        <div class="serial-view__inner">
            <div class="serial-view__content">
                <header class="serial-titleline">
                    <h1>{series.title} {series.year} {season-type-4} смотреть онлайн</h1>
                    [auth.is_admin]
                        [series.kp_id]
                            <div class="usp-edit">
                                <a href="{admin_url|raw}/series?kp_id={series.kp_id}">Редактировать</a>
                            </div>
                        [/series.kp_id]
                    [/auth.is_admin]
                </header>

                [series.description]
                    <div class="serial-description slice-this">{series.description}</div>
                [/series.description]

                <div class="serial-details">
                    <div class="serial-source-ratings">
                        [series.kp_rating]
                            <div class="serial-source-rating serial-source-rating--kp">
                                <span>КП</span>
                                <strong>{series.kp_rating}</strong>
                            </div>
                        [/series.kp_rating]
                        [series.imdb_rating]
                            <div class="serial-source-rating serial-source-rating--imdb">
                                <span>IMDb</span>
                                <strong>{series.imdb_rating}</strong>
                            </div>
                        [/series.imdb_rating]
                        [series.status_badge_label]
                            <div class="serial-status serial-status--{series.status_badge_class|raw}">
                                <span class="serial-status__dot"></span>
                                <span class="serial-status__label">Статус сериала:</span>
                                <span class="serial-status__value">{series.status_badge_label}</span>
                            </div>
                        [/series.status_badge_label]
                    </div>

                    <div class="serial-details__rows">
                        [series.title_original]
                            <div class="serial-detail">
                                <div class="serial-detail__name">Оригинальное название:</div>
                                <div class="serial-detail__value">{series.title_original}</div>
                            </div>
                        [/series.title_original]
                        [series.premiere_date_label]
                            <div class="serial-detail">
                                <div class="serial-detail__name">Дата выхода:</div>
                                <div class="serial-detail__value">
                                    [not-series.premiere_is_year_only]
                                        [series.premiere_day_month_label]{series.premiere_day_month_label} [/series.premiere_day_month_label]
                                    [/not-series.premiere_is_year_only]
                                    [series.year_label]
                                        [series.year_url]
                                            <a href="{series.year_url|raw}" class="serial-detail__link">{series.year_label}</a>
                                        [/series.year_url]
                                        [not-series.year_url]{series.year_label}[/not-series.year_url]
                                    [/series.year_label]
                                    [series.age_limit_label]
                                        <span class="serial-age" title="{series.age_limit_tooltip|raw}">{series.age_limit_label}</span>
                                    [/series.age_limit_label]
                                </div>
                            </div>
                        [/series.premiere_date_label]
                        [not-series.premiere_date_label]
                            [series.year_label]
                                <div class="serial-detail">
                                    <div class="serial-detail__name">Год:</div>
                                    <div class="serial-detail__value">
                                        [series.year_url]
                                            <a href="{series.year_url|raw}" class="serial-detail__link">{series.year_label}</a>
                                        [/series.year_url]
                                        [not-series.year_url]{series.year_label}[/not-series.year_url]
                                    </div>
                                </div>
                            [/series.year_label]
                        [/not-series.premiere_date_label]
                        [series.countries]
                            <div class="serial-detail">
                                <div class="serial-detail__name">Страна:</div>
                                <div class="serial-detail__value serial-detail__links">
                                    [loop series.countries]
                                        <a href="{item.url|raw}" class="serial-detail__link">{item.name}</a>[not-item.is_last]<span class="serial-detail__sep">, </span>[/not-item.is_last]
                                    [/loop]
                                </div>
                            </div>
                        [/series.countries]
                        [series.translation]
                            <div class="serial-detail">
                                <div class="serial-detail__name">Перевод:</div>
                                <div class="serial-detail__value">{series.translation}</div>
                            </div>
                        [/series.translation]
                        [series.directors]
                            <div class="serial-detail">
                                <div class="serial-detail__name">Режиссёр:</div>
                                <div class="serial-detail__value serial-detail__links">
                                    [loop series.directors]
                                        <span class="serial-detail__link">{item.name}</span>[not-item.is_last]<span class="serial-detail__sep">, </span>[/not-item.is_last]
                                    [/loop]
                                </div>
                            </div>
                        [/series.directors]
                        [series.genres]
                            <div class="serial-detail">
                                <div class="serial-detail__name">Жанр:</div>
                                <div class="serial-detail__value serial-detail__links">
                                    [loop series.genres]
                                        <a href="{item.url|raw}" class="serial-detail__link">{item.name}</a>[not-item.is_last]<span class="serial-detail__sep">, </span>[/not-item.is_last]
                                    [/loop]
                                </div>
                            </div>
                        [/series.genres]
                        [series.studios]
                            <div class="serial-detail">
                                <div class="serial-detail__name">Студии:</div>
                                <div class="serial-detail__value serial-detail__studios">
                                    [loop series.studios]
                                        <a href="{item.url|raw}" class="serial-studio" title="{item.title}">
                                            [item.logo_url]
                                                <img src="{item.logo_url|raw}" alt="{item.title}" class="serial-studio__logo" loading="lazy">
                                            [/item.logo_url]
                                            [not-item.logo_url]
                                                <span class="serial-studio__name">{item.title}</span>
                                            [/not-item.logo_url]
                                        </a>
                                    [/loop]
                                </div>
                            </div>
                        [/series.studios]
                        [series.collections]
                            <div class="serial-detail">
                                <div class="serial-detail__name">Подборки:</div>
                                <div class="serial-detail__value serial-detail__links">
                                    [loop series.collections]
                                        <a href="{item.url|raw}" class="serial-detail__link">{item.title}</a>[not-item.is_last]<span class="serial-detail__sep">, </span>[/not-item.is_last]
                                    [/loop]
                                </div>
                            </div>
                        [/series.collections]
                        [series.channel_name]
                            <div class="serial-detail">
                                <div class="serial-detail__name">Канал:</div>
                                <div class="serial-detail__value">
                                    [series.channel_url]
                                        <a href="{series.channel_url|raw}" style="border-bottom: unset!important;">
                                            [series.channel_logo_url]
                                                <span><img src="{series.channel_logo_url|raw}" alt="{series.channel_name}" style="width: 90px;"></span>
                                            [/series.channel_logo_url]
                                            [not-series.channel_logo_url]{series.channel_name}[/not-series.channel_logo_url]
                                        </a>
                                    [/series.channel_url]
                                    [not-series.channel_url]{series.channel_name}[/not-series.channel_url]
                                </div>
                            </div>
                        [/series.channel_name]
                        [series.actors]
                            <div class="serial-detail serial-detail--wide">
                                <div class="serial-detail__name">Актёры:</div>
                                <div class="serial-detail__value serial-detail__links">
                                    [loop series.actors]
                                        <span class="serial-detail__link serial-detail__link--person"[item.photo_url] data-person-photo="{item.photo_url|raw}"[/item.photo_url]>{item.name}</span>[not-item.is_last]<span class="serial-detail__sep">, </span>[/not-item.is_last]
                                    [/loop]
                                </div>
                            </div>
                        [/series.actors]
                        [series.duration_minutes]
                            <div class="serial-detail">
                                <div class="serial-detail__name">Длительность:</div>
                                <div class="serial-detail__value">{series.duration_minutes} мин.</div>
                            </div>
                        [/series.duration_minutes]
                        [series.episode_progress_label]
                            <div class="serial-detail">
                                <div class="serial-detail__name">Серии:</div>
                                <div class="serial-detail__value">{series.episode_progress_label}</div>
                            </div>
                        [/series.episode_progress_label]
                    </div>
                </div>

                [has_coming_soon]
                    {anticipation_widget|raw}
                [/has_coming_soon]
            </div>

            <aside class="serial-view__left">
                <div class="serial-poster-card">
                    <img src="{series.poster_url|raw}" alt="{series.title}">
                    <div class="serial-poster-card__mark"[not-series.user_rating] hidden[/not-series.user_rating]>
                        <span data-user-rating>{series.user_rating}</span>
                    </div>
                </div>

                [has_series_vote]
                <div class="serial-vote" data-series-id="{series.id}">
                    <button type="button" class="serial-vote__btn serial-vote__btn--like dontusebuttonclass vote-btn" data-vote="1">
                        <span class="serial-vote__icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 21h4V9H2v12Zm20-10c0-1.1-.9-2-2-2h-6.31l.95-4.57.03-.32c0-.41-.17-.79-.44-1.06L13.17 2 6.59 8.59C6.22 8.95 6 9.45 6 10v9c0 1.1.9 2 2 2h9c.82 0 1.54-.5 1.84-1.22l3.02-7.05c.09-.23.14-.47.14-.73v-1Z"/></svg></span>
                        <span data-likes>{series.likes}</span>
                    </button>
                    <button type="button" class="serial-vote__btn serial-vote__btn--dislike dontusebuttonclass vote-btn" data-vote="-1">
                        <span data-dislikes>{series.dislikes}</span>
                        <span class="serial-vote__icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M22 3h-4v12h4V3ZM2 13c0 1.1.9 2 2 2h6.31l-.95 4.57-.03.32c0 .41.17.79.44 1.06L10.83 22l6.58-6.59c.37-.36.59-.86.59-1.41V5c0-1.1-.9-2-2-2H7c-.82 0-1.54.5-1.84 1.22L2.14 11.27c-.09.23-.14.47-.14.73v1Z"/></svg></span>
                    </button>
                </div>
                [/has_series_vote]

                <div class="serial-side-actions">
                    [has_coming_soon]
                        <button type="button" class="serial-side-btn serial-side-btn--soon dontusebuttonclass expected-watch-btn[anticipation.watch_active] is-active[/anticipation.watch_active]" data-anticipation-root data-series-id="{series.id}" data-anticipation-vote="1">
                            <span class="serial-side-btn__ico"><span class="fa fa-bell"></span></span>
                            <span class="serial-side-btn__text">Буду смотреть</span>
                        </button>
                    [/has_coming_soon]
                    [has_favourites]
                        <button type="button" class="serial-side-btn serial-side-btn--favourite dontusebuttonclass" data-favourite-toggle data-series-id="{series.id}" aria-pressed="false">
                            <span class="serial-side-btn__ico">
                                <svg class="serial-favourite-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35Z"/></svg>
                            </span>
                            <span class="serial-side-btn__text" data-favourite-label>{favourites_ui_add_label}</span>
                        </button>
                    [/has_favourites]
                    [has_watchlists]
                    <div class="serial-list-dropdown" data-watchlist-root>
                        <button class="serial-side-btn serial-side-btn--list dontusebuttonclass" type="button" data-watchlist-toggle>
                            <span class="serial-side-btn__ico">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2Z"/></svg>
                            </span>
                            <span class="serial-side-btn__text" data-watchlist-label>{watchlist_ui_add_label}</span>
                            <span class="serial-side-btn__arrow"><svg viewBox="0 0 24 24"><path d="M7 10l5 5 5-5H7Z"/></svg></span>
                        </button>
                        <div class="serial-list-dropdown__menu" data-watchlist-menu>
                            <p class="serial-list-dropdown__hint" data-watchlist-guest hidden>{watchlist_ui_login_hint}</p>
                        </div>
                    </div>
                    [/has_watchlists]

                    [has_schedule]
                        <button class="serial-side-btn serial-side-btn--schedule dontusebuttonclass" type="button" data-episodes-open>
                            <span class="serial-side-btn__ico">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 4h-1V2h-2v2H8V2H6v2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2Zm0 16H5V10h14v10ZM5 8V6h14v2H5Zm7 4h5v5h-5v-5Z"/></svg>
                            </span>
                            <span class="serial-side-btn__text">Расписание серий</span>
                            <span class="serial-side-btn__arrow" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M9.29 6.71a1 1 0 0 0 0 1.41L13.17 12l-3.88 3.88a1 1 0 1 0 1.41 1.41l4.59-4.59a1 1 0 0 0 0-1.41L10.7 6.7a1 1 0 0 0-1.41.01Z"/></svg></span>
                        </button>
                    [/has_schedule]

                    [has_telegram]
                        <a class="serial-side-btn serial-side-btn--telegram" href="{telegram_url|raw}" target="_blank" rel="noopener">
                            <span class="serial-side-btn__ico"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21.74 4.68c.22-.92-.67-1.68-1.5-1.31L2.93 10.93c-.95.42-.91 1.78.07 2.13l4.34 1.52 1.68 5.24c.31.96 1.55 1.21 2.2.44l2.46-2.91 4.44 3.22c.78.57 1.9.13 2.12-.81l3.5-15.08ZM8.07 13.59l8.47-5.21c.38-.23.75.27.43.57l-6.96 6.48-.27 2.84-1.67-4.68Z"/></svg></span>
                            <span class="serial-side-btn__text">{telegram_label}</span>
                        </a>
                    [/has_telegram]
                </div>
            </aside>
        </div>
    </section>

    <h2 class="serial-watch-title">«{series.title}» смотреть онлайн бесплатно в хорошем качестве</h2>

    [has_notifications]
        <div class="series-subscribe-box[notification_subscribed] is-subscribed[/notification_subscribed]" id="seriesSubscribeBox" data-subscribed="{notification_subscribed}">
            <div class="series-subscribe-box__icon"><span class="fa fa-bell"></span></div>
            <div class="series-subscribe-box__content">
                <div class="series-subscribe-box__title">{notifications_ui_subscribe_title}</div>
                <div class="series-subscribe-box__text">{notifications_ui_subscribe_text}</div>
                <span class="series-subscribe-box__status" id="notifySubscribedBadge"[not-notification_subscribed] hidden[/not-notification_subscribed]>{notifications_ui_subscribed_badge}</span>
                <div class="series-subscribe-box__feedback" id="notifySubscribeFeedback" hidden></div>
            </div>
            <button class="dontusebuttonclass series-subscribe-box__button" type="button" id="notifyOpenBtn" data-action="subscribe">{notifications_ui_subscribe_btn}</button>
        </div>
    [/has_notifications]

    [has_notifications]
    <div class="notify-overlay" id="notifyOverlay" hidden>
        <div class="notify-modal">
            <button class="dontusebuttonclass notify-close" type="button" data-notify-close aria-label="Закрыть">×</button>
            <h3 class="notify-title">{notifications_ui_title}</h3>
            <div class="notify-feedback" id="notifyFeedback" hidden></div>
            <form class="notify-form" id="notifyForm">
                <label class="notify-item"><input type="checkbox" name="notify_any" value="1" checked><span></span>На новую серию</label>
                [loop notify_voices]
                    <label class="notify-item"><input type="checkbox" name="voices[]" value="{item.name}"><span></span>{item.name}</label>
                [/loop]
                <div class="notify-actions">
                    <button type="button" class="dontusebuttonclass notify-unsubscribe" id="notifyUnsubscribeBtn" hidden>Отключить для сериала</button>
                    <button type="button" class="dontusebuttonclass notify-cancel" data-notify-close>Отменить</button>
                    <button type="submit" class="dontusebuttonclass notify-save">Сохранить</button>
                </div>
            </form>
        </div>
    </div>
    [/has_notifications]

    <section class="trailer-box" data-trailer-box>
        [has_players]
        <div class="trailer-box__top">
            <div class="trailer-tabs-wrap" data-trailer-tabs>
                <button type="button" class="trailer-tabs-nav trailer-tabs-nav--prev dontusebuttonclass" aria-label="Назад" hidden>
                    <span class="fa fa-angle-left"></span>
                </button>
                <div class="trailer-tabs" role="tablist" data-player-tabs>
                    [loop players]
                        [item.is_first]
                            <button type="button" class="trailer-tabs__btn dontusebuttonclass is-active" data-player-index="{item.index}">{item.label}</button>
                        [/item.is_first]
                        [not-item.is_first]
                            <button type="button" class="trailer-tabs__btn dontusebuttonclass" data-player-index="{item.index}">{item.label}</button>
                        [/not-item.is_first]
                    [/loop]
                </div>
                <button type="button" class="trailer-tabs-nav trailer-tabs-nav--next dontusebuttonclass" aria-label="Вперёд" hidden>
                    <span class="fa fa-angle-right"></span>
                </button>
            </div>
            <div class="trailer-actions">
                <label class="trailer-light">
                    <input type="checkbox" data-player-light>
                    <span></span>
                    Свет
                </label>
                <button type="button" class="trailer-report dontusebuttonclass" data-player-report>
                    <span class="fa fa-exclamation-triangle" aria-hidden="true"></span>
                    Есть жалоба?
                </button>
            </div>
        </div>
        <div class="trailer-box__body">
            [loop players]
                [item.is_first]
                    <div class="trailer-panel is-active" data-player-panel="{item.index}">
                        <div class="trailer-frame">
                            [item.is_embed]
                                <div class="player-embed">{item.html|raw}</div>
                            [/item.is_embed]
                            [not-item.is_embed]
                                <iframe class="player-iframe"
                                        src="{item.url|raw}"
                                        data-player-url="{item.url|raw}"
                                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                                        allowfullscreen></iframe>
                            [/not-item.is_embed]
                        </div>
                    </div>
                [/item.is_first]
                [not-item.is_first]
                    <div class="trailer-panel" data-player-panel="{item.index}" hidden>
                        <div class="trailer-frame">
                            [item.is_embed]
                                <div class="player-embed">{item.html|raw}</div>
                            [/item.is_embed]
                            [not-item.is_embed]
                                <iframe class="player-iframe"
                                        data-player-url="{item.url|raw}"
                                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                                        allowfullscreen></iframe>
                            [/not-item.is_embed]
                        </div>
                    </div>
                [/not-item.is_first]
            [/loop]
        </div>
        [/has_players]
        [not-has_players]
        <div class="trailer-box__top">
            <div class="trailer-tabs" role="tablist">
                <button type="button" class="trailer-tabs__btn dontusebuttonclass is-active" disabled>Смотреть онлайн</button>
            </div>
            <div class="trailer-actions">
                <label class="trailer-light">
                    <input type="checkbox" data-player-light>
                    <span></span>
                    Свет
                </label>
                <button type="button" class="trailer-report dontusebuttonclass" data-player-report>
                    <span class="fa fa-exclamation-triangle" aria-hidden="true"></span>
                    Есть жалоба?
                </button>
            </div>
        </div>
        <div class="trailer-box__body">
            <div class="trailer-panel is-active">
                <div class="trailer-frame">
                    <div class="trailer-frame__empty">{series_ui_player_empty}</div>
                </div>
            </div>
        </div>
        [/not-has_players]
        [series_share_widget_code]
            <div class="trailer-box__actions trailer-box__actions--split">
                <div class="trailer-box__share">
                    {series_share_widget_code|raw}
                </div>
                <button class="trailer-box__bottom trailer-box__bookmark dontusebuttonclass" type="button" data-bookmark-open aria-haspopup="dialog">
                    <span class="fa fa-bookmark" aria-hidden="true"></span>
                    {series_ui_bookmark_hint}
                </button>
            </div>
        [/series_share_widget_code]
        [not-series_share_widget_code]
            <button class="trailer-box__bottom dontusebuttonclass" type="button" data-bookmark-open aria-haspopup="dialog">
                <span class="fa fa-bookmark" aria-hidden="true"></span>
                {series_ui_bookmark_hint}
            </button>
        [/not-series_share_widget_code]
    </section>

    <div class="light-overlay" data-light-overlay></div>

    <div id="ps-overlay-wrap" data-player-report-modal hidden>
        <button type="button" id="ps-close" class="dontusebuttonclass" data-player-report-close aria-label="Закрыть"></button>
        <div id="ps-content-holder">
            <div id="ps-report-content">
                <div id="ps-report-title">Сообщить о проблеме с плеером</div>
                <div id="ps-report-title-info">
                    Прежде чем оставлять жалобу, попробуйте другой плеер (например «Плеер 2»).
                    Также AdBlock и похожие расширения часто мешают загрузке — попробуйте отключить их на время.
                </div>
                <div id="ps-report-issues" data-player-report-issues>
                    <button type="button" class="report-item dontusebuttonclass" data-reason="player_not_shown">
                        Плеер не отображается (только колесо загрузки либо сообщение)
                    </button>
                    <button type="button" class="report-item dontusebuttonclass" data-reason="video_not_start">
                        Видео не запускается или черный экран после запуска
                    </button>
                    <button type="button" class="report-item dontusebuttonclass" data-reason="audio_desync">
                        Звук и видео не совпадают
                    </button>
                    <button type="button" class="report-item dontusebuttonclass" data-reason="description_error">
                        Ошибка в описании
                    </button>
                    <button type="button" class="report-item dontusebuttonclass" data-reason="other">
                        Другое
                    </button>
                </div>
                <div id="ps-report-issues-comment">
                    <textarea
                        name="message"
                        rows="3"
                        maxlength="2000"
                        placeholder="Опишите проблему подробнее (необязательно, для «Другое» — обязательно)"
                        data-player-report-message
                    ></textarea>
                    <button type="button" class="report-item report-item--submit dontusebuttonclass" data-player-report-submit>
                        Отправить жалобу
                    </button>
                    <div class="player-report-feedback" data-player-report-feedback hidden></div>
                </div>
            </div>
        </div>
    </div>

    <div class="bookmark-modal" data-bookmark-modal hidden>
        <div class="bookmark-modal__overlay" data-bookmark-close></div>
        <div class="bookmark-modal__window" role="dialog" aria-modal="true" aria-labelledby="bookmarkModalTitle">
            <div class="bookmark-modal__head">
                <div>
                    <div class="bookmark-modal__label">Закладки</div>
                    <h3 id="bookmarkModalTitle">{series_ui_bookmark_modal_title}</h3>
                </div>
                <button class="bookmark-modal__close dontusebuttonclass" type="button" data-bookmark-close aria-label="Закрыть">×</button>
            </div>
            <div class="bookmark-modal__body">
                <ol class="bookmark-modal__steps">
                    <li>Нажмите <kbd>Ctrl</kbd> + <kbd>D</kbd> на Windows/Linux или <kbd>⌘</kbd> + <kbd>D</kbd> на Mac.</li>
                    <li>Либо нажмите на звёздочку в адресной строке браузера.</li>
                    <li>Подтвердите сохранение закладки в появившемся окне.</li>
                </ol>
                <p class="bookmark-modal__note">После добавления в закладки вы сможете быстро возвращаться к новым сериям.</p>
            </div>
        </div>
    </div>

    [has_reactions]
        {reactions_widget|raw}
    [/has_reactions]

    [has_schedule]
        {episodes_modal|raw}
    [/has_schedule]

    [has_comments]
    <section class="comments-section" id="commentsSection" data-series-id="{series.id}">
        [auth.logged_in]<span hidden data-logged-in="1"></span>[/auth.logged_in]

        <header class="comments-section__header">
            <h2 class="comments-section__title">{comments_ui_title}</h2>
            <span class="comments-section__count" data-comments-count[not-comments_count] hidden[/not-comments_count]>{comments_count_label}</span>
        </header>

        <div class="comments-notice" data-comments-notice hidden></div>

        <p class="comments-compose__hint">{comments_ui_spoiler_hint}</p>

        <div class="comments-compose">
            [auth.logged_in]
                <form class="comment-form" data-comment-form="root" action="#" novalidate>
                    <label class="comment-form__label" for="comment-body-root">{comments_ui_label}</label>
                    <textarea id="comment-body-root" name="body" placeholder="{comments_ui_placeholder}" rows="4"></textarea>
                    <div class="comment-form__footer">
                        <button type="button" class="dontusebuttonclass comment-form__submit" data-comment-submit>{comments_ui_submit}</button>
                    </div>
                </form>
            [/auth.logged_in]
            [not-auth.logged_in]
            [comments_guest_enabled]
                <form class="comment-form comment-form--guest" data-comment-form="root" action="#" novalidate>
                    <div class="comment-form__guest">
                        <input type="text" name="guest_name" placeholder="{comments_ui_guest_name}" maxlength="120" autocomplete="nickname">
                        <label class="comment-form__anon">
                            <input type="checkbox" name="is_anonymous" value="1">
                            <span>{comments_ui_anonymous}</span>
                        </label>
                    </div>
                    <label class="comment-form__label" for="comment-body-guest">{comments_ui_label}</label>
                    <textarea id="comment-body-guest" name="body" placeholder="{comments_ui_placeholder}" rows="4"></textarea>
                    <div class="comment-form__footer">
                        <button type="button" class="dontusebuttonclass comment-form__submit" data-comment-submit>{comments_ui_submit}</button>
                    </div>
                </form>
            [/comments_guest_enabled]
            [/not-auth.logged_in]
        </div>

        <div class="comments-sort" data-comments-sort data-comments-sort-current="{comments_sort}">
            <span class="comments-sort__label">{comments_ui_sort_label}</span>
            <div class="comments-sort__links">
                [comments_sort_date_active]
                    <button type="button" class="dontusebuttonclass comments-sort__link is-active" data-comments-sort-value="date">{comments_ui_sort_date}</button>
                [/comments_sort_date_active]
                [not-comments_sort_date_active]
                    <button type="button" class="dontusebuttonclass comments-sort__link" data-comments-sort-value="date">{comments_ui_sort_date}</button>
                [/not-comments_sort_date_active]
                <span class="comments-sort__sep" aria-hidden="true"></span>
                [comments_sort_rating_active]
                    <button type="button" class="dontusebuttonclass comments-sort__link is-active" data-comments-sort-value="rating">{comments_ui_sort_rating}</button>
                [/comments_sort_rating_active]
                [not-comments_sort_rating_active]
                    <button type="button" class="dontusebuttonclass comments-sort__link" data-comments-sort-value="rating">{comments_ui_sort_rating}</button>
                [/not-comments_sort_rating_active]
            </div>
        </div>

        <div class="comments-list" data-comments-list data-comments-ssr="1">
            [comments_empty]
                <p class="comment-empty">{comments_ui_empty}</p>
            [/comments_empty]
            [not-comments_empty]
                {comments_list_html|raw}
            [/not-comments_empty]
        </div>
    </section>
    [/has_comments]

    [has_related]
    <section class="sect related-sect" aria-label="Рекомендуем посмотреть">
        <div class="sect-header fx-row fx-middle fx-start">
            <span class="sect-title">Рекомендуем посмотреть</span>
        </div>
        <div class="sect-cont sect-items clearfix">
            {related_cards_html|raw}
        </div>
    </section>
    [/has_related]
</div>