<?php

namespace App\Support;

use App\Models\SiteSetting;
use Illuminate\Validation\Rules\Password;

class SiteConfig
{
    /**
     * @return array<string, array{type: string, default: string, group: string, label: string, description?: string, min?: int, max?: int}>
     */
    public static function definitions(): array
    {
        return [
            // Auth — toggles
            'auth_login_enabled' => ['type' => 'bool', 'default' => '1', 'group' => 'auth', 'label' => 'Вход на сайт'],
            'auth_register_enabled' => ['type' => 'bool', 'default' => '1', 'group' => 'auth', 'label' => 'Регистрация'],
            'auth_password_reset_enabled' => ['type' => 'bool', 'default' => '1', 'group' => 'auth', 'label' => 'Восстановление пароля'],
            'auth_profile_enabled' => ['type' => 'bool', 'default' => '1', 'group' => 'auth', 'label' => 'Личный кабинет'],

            // Auth — limits
            'auth_password_min_length' => ['type' => 'int', 'default' => '8', 'group' => 'auth', 'label' => 'Мин. длина пароля', 'min' => 6, 'max' => 128],
            'auth_name_max_length' => ['type' => 'int', 'default' => '120', 'group' => 'auth', 'label' => 'Макс. длина имени', 'min' => 2, 'max' => 255],
            'auth_email_max_length' => ['type' => 'int', 'default' => '255', 'group' => 'auth', 'label' => 'Макс. длина email', 'min' => 32, 'max' => 255],

            // Auth — messages
            'auth_msg_login_failed' => ['type' => 'string', 'default' => 'Неверный email или пароль.', 'group' => 'auth', 'label' => 'Ошибка входа'],
            'auth_msg_account_blocked' => ['type' => 'string', 'default' => 'Аккаунт заблокирован.', 'group' => 'auth', 'label' => 'Аккаунт заблокирован'],
            'auth_msg_login_success' => ['type' => 'string', 'default' => 'Вы вошли в аккаунт.', 'group' => 'auth', 'label' => 'Успешный вход'],
            'auth_msg_register_success' => ['type' => 'string', 'default' => 'Аккаунт создан. Добро пожаловать!', 'group' => 'auth', 'label' => 'Успешная регистрация'],
            'auth_msg_logout_success' => ['type' => 'string', 'default' => 'Вы вышли из аккаунта.', 'group' => 'auth', 'label' => 'Выход'],
            'auth_msg_reset_link_sent' => ['type' => 'string', 'default' => 'Если email зарегистрирован, мы отправили ссылку для сброса пароля.', 'group' => 'auth', 'label' => 'Ссылка на сброс отправлена'],
            'auth_msg_password_updated' => ['type' => 'string', 'default' => 'Пароль обновлён. Войдите с новым паролем.', 'group' => 'auth', 'label' => 'Пароль обновлён'],
            'auth_msg_reset_invalid_token' => ['type' => 'string', 'default' => 'Ссылка для сброса пароля недействительна или устарела.', 'group' => 'auth', 'label' => 'Неверная ссылка сброса'],
            'auth_msg_reset_user_not_found' => ['type' => 'string', 'default' => 'Пользователь с таким email не найден.', 'group' => 'auth', 'label' => 'Email не найден'],
            'auth_msg_reset_failed' => ['type' => 'string', 'default' => 'Не удалось сбросить пароль. Попробуйте запросить ссылку ещё раз.', 'group' => 'auth', 'label' => 'Сброс не удался'],
            'auth_msg_auth_required' => ['type' => 'string', 'default' => 'Требуется авторизация', 'group' => 'auth', 'label' => 'Нужна авторизация'],

            // Auth — UI
            'auth_ui_login_title' => ['type' => 'string', 'default' => 'Вход в аккаунт', 'group' => 'auth', 'label' => 'Заголовок входа'],
            'auth_ui_register_title' => ['type' => 'string', 'default' => 'Регистрация', 'group' => 'auth', 'label' => 'Заголовок регистрации'],
            'auth_ui_forgot_title' => ['type' => 'string', 'default' => 'Сброс пароля', 'group' => 'auth', 'label' => 'Заголовок сброса'],
            'auth_ui_reset_title' => ['type' => 'string', 'default' => 'Новый пароль', 'group' => 'auth', 'label' => 'Заголовок нового пароля'],
            'auth_ui_forgot_hint' => ['type' => 'string', 'default' => 'Укажите email — мы отправим ссылку для создания нового пароля.', 'group' => 'auth', 'label' => 'Подсказка сброса'],
            'auth_ui_header_login' => ['type' => 'string', 'default' => 'Войти', 'group' => 'auth', 'label' => 'Кнопка входа в шапке'],
            'auth_ui_header_profile' => ['type' => 'string', 'default' => 'Профиль', 'group' => 'auth', 'label' => 'Ссылка профиля в шапке'],

            // Comments — toggles
            'comments_enabled' => ['type' => 'bool', 'default' => '1', 'group' => 'comments', 'label' => 'Комментарии на странице сериала'],
            'comments_guest_enabled' => ['type' => 'bool', 'default' => '1', 'group' => 'comments', 'label' => 'Комментарии от гостей'],
            'comments_vote_enabled' => ['type' => 'bool', 'default' => '1', 'group' => 'comments', 'label' => 'Голоса за комментарии'],
            'comments_vote_guest_enabled' => ['type' => 'bool', 'default' => '1', 'group' => 'comments', 'label' => 'Голоса гостей за комментарии'],

            // Comments — limits
            'comments_body_min_length' => ['type' => 'int', 'default' => '2', 'group' => 'comments', 'label' => 'Мин. длина комментария', 'min' => 1, 'max' => 1000],
            'comments_body_max_length' => ['type' => 'int', 'default' => '5000', 'group' => 'comments', 'label' => 'Макс. длина комментария', 'min' => 100, 'max' => 20000],
            'comments_guest_name_max_length' => ['type' => 'int', 'default' => '120', 'group' => 'comments', 'label' => 'Макс. длина имени гостя', 'min' => 2, 'max' => 255],
            'comments_max_reply_depth' => ['type' => 'int', 'default' => '8', 'group' => 'comments', 'label' => 'Макс. глубина ответов', 'min' => 1, 'max' => 20],
            'profile_comments_limit' => ['type' => 'int', 'default' => '20', 'group' => 'comments', 'label' => 'Комментариев в профиле', 'min' => 5, 'max' => 100],

            // Comments — messages
            'comments_msg_guest_name_required' => ['type' => 'string', 'default' => 'Укажите имя или отметьте «Анонимно».', 'group' => 'comments', 'label' => 'Имя гостя не указано'],
            'comments_msg_pending' => ['type' => 'string', 'default' => 'Комментарий отправлен на модерацию.', 'group' => 'comments', 'label' => 'На модерации'],
            'comments_msg_published' => ['type' => 'string', 'default' => 'Комментарий опубликован.', 'group' => 'comments', 'label' => 'Опубликован'],
            'comments_msg_max_depth' => ['type' => 'string', 'default' => 'Достигнута максимальная глубина ответов.', 'group' => 'comments', 'label' => 'Макс. глубина'],
            'comments_msg_too_short' => ['type' => 'string', 'default' => 'Комментарий слишком короткий.', 'group' => 'comments', 'label' => 'Слишком короткий'],
            'comments_msg_submit_failed' => ['type' => 'string', 'default' => 'Не удалось отправить комментарий.', 'group' => 'comments', 'label' => 'Ошибка отправки'],
            'comments_msg_disabled' => ['type' => 'string', 'default' => 'Комментарии отключены.', 'group' => 'comments', 'label' => 'Комментарии отключены'],
            'comments_msg_links_forbidden' => ['type' => 'string', 'default' => 'Ссылки в комментариях запрещены.', 'group' => 'comments', 'label' => 'Ссылки запрещены'],

            // Comments — UI
            'comments_ui_title' => ['type' => 'string', 'default' => 'Комментарии', 'group' => 'comments', 'label' => 'Заголовок блока'],
            'comments_ui_label' => ['type' => 'string', 'default' => 'Напишите комментарий', 'group' => 'comments', 'label' => 'Подпись поля'],
            'comments_ui_placeholder' => ['type' => 'string', 'default' => 'Поделитесь впечатлениями о сериале...', 'group' => 'comments', 'label' => 'Placeholder'],
            'comments_ui_guest_name' => ['type' => 'string', 'default' => 'Ваше имя', 'group' => 'comments', 'label' => 'Поле имени гостя'],
            'comments_ui_anonymous' => ['type' => 'string', 'default' => 'Анонимно', 'group' => 'comments', 'label' => 'Чекбокс анонимно'],
            'comments_ui_submit' => ['type' => 'string', 'default' => 'Отправить', 'group' => 'comments', 'label' => 'Кнопка отправки'],
            'comments_ui_loading' => ['type' => 'string', 'default' => 'Загрузка комментариев...', 'group' => 'comments', 'label' => 'Загрузка'],
            'comments_ui_empty' => ['type' => 'string', 'default' => 'Пока нет комментариев. Будьте первым!', 'group' => 'comments', 'label' => 'Пустой список'],
            'comments_ui_load_error' => ['type' => 'string', 'default' => 'Не удалось загрузить комментарии.', 'group' => 'comments', 'label' => 'Ошибка загрузки'],
            'comments_ui_reply_placeholder' => ['type' => 'string', 'default' => 'Ваш ответ...', 'group' => 'comments', 'label' => 'Placeholder ответа'],
            'comments_ui_reply' => ['type' => 'string', 'default' => 'Ответить', 'group' => 'comments', 'label' => 'Кнопка ответа'],
            'comments_ui_cancel' => ['type' => 'string', 'default' => 'Отмена', 'group' => 'comments', 'label' => 'Отмена'],
            'comments_ui_vote_like' => ['type' => 'string', 'default' => 'Нравится', 'group' => 'comments', 'label' => 'Лайк комментария'],
            'comments_ui_vote_dislike' => ['type' => 'string', 'default' => 'Не нравится', 'group' => 'comments', 'label' => 'Дизлайк комментария'],
            'comments_ui_spoiler' => ['type' => 'string', 'default' => 'Спойлер', 'group' => 'comments', 'label' => 'Кнопка спойлера'],
            'comments_ui_spoiler_hint' => ['type' => 'string', 'default' => 'Сюжетные спойлеры оформляйте кнопкой «Спойлер» или тегами [spoiler]текст[/spoiler]. Ссылки запрещены.', 'group' => 'comments', 'label' => 'Подсказка к полю'],
            'comments_ui_spoiler_reveal' => ['type' => 'string', 'default' => 'Спойлер', 'group' => 'comments', 'label' => 'Показать спойлер'],
            'comments_ui_spoiler_hide' => ['type' => 'string', 'default' => 'Скрыть спойлер', 'group' => 'comments', 'label' => 'Скрыть спойлер'],
            'comments_label_anonymous' => ['type' => 'string', 'default' => 'Аноним', 'group' => 'comments', 'label' => 'Имя анонима'],
            'comments_label_guest' => ['type' => 'string', 'default' => 'Гость', 'group' => 'comments', 'label' => 'Имя гостя'],
            'comments_label_user' => ['type' => 'string', 'default' => 'Пользователь', 'group' => 'comments', 'label' => 'Имя пользователя без профиля'],
            'comments_ui_sort_label' => ['type' => 'string', 'default' => 'Сортировка', 'group' => 'comments', 'label' => 'Подпись сортировки'],
            'comments_ui_sort_date' => ['type' => 'string', 'default' => 'По дате', 'group' => 'comments', 'label' => 'Сортировка по дате'],
            'comments_ui_sort_rating' => ['type' => 'string', 'default' => 'По рейтингу', 'group' => 'comments', 'label' => 'Сортировка по рейтингу'],
            'comments_ui_pinned' => ['type' => 'string', 'default' => 'Закреплён', 'group' => 'comments', 'label' => 'Метка закреплённого'],

            // Ratings
            'series_vote_enabled' => ['type' => 'bool', 'default' => '1', 'group' => 'ratings', 'label' => 'Лайк/дизлайк сериала'],
            'series_vote_guest_enabled' => ['type' => 'bool', 'default' => '1', 'group' => 'ratings', 'label' => 'Голоса гостей за сериал'],
            'anticipation_enabled' => ['type' => 'bool', 'default' => '1', 'group' => 'ratings', 'label' => 'Рейтинг ожидания (Скоро)'],
            'anticipation_guest_enabled' => ['type' => 'bool', 'default' => '1', 'group' => 'ratings', 'label' => 'Голоса гостей в разделе Скоро'],
            'coming_soon_per_page' => ['type' => 'int', 'default' => '20', 'group' => 'catalog', 'label' => 'Сериалов на странице «Скоро»', 'min' => 5, 'max' => 60],
            'reactions_guest_enabled' => ['type' => 'bool', 'default' => '1', 'group' => 'ratings', 'label' => 'Реакции от гостей'],
            'reactions_msg_disabled' => ['type' => 'string', 'default' => 'Виджет реакций отключён.', 'group' => 'ratings', 'label' => 'Реакции отключены'],
            'reactions_msg_save_failed' => ['type' => 'string', 'default' => 'Не удалось сохранить оценку. Попробуйте позже.', 'group' => 'ratings', 'label' => 'Ошибка сохранения реакции'],
            'series_vote_msg_disabled' => ['type' => 'string', 'default' => 'Голосование отключено.', 'group' => 'ratings', 'label' => 'Голосование отключено'],

            // Watchlists & notifications
            'watchlists_enabled' => ['type' => 'bool', 'default' => '1', 'group' => 'engagement', 'label' => 'Списки просмотра'],
            'notifications_enabled' => ['type' => 'bool', 'default' => '1', 'group' => 'engagement', 'label' => 'Уведомления о сериях'],
            'watchlist_name_min_length' => ['type' => 'int', 'default' => '2', 'group' => 'engagement', 'label' => 'Мин. длина названия списка', 'min' => 1, 'max' => 50],
            'watchlist_name_max_length' => ['type' => 'int', 'default' => '120', 'group' => 'engagement', 'label' => 'Макс. длина названия списка', 'min' => 10, 'max' => 255],
            'watchlist_ui_add_label' => ['type' => 'string', 'default' => 'Добавить в список', 'group' => 'engagement', 'label' => 'Кнопка списков'],
            'watchlist_ui_login_hint' => ['type' => 'string', 'default' => 'Войдите, чтобы сохранять списки', 'group' => 'engagement', 'label' => 'Подсказка для гостя'],
            'favourites_enabled' => ['type' => 'bool', 'default' => '1', 'group' => 'engagement', 'label' => 'Избранное'],
            'favourites_guest_enabled' => ['type' => 'bool', 'default' => '1', 'group' => 'engagement', 'label' => 'Избранное для гостей'],
            'favourites_list_limit' => ['type' => 'int', 'default' => '100', 'group' => 'engagement', 'label' => 'Лимит избранного', 'min' => 10, 'max' => 500],
            'favourites_ui_add_label' => ['type' => 'string', 'default' => 'В избранное', 'group' => 'engagement', 'label' => 'Кнопка «В избранное»'],
            'favourites_ui_remove_label' => ['type' => 'string', 'default' => 'В избранном', 'group' => 'engagement', 'label' => 'Кнопка «В избранном»'],
            'favourites_ui_page_title' => ['type' => 'string', 'default' => 'Избранное', 'group' => 'engagement', 'label' => 'Заголовок страницы избранного'],
            'favourites_ui_empty' => ['type' => 'string', 'default' => 'Вы ещё не добавили сериалы в избранное. Откройте страницу сериала и нажмите «В избранное».', 'group' => 'engagement', 'label' => 'Пустое избранное'],
            'favourites_meta_title' => ['type' => 'string', 'default' => 'Избранное — мои сериалы', 'group' => 'engagement', 'label' => 'Meta title избранного'],
            'favourites_meta_description' => ['type' => 'string', 'default' => 'Список избранных сериалов для быстрого доступа.', 'group' => 'engagement', 'label' => 'Meta description избранного'],
            'watch_history_enabled' => ['type' => 'bool', 'default' => '1', 'group' => 'engagement', 'label' => 'История просмотров'],
            'watch_history_guest_enabled' => ['type' => 'bool', 'default' => '1', 'group' => 'engagement', 'label' => 'История просмотров для гостей'],
            'watch_history_home_limit' => ['type' => 'int', 'default' => '20', 'group' => 'engagement', 'label' => 'Сериалов в блоке истории на главной', 'min' => 4, 'max' => 40],
            'watch_history_max_items' => ['type' => 'int', 'default' => '100', 'group' => 'engagement', 'label' => 'Макс. записей в истории', 'min' => 20, 'max' => 500],
            'watch_history_ui_title' => ['type' => 'string', 'default' => 'История просмотров', 'group' => 'engagement', 'label' => 'Заголовок блока истории'],
            'notifications_msg_saved' => ['type' => 'string', 'default' => 'Настройки уведомлений сохранены.', 'group' => 'engagement', 'label' => 'Уведомления сохранены'],
            'notifications_ui_title' => ['type' => 'string', 'default' => 'Уведомления о выходе серий', 'group' => 'engagement', 'label' => 'Заголовок модалки'],
            'notifications_ui_subscribe_title' => ['type' => 'string', 'default' => 'Следите за выходом новых серий', 'group' => 'engagement', 'label' => 'Заголовок блока подписки'],
            'notifications_ui_subscribe_text' => ['type' => 'string', 'default' => 'Нажмите «Подписаться» — уведомление придёт, когда выйдет следующая серия. Озвучки можно выбрать в настройках.', 'group' => 'engagement', 'label' => 'Текст блока подписки'],
            'notifications_ui_subscribe_btn' => ['type' => 'string', 'default' => 'Подписаться', 'group' => 'engagement', 'label' => 'Кнопка подписки'],
            'notifications_ui_subscribe_btn_guest' => ['type' => 'string', 'default' => 'Войти и подписаться', 'group' => 'engagement', 'label' => 'Кнопка подписки для гостя'],
            'notifications_ui_unsubscribe_btn' => ['type' => 'string', 'default' => 'Отписаться', 'group' => 'engagement', 'label' => 'Кнопка отписки'],
            'notifications_ui_subscribed_badge' => ['type' => 'string', 'default' => 'Вы подписаны на новые серии', 'group' => 'engagement', 'label' => 'Метка активной подписки'],
            'notifications_msg_prefs_saved' => ['type' => 'string', 'default' => 'Настройки уведомлений обновлены.', 'group' => 'engagement', 'label' => 'Настройки профиля сохранены'],
            'notifications_msg_unsubscribed' => ['type' => 'string', 'default' => 'Уведомления для сериала отключены.', 'group' => 'engagement', 'label' => 'Отписка от сериала'],
            'notifications_inbox_limit' => ['type' => 'int', 'default' => '30', 'group' => 'engagement', 'label' => 'Лимит уведомлений в шапке', 'min' => 5, 'max' => 100],
            'telegram_url' => ['type' => 'string', 'default' => '', 'group' => 'engagement', 'label' => 'Ссылка Telegram на странице сериала'],
            'telegram_label' => ['type' => 'string', 'default' => 'Наш Telegram', 'group' => 'engagement', 'label' => 'Текст кнопки Telegram'],
            'card_badge_popular_days' => ['type' => 'int', 'default' => '3', 'group' => 'engagement', 'label' => 'Дней для бейджа «Популярно»', 'min' => 1, 'max' => 30],
            'card_badge_popular_min_views' => ['type' => 'int', 'default' => '50', 'group' => 'engagement', 'label' => 'Мин. просмотров для «Популярно»', 'min' => 1, 'max' => 100000],
            'card_badge_popular_percentile' => ['type' => 'int', 'default' => '15', 'group' => 'engagement', 'label' => 'Топ % сериалов для «Популярно»', 'min' => 1, 'max' => 50],
            'card_badge_new_episode_days' => ['type' => 'int', 'default' => '2', 'group' => 'engagement', 'label' => 'Дней для бейджа «Новая серия»', 'min' => 1, 'max' => 14],
            'card_badge_new_episode_label' => ['type' => 'string', 'default' => 'Новая серия', 'group' => 'engagement', 'label' => 'Текст бейджа новой серии'],
            'card_badge_popular_label' => ['type' => 'string', 'default' => 'Популярно', 'group' => 'engagement', 'label' => 'Текст бейджа популярности'],
            'card_reaction_min_votes' => ['type' => 'int', 'default' => '1', 'group' => 'engagement', 'label' => 'Мин. голосов для эмоджи на карточке', 'min' => 1, 'max' => 100],

            // CDN VideoHub auto-player
            'player_cdnvideohub_auto_enabled' => [
                'type' => 'bool',
                'default' => '0',
                'group' => 'players',
                'label' => 'Автодобавление при импорте',
                'description' => 'Автоматически создавать вкладку плеера при импорте из KinoPoisk и Alloha',
            ],
            'player_cdnvideohub_tab_name' => [
                'type' => 'string',
                'default' => 'Coldfilm',
                'group' => 'players',
                'label' => 'Название вкладки',
            ],
            'player_cdnvideohub_priority' => [
                'type' => 'int',
                'default' => '100',
                'group' => 'players',
                'label' => 'Приоритет вкладки',
                'description' => 'Чем выше — тем левее вкладка',
                'min' => 0,
                'max' => 1000,
            ],
            'player_cdnvideohub_element_id' => [
                'type' => 'string',
                'default' => 'cdnvideohubvideoplayer',
                'group' => 'players',
                'label' => 'id элемента <video-player>',
            ],
            'player_cdnvideohub_publisher_id' => [
                'type' => 'string',
                'default' => '15',
                'group' => 'players',
                'label' => 'data-publisher-id',
            ],
            'player_cdnvideohub_is_show_banner' => [
                'type' => 'bool',
                'default' => '0',
                'group' => 'players',
                'label' => 'is-show-banner',
            ],
            'player_cdnvideohub_is_show_voice_only' => [
                'type' => 'bool',
                'default' => '0',
                'group' => 'players',
                'label' => 'is-show-voice-only',
            ],
            'player_cdnvideohub_aggregator' => [
                'type' => 'string',
                'default' => 'kp',
                'group' => 'players',
                'label' => 'data-aggregator',
                'description' => 'Источник ID: kp — KinoPoisk',
            ],
            'player_cdnvideohub_script_url' => [
                'type' => 'string',
                'default' => 'https://player.cdnvideohub.com/s2/stable/video-player.umd.js',
                'group' => 'players',
                'label' => 'URL скрипта плеера',
            ],
            'player_alloha_sync_enabled' => [
                'type' => 'bool',
                'default' => '1',
                'group' => 'players',
                'label' => 'Импорт плееров из Alloha',
                'description' => 'Если выключено — при импорте Alloha плееры не добавляются (CDN VideoHub работает отдельно)',
            ],

            // Import limits (KinoPoisk / Alloha / TMDB)
            'import_max_actors' => [
                'type' => 'int',
                'default' => '35',
                'group' => 'integrations',
                'label' => 'Макс. актёров при импорте',
                'description' => 'Лимит актёров при импорте из KinoPoisk, Alloha и TMDB. Большие списки сильно замедляют парсинг (скачивание фото).',
                'min' => 1,
                'max' => 200,
            ],

            // Catalog & navigation
            'catalog_per_page' => ['type' => 'int', 'default' => '18', 'group' => 'catalog', 'label' => 'Сериалов на странице каталога', 'min' => 6, 'max' => 60],
            'catalog_heading' => ['type' => 'string', 'default' => 'Все сериалы', 'group' => 'catalog', 'label' => 'Заголовок страницы каталога'],
            'catalog_meta_title' => ['type' => 'string', 'default' => 'Все сериалы — смотреть онлайн бесплатно', 'group' => 'catalog', 'label' => 'Meta title каталога'],
            'catalog_meta_description' => ['type' => 'string', 'default' => 'Полный каталог сериалов: фильтры по жанру, стране, году и рейтингу.', 'group' => 'catalog', 'label' => 'Meta description каталога'],
            'search_per_page' => ['type' => 'int', 'default' => '24', 'group' => 'catalog', 'label' => 'Результатов поиска на странице', 'min' => 6, 'max' => 60],
            'search_suggest_min_chars' => ['type' => 'int', 'default' => '2', 'group' => 'catalog', 'label' => 'Мин. символов для быстрого поиска', 'min' => 1, 'max' => 6],
            'search_suggest_limit' => ['type' => 'int', 'default' => '5', 'group' => 'catalog', 'label' => 'Результатов в каждой группе быстрого поиска', 'min' => 2, 'max' => 10],
            'search_full_group_limit' => ['type' => 'int', 'default' => '12', 'group' => 'catalog', 'label' => 'Результатов в каждой группе полного поиска', 'min' => 4, 'max' => 60],
            'search_popular_limit' => ['type' => 'int', 'default' => '20', 'group' => 'catalog', 'label' => 'Популярных поисковых запросов на странице', 'min' => 5, 'max' => 50],
            'collections_per_page' => ['type' => 'int', 'default' => '12', 'group' => 'catalog', 'label' => 'Сериалов в подборке на странице', 'min' => 6, 'max' => 48],
            'collections_index_limit' => ['type' => 'int', 'default' => '40', 'group' => 'catalog', 'label' => 'Подборок на странице списка', 'min' => 6, 'max' => 100],
            'studios_per_page' => ['type' => 'int', 'default' => '12', 'group' => 'catalog', 'label' => 'Сериалов студии на странице', 'min' => 6, 'max' => 48],
            'studios_index_limit' => ['type' => 'int', 'default' => '40', 'group' => 'catalog', 'label' => 'Студий на странице списка', 'min' => 6, 'max' => 100],
            'home_studios_limit' => ['type' => 'int', 'default' => '8', 'group' => 'catalog', 'label' => 'Студий на главной', 'min' => 2, 'max' => 24],
            'home_studios_sort' => [
                'type' => 'enum',
                'default' => 'catalog',
                'group' => 'catalog',
                'label' => 'Сортировка студий на главной',
                'options' => [
                    'catalog' => 'По закреплению и порядку',
                    'items_desc' => 'По количеству сериалов (убыв.)',
                    'items_asc' => 'По количеству сериалов (возр.)',
                    'title_asc' => 'Название (А→Я)',
                    'title_desc' => 'Название (Я→А)',
                ],
            ],
            'studios_index_sort' => [
                'type' => 'enum',
                'default' => 'catalog',
                'group' => 'catalog',
                'label' => 'Сортировка студий в списке',
                'options' => [
                    'catalog' => 'По закреплению и порядку',
                    'items_desc' => 'По количеству сериалов (убыв.)',
                    'items_asc' => 'По количеству сериалов (возр.)',
                    'title_asc' => 'Название (А→Я)',
                    'title_desc' => 'Название (Я→А)',
                ],
            ],
            'home_popular_limit' => ['type' => 'int', 'default' => '16', 'group' => 'catalog', 'label' => 'Популярных на главной', 'min' => 4, 'max' => 40],
            'nav_mega_genres_limit' => ['type' => 'int', 'default' => '14', 'group' => 'catalog', 'label' => 'Жанров в mega-menu', 'min' => 4, 'max' => 30],
            'nav_mega_countries_limit' => ['type' => 'int', 'default' => '10', 'group' => 'catalog', 'label' => 'Стран в mega-menu', 'min' => 4, 'max' => 30],
            'search_placeholder_desktop' => ['type' => 'string', 'default' => 'Поиск...', 'group' => 'catalog', 'label' => 'Placeholder поиска (desktop)'],
            'search_placeholder_mobile' => ['type' => 'string', 'default' => 'Что ищем?', 'group' => 'catalog', 'label' => 'Placeholder поиска (mobile)'],

            // SEO / content
            'seo_google_verification' => [
                'type' => 'string',
                'default' => '',
                'group' => 'seo',
                'label' => 'Google Search Console',
                'description' => 'Значение content из meta-тега google-site-verification',
            ],
            'seo_yandex_verification' => [
                'type' => 'string',
                'default' => '',
                'group' => 'seo',
                'label' => 'Яндекс Вебмастер',
                'description' => 'Значение content из meta-тега yandex-verification',
            ],
            'seo_counters_code' => [
                'type' => 'html',
                'default' => '',
                'group' => 'seo',
                'label' => 'Код счётчиков и метрик',
                'description' => 'HTML/JavaScript: Яндекс.Метрика, Google Analytics, LiveInternet и др. Выводится перед </body> на всех страницах.',
            ],
            'series_meta_title_suffix' => ['type' => 'string', 'default' => ' смотреть онлайн в хорошем HD качестве бесплатно', 'group' => 'seo', 'label' => 'Суффикс title сериала'],
            'series_meta_description_fallback' => ['type' => 'string', 'default' => 'Смотреть бесплатно онлайн.', 'group' => 'seo', 'label' => 'Fallback description сериала'],
            'home_meta_title' => ['type' => 'string', 'default' => 'Сериалы онлайн, смотреть в хорошем HD качестве бесплатно', 'group' => 'seo', 'label' => 'Meta title главной'],
            'home_meta_description' => ['type' => 'string', 'default' => 'Lordserials — смотреть новые серии любимых сериалов в хорошем переводе бесплатно.', 'group' => 'seo', 'label' => 'Meta description главной'],
            'series_ui_player_empty' => ['type' => 'string', 'default' => 'Плеер скоро будет добавлен', 'group' => 'seo', 'label' => 'Текст без плеера'],
            'series_ui_bookmark_hint' => ['type' => 'string', 'default' => 'Добавляйте сайт в закладки, чтобы ничего не пропустить!', 'group' => 'seo', 'label' => 'Подсказка под плеером'],
            'series_ui_bookmark_modal_title' => ['type' => 'string', 'default' => 'Как добавить сайт в закладки', 'group' => 'seo', 'label' => 'Заголовок модалки закладок'],
            'series_ui_bookmark_thanks' => ['type' => 'string', 'default' => 'Спасибо! Сайт добавлен в закладки — вы больше ничего не пропустите.', 'group' => 'seo', 'label' => 'Сообщение после Ctrl+D'],
            'series_share_widget_code' => [
                'type' => 'html',
                'default' => '',
                'group' => 'seo',
                'label' => 'Виджет «Поделиться» под плеером',
                'description' => 'HTML/JavaScript-код виджета соцсетей (Яндекс.Поделиться, uSocial и др.). Выводится слева под плеером на странице сериала.',
            ],

            // Image optimization
            'images_optimize_enabled' => [
                'type' => 'bool',
                'default' => '1',
                'group' => 'optimization',
                'label' => 'Оптимизация изображений',
                'description' => 'Сжатие и изменение размера при загрузке постеров и обложек',
            ],
            'images_poster_max_width' => [
                'type' => 'int',
                'default' => '400',
                'group' => 'optimization',
                'label' => 'Макс. ширина постера (px)',
                'description' => '0 — без ограничения по ширине',
                'min' => 0,
                'max' => 4000,
            ],
            'images_poster_max_height' => [
                'type' => 'int',
                'default' => '0',
                'group' => 'optimization',
                'label' => 'Макс. высота постера (px)',
                'description' => '0 — без ограничения по высоте',
                'min' => 0,
                'max' => 4000,
            ],
            'images_poster_quality' => [
                'type' => 'int',
                'default' => '85',
                'group' => 'optimization',
                'label' => 'Качество сжатия (%)',
                'min' => 10,
                'max' => 100,
            ],
            'images_poster_format' => [
                'type' => 'enum',
                'default' => 'keep',
                'group' => 'optimization',
                'label' => 'Формат файла',
                'options' => [
                    'keep' => 'Как в оригинале',
                    'jpg' => 'JPEG',
                    'webp' => 'WebP',
                    'png' => 'PNG',
                ],
            ],
            'images_poster_filename' => [
                'type' => 'enum',
                'default' => 'kp_prefix',
                'group' => 'optimization',
                'label' => 'Имя файла постера сериала',
                'description' => 'Шаблон имени в /storage/posters/',
                'options' => [
                    'kp_prefix' => 'kp-{id} (например kp-357)',
                    'kp_id' => 'Только ID ({id})',
                    'slug' => 'Slug сериала',
                    'title_year' => 'Название-год (title-2024)',
                ],
            ],
            'images_collection_filename' => [
                'type' => 'enum',
                'default' => 'collection_slug',
                'group' => 'optimization',
                'label' => 'Имя файла обложки подборки',
                'options' => [
                    'collection_slug' => 'collection-{slug}',
                    'slug' => 'Только slug подборки',
                ],
            ],
            'images_studio_filename' => [
                'type' => 'enum',
                'default' => 'studio_slug',
                'group' => 'optimization',
                'label' => 'Имя файла логотипа студии',
                'options' => [
                    'studio_slug' => 'studio-{slug}',
                    'slug' => 'Только slug студии',
                ],
            ],
            'images_poster_max_upload_kb' => [
                'type' => 'int',
                'default' => '5120',
                'group' => 'optimization',
                'label' => 'Макс. размер загрузки (КБ)',
                'min' => 100,
                'max' => 20480,
            ],

            // Maintenance
            'maintenance_enabled' => [
                'type' => 'bool',
                'default' => '0',
                'group' => 'maintenance',
                'label' => 'Режим технического обслуживания',
                'description' => 'Сайт закрыт для посетителей и поисковиков. Доступ остаётся только у администраторов.',
            ],
            'maintenance_title' => [
                'type' => 'string',
                'default' => 'Сайт на техническом обслуживании',
                'group' => 'maintenance',
                'label' => 'Заголовок заглушки',
            ],
            'maintenance_message' => [
                'type' => 'string',
                'default' => 'Мы проводим технические работы. Скоро вернёмся!',
                'group' => 'maintenance',
                'label' => 'Текст заглушки',
            ],

            // General UI
            'ui_msg_server_error' => ['type' => 'string', 'default' => 'Ошибка сервера. Попробуйте позже.', 'group' => 'general', 'label' => 'Ошибка сервера'],
            'ui_msg_generic_error' => ['type' => 'string', 'default' => 'Произошла ошибка', 'group' => 'general', 'label' => 'Общая ошибка'],
            'ui_msg_session_expired' => ['type' => 'string', 'default' => 'Сессия истекла. Обновите страницу и попробуйте снова.', 'group' => 'general', 'label' => 'Сессия истекла'],
            'ui_msg_form_network_error' => ['type' => 'string', 'default' => 'Не удалось отправить форму. Проверьте соединение.', 'group' => 'general', 'label' => 'Ошибка сети формы'],
        ];
    }

    /**
     * @return list<string>
     */
    public static function managedKeys(): array
    {
        return array_keys(self::definitions());
    }

    public static function bool(string $key): bool
    {
        return self::get($key) === '1';
    }

    public static function int(string $key): int
    {
        return (int)self::get($key);
    }

    public static function str(string $key): string
    {
        return self::get($key);
    }

    public static function get(string $key): string
    {
        $definitions = self::definitions();
        $default = $definitions[$key]['default'] ?? '';

        return (string)SiteSetting::get($key, $default);
    }

    public static function passwordRule(): Password
    {
        return Password::min(max(6, self::int('auth_password_min_length')));
    }

    /**
     * @return array<string, bool>
     */
    public static function features(): array
    {
        $features = [];
        foreach (self::definitions() as $key => $definition) {
            if ($definition['type'] === 'bool') {
                $features[$key] = self::bool($key);
            }
        }

        return $features;
    }

    /**
     * @return array<string, string|int|bool>
     */
    public static function forJs(): array
    {
        $payload = [];
        foreach (self::definitions() as $key => $definition) {
            if ($definition['type'] === 'html') {
                continue;
            }

            $payload[$key] = match ($definition['type']) {
                'bool' => self::bool($key),
                'int' => self::int($key),
                default => self::str($key),
            };
        }

        return $payload;
    }

    /**
     * @return array<string, string>
     */
    public static function forTpl(): array
    {
        $vars = [];
        foreach (self::definitions() as $key => $definition) {
            if ($definition['type'] === 'bool') {
                $vars[$key] = self::bool($key) ? '1' : '';
                continue;
            }

            $vars[$key] = (string)match ($definition['type']) {
                'int' => self::int($key),
                default => self::str($key),
            };
        }

        return $vars;
    }

    /**
     * @return array<string, array{title: string, fields: list<array<string, mixed>>}>
     */
    public static function adminGroups(): array
    {
        $groups = [
            'maintenance' => ['title' => 'Техническое обслуживание', 'fields' => []],
            'auth' => ['title' => 'Авторизация', 'fields' => []],
            'comments' => ['title' => 'Комментарии', 'fields' => []],
            'ratings' => ['title' => 'Рейтинги и реакции', 'fields' => []],
            'engagement' => ['title' => 'Списки и уведомления', 'fields' => []],
            'catalog' => ['title' => 'Каталог и навигация', 'fields' => []],
            'optimization' => ['title' => 'Оптимизация изображений', 'fields' => []],
            'players' => ['title' => 'CDN VideoHub', 'fields' => []],
            'integrations' => ['title' => 'Импорт метаданных', 'fields' => []],
            'general' => ['title' => 'Общие сообщения', 'fields' => []],
        ];

        foreach (self::definitions() as $key => $definition) {
            $group = $definition['group'];
            if (!isset($groups[$group])) {
                continue;
            }

            if ($group === 'seo') {
                continue;
            }

            $groups[$group]['fields'][] = [
                'key' => $key,
                'type' => $definition['type'],
                'label' => $definition['label'],
                'description' => $definition['description'] ?? null,
                'min' => $definition['min'] ?? null,
                'max' => $definition['max'] ?? null,
                'options' => $definition['options'] ?? null,
            ];
        }

        return $groups;
    }

    /**
     * @return array<string, array{title: string, fields: list<array<string, mixed>>}>
     */
    public static function seoFields(): array
    {
        $verification = [];
        $counters = [];
        $widgets = [];
        $content = [];

        foreach (self::definitions() as $key => $definition) {
            if ($definition['group'] !== 'seo') {
                continue;
            }

            $field = [
                'key' => $key,
                'type' => $definition['type'],
                'label' => $definition['label'],
                'description' => $definition['description'] ?? null,
            ];

            if (str_contains($key, 'verification')) {
                $verification[] = $field;
            } elseif (str_contains($key, 'counters')) {
                $counters[] = $field;
            } elseif (str_contains($key, 'share_widget')) {
                $widgets[] = $field;
            } else {
                $content[] = $field;
            }
        }

        $groups = [];
        if ($verification !== []) {
            $groups['seo_verification'] = ['title' => 'Верификация поисковиков', 'fields' => $verification];
        }
        if ($counters !== []) {
            $groups['seo_counters'] = ['title' => 'Счётчики и метрики', 'fields' => $counters];
        }
        if ($widgets !== []) {
            $groups['seo_widgets'] = ['title' => 'Виджеты на странице сериала', 'fields' => $widgets];
        }
        if ($content !== []) {
            $groups['seo_content'] = ['title' => 'SEO и тексты сериала', 'fields' => $content];
        }

        return $groups;
    }

    public static function normalizeForSave(string $key, mixed $value): ?string
    {
        $definition = self::definitions()[$key] ?? null;
        if (!$definition) {
            return is_scalar($value) || $value === null ? (string)($value ?? '') : null;
        }

        return match ($definition['type']) {
            'bool' => ($value === true || $value === '1' || $value === 1 || $value === 'true') ? '1' : '0',
            'int' => (string)self::clampInt((int)$value, (int)($definition['min'] ?? PHP_INT_MIN), (int)($definition['max'] ?? PHP_INT_MAX)),
            'enum' => self::normalizeEnum($value, $definition['options'] ?? [], (string)$definition['default']),
            'html' => mb_substr(str_replace("\r\n", "\n", (string)($value ?? '')), 0, 65535),
            default => str_replace("\r\n", "\n", trim((string)($value ?? ''))),
        };
    }

    public static function ensureDefaults(): void
    {
        foreach (self::definitions() as $key => $definition) {
            if (SiteSetting::query()->where('key', $key)->exists()) {
                continue;
            }
            SiteSetting::set($key, $definition['default']);
        }
    }

    private static function clampInt(int $value, int $min, int $max): int
    {
        return max($min, min($max, $value));
    }

    /**
     * @param array<string, string> $options
     */
    private static function normalizeEnum(mixed $value, array $options, string $default): string
    {
        $candidate = trim((string)($value ?? ''));

        return array_key_exists($candidate, $options) ? $candidate : $default;
    }
}
