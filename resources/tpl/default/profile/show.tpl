[meta-title]Профиль — {profile.name}[/meta-title]
[meta-description]{seo.description}[/meta-description]
[meta-robots]noindex, nofollow[/meta-robots]

<main class="profile-page">
    <div id="profileFlash" class="profile-flash profile-flash--success" hidden></div>
    [flash_success]
        <div class="profile-flash profile-flash--success">{flash_success}</div>
    [/flash_success]

    <div class="profile-layout">
        <aside class="profile-sidebar">
            <div class="profile-avatar">{profile.initial}</div>
            <h1 class="profile-sidebar__name">{profile.name}</h1>
            <p class="profile-sidebar__meta">На сайте с {profile.registered_at}</p>

            <div class="profile-stats">
                <div class="profile-stat">
                    <span class="profile-stat__num">{profile_stats.lists}</span>
                    <span class="profile-stat__label">списков</span>
                </div>
                <div class="profile-stat">
                    <span class="profile-stat__num" data-profile-stat-items>{profile_stats.items}</span>
                    <span class="profile-stat__label">сериалов</span>
                </div>
                <div class="profile-stat">
                    <span class="profile-stat__num">{profile_stats.comments}</span>
                    <span class="profile-stat__label">комментариев</span>
                </div>
            </div>

            <form action="/logout" method="post" class="profile-logout">
                <input type="hidden" name="_token" value="{csrf_token|raw}">
                <button type="submit" class="profile-btn profile-btn--ghost">Выйти</button>
            </form>
        </aside>

        <div class="profile-main">
            <nav class="profile-tabs" role="tablist">
                <button type="button" class="profile-tabs__btn is-active" data-profile-tab="account">Аккаунт</button>
                <button type="button" class="profile-tabs__btn" data-profile-tab="lists">Мои списки</button>
                [has_notifications]
                    <button type="button" class="profile-tabs__btn" data-profile-tab="notifications">Уведомления</button>
                [/has_notifications]
                <button type="button" class="profile-tabs__btn" data-profile-tab="comments">Комментарии</button>
            </nav>

            <section class="profile-panel is-active" data-profile-panel="account">
                <div class="profile-grid">
                    <div class="profile-card">
                        <h2>Данные аккаунта</h2>
                        <form class="profile-form" action="/profile/update" method="post">
                            <input type="hidden" name="_token" value="{csrf_token|raw}">
                            <label class="profile-field">
                                <span>Имя</span>
                                <input type="text" name="name" value="{profile.name}" required>
                            </label>
                            <label class="profile-field">
                                <span>Email</span>
                                <input type="email" name="email" value="{profile.email|raw}" required>
                            </label>
                            <button type="submit" class="profile-btn">Сохранить</button>
                        </form>
                    </div>

                    <div class="profile-card">
                        <h2>Смена пароля</h2>
                        <div class="profile-flash profile-flash--error" hidden data-form-feedback></div>
                        [form_errors_list]
                            <div class="profile-flash profile-flash--error">
                                [loop form_errors_list]
                                    <div>{item.message}</div>
                                [/loop]
                            </div>
                        [/form_errors_list]
                        <form class="profile-form" action="/profile/password" method="post">
                            <input type="hidden" name="_token" value="{csrf_token|raw}">
                            <label class="profile-field">
                                <span>Текущий пароль</span>
                                <input type="password" name="current_password" required>
                            </label>
                            <label class="profile-field">
                                <span>Новый пароль</span>
                                <input type="password" name="password" required>
                            </label>
                            <label class="profile-field">
                                <span>Повтор пароля</span>
                                <input type="password" name="password_confirmation" required>
                            </label>
                            <button type="submit" class="profile-btn">Обновить пароль</button>
                        </form>
                    </div>
                </div>
            </section>

            <section class="profile-panel" data-profile-panel="lists" hidden>
                <div class="profile-card profile-card--full">
                    <div class="profile-list-head">
                        <h2>Мои списки</h2>
                        <form class="profile-new-list" action="/profile/lists" method="post">
                            <input type="hidden" name="_token" value="{csrf_token|raw}">
                            <input type="text" name="name" placeholder="Название нового списка" required maxlength="120">
                            <button type="submit" class="profile-btn profile-btn--sm">Создать</button>
                        </form>
                    </div>

                    [loop watchlists]
                        <div class="profile-watchlist-block" data-watchlist-id="{item.id}">
                            <div class="profile-watchlist-block__head">
                                [item.is_system]
                                    <h3>{item.name}</h3>
                                [/item.is_system]
                                [not-item.is_system]
                                    <form class="profile-rename-list" action="/profile/lists/{item.id}" method="post">
                                        <input type="hidden" name="_token" value="{csrf_token|raw}">
                                        <input type="text" name="name" value="{item.name}" required maxlength="120">
                                        <button type="submit" class="profile-btn profile-btn--sm profile-btn--ghost">Сохранить</button>
                                    </form>
                                    <form action="/profile/lists/{item.id}/delete" method="post" class="profile-delete-list">
                                        <input type="hidden" name="_token" value="{csrf_token|raw}">
                                        <button type="submit" class="profile-btn profile-btn--sm profile-btn--danger">Удалить</button>
                                    </form>
                                [/not-item.is_system]
                                <span class="profile-watchlist-count" data-watchlist-count>{item.items_count}</span>
                            </div>

                            <div class="profile-watchlist-items" data-watchlist-items>
                                [loop item.items]
                                    <article class="profile-watchlist-item" data-series-id="{item.series_id}">
                                        <a class="profile-watchlist-item__link" href="{item.url|raw}">
                                            <img src="{item.poster_url|raw}" alt="{item.title}" loading="lazy" width="64" height="96">
                                            <span class="profile-watchlist-item__title">{item.title}</span>
                                        </a>
                                        <button type="button"
                                                class="dontusebuttonclass profile-watchlist-item__remove"
                                                data-watchlist-remove
                                                data-list-id="{item.list_id}"
                                                data-series-id="{item.series_id}"
                                                title="Убрать из списка"
                                                aria-label="Убрать из списка">
                                            <span class="fa fa-trash-o" aria-hidden="true"></span>
                                        </button>
                                    </article>
                                [/loop]
                            </div>
                            <p class="profile-empty" data-watchlist-empty [item.items] hidden[/item.items]>Список пуст</p>
                        </div>
                    [/loop]
                </div>
            </section>

            [has_notifications]
            <section class="profile-panel" data-profile-panel="notifications" hidden id="notifications">
                <div class="profile-grid">
                    <div class="profile-card">
                        <h2>Каналы уведомлений</h2>
                        <p class="profile-card__hint">Выберите, как получать сообщения о новых сериях подписанных сериалов.</p>
                        <form class="profile-notification-prefs" id="notificationPrefsForm">
                            <label class="profile-field profile-field--checkbox">
                                <input type="checkbox" name="notify_via_email" value="1" [profile.notify_via_email]checked[/profile.notify_via_email]>
                                <span>На email ({profile.email|raw})</span>
                            </label>
                            <label class="profile-field profile-field--checkbox">
                                <input type="checkbox" name="notify_via_site" value="1" [profile.notify_via_site]checked[/profile.notify_via_site]>
                                <span>В виджет уведомлений в шапке сайта</span>
                            </label>
                            <label class="profile-field profile-field--checkbox">
                                <input type="checkbox" name="notify_via_push" value="1" [profile.notify_via_push]checked[/profile.notify_via_push]>
                                <span>{notifications_ui_push_label}</span>
                            </label>
                            <button type="submit" class="profile-btn">Сохранить</button>
                        </form>
                    </div>

                    <div class="profile-card profile-card--full">
                        <h2>Подписки на сериалы</h2>
                        <div class="series-notifications-list">
                            [loop notification_subscriptions]
                                <article class="series-notification-card">
                                    <a class="series-notification-poster" href="{item.series_url|raw}">
                                        <img src="{item.poster_url|raw}" alt="{item.series_title}" loading="lazy">
                                    </a>
                                    <div class="series-notification-body">
                                        <div class="series-notification-top">
                                            <a class="series-notification-name" href="{item.series_url|raw}">{item.series_title}</a>
                                        </div>
                                        <div class="series-notification-meta">
                                            <span class="series-notification-badge series-notification-badge--voice">{item.voices_text}</span>
                                        </div>
                                    </div>
                                    <button type="button" class="series-notification-delete" data-unsubscribe-series="{item.series_id}">Отключить</button>
                                </article>
                            [/loop]
                            [not-notification_subscriptions]
                                <div class="series-notifications-empty">
                                    <strong>Подписок пока нет</strong>
                                    Настройте уведомления на странице интересующего сериала.
                                </div>
                            [/not-notification_subscriptions]
                        </div>
                    </div>
                </div>
            </section>
            [/has_notifications]

            <section class="profile-panel" data-profile-panel="comments" hidden>
                <div class="profile-card profile-card--full">
                    <h2>Мои комментарии</h2>
                    <div class="series-notifications-list">
                        [loop profile_comments]
                            <article class="series-notification-card">
                                <a class="series-notification-poster" href="{item.series_url|raw}">
                                    <img src="{item.poster_url|raw}" alt="{item.series_title}" loading="lazy">
                                </a>
                                <div class="series-notification-body">
                                    <div class="series-notification-top">
                                        <a class="series-notification-name" href="{item.series_url|raw}">{item.series_title}</a>
                                        <span class="series-notification-date">{item.created_at}</span>
                                    </div>
                                    <div class="series-notification-title">{item.body_html|raw}</div>
                                    <div class="series-notification-meta">
                                        <span class="series-notification-badge {item.status_badge_class}">{item.status_label}</span>
                                    </div>
                                </div>
                            </article>
                        [/loop]
                        [not-profile_comments]
                            <div class="series-notifications-empty">
                                <strong>Комментариев пока нет</strong>
                                Оставьте первый комментарий на странице интересующего сериала.
                            </div>
                        [/not-profile_comments]
                    </div>
                </div>
            </section>
        </div>
    </div>
</main>
