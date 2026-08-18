<?php

namespace App\Support;

class TplDocumentation
{
    /**
     * @return array<string, mixed>
     */
    public static function payload(): array
    {
        return [
            'syntax' => self::syntaxSections(),
            'contexts' => self::contexts(),
            'hints' => self::hints(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function payloadForAdmin(string $path = ''): array
    {
        $payload = self::payload();
        if ($path === '') {
            return $payload;
        }

        $activeContexts = self::contextsForTemplatePath($path);
        $sampleData = TplSampleValues::forTemplatePath($path);
        $samples = $sampleData['values'];

        $payload['active_contexts'] = $activeContexts;
        $payload['samples'] = $samples;
        $payload['sample_source'] = $sampleData['source'];
        $payload['hints'] = self::enrichHints($payload['hints'], $samples, $activeContexts);

        foreach ($activeContexts as $ctxKey) {
            if (!isset($payload['contexts'][$ctxKey])) {
                continue;
            }
            foreach ($payload['contexts'][$ctxKey]['variables'] as $index => $variable) {
                $name = $variable['name'];
                if (isset($samples[$name]) && $samples[$name] !== '') {
                    $payload['contexts'][$ctxKey]['variables'][$index]['sample'] = TplSampleValues::truncate($samples[$name]);
                }
            }
        }

        return $payload;
    }

    /**
     * @param list<array<string, mixed>> $hints
     * @param array<string, string> $samples
     * @param list<string> $activeContexts
     * @return list<array<string, mixed>>
     */
    private static function enrichHints(array $hints, array $samples, array $activeContexts): array
    {
        $map = [];

        foreach ($hints as $hint) {
            $contexts = $hint['contexts'] ?? [];
            if ($contexts !== [] && !array_intersect($contexts, $activeContexts)) {
                continue;
            }

            $key = ($hint['kind'] ?? '') . '|' . ($hint['insert'] ?? '');
            if (isset($map[$key])) {
                continue;
            }

            $varKey = self::variableKeyFromHint($hint);
            if ($varKey !== null && isset($samples[$varKey]) && $samples[$varKey] !== '') {
                $hint['sample'] = $samples[$varKey];
                $sampleDetail = '→ ' . TplSampleValues::truncate($samples[$varKey]);
                $hint['detail'] = ($hint['detail'] ?? '') !== ''
                    ? trim($hint['detail'] . ' · ' . $sampleDetail)
                    : $sampleDetail;
            } elseif (($hint['kind'] ?? '') === 'block' && $varKey !== null) {
                $sampleDetail = ($samples[$varKey] ?? '') !== ''
                    ? '→ ' . TplSampleValues::truncate($samples[$varKey], 40)
                    : '→ сейчас пусто';
                $hint['detail'] = ($hint['detail'] ?? '') !== ''
                    ? trim($hint['detail'] . ' · ' . $sampleDetail)
                    : $sampleDetail;
            }

            $map[$key] = $hint;
        }

        return array_values($map);
    }

    /**
     * @param array<string, mixed> $hint
     */
    private static function variableKeyFromHint(array $hint): ?string
    {
        $insert = (string)($hint['insert'] ?? '');

        if (str_starts_with($insert, '{') && str_ends_with($insert, '}')) {
            $key = substr($insert, 1, -1);

            return str_ends_with($key, '|raw') ? substr($key, 0, -4) : $key;
        }

        if (preg_match('/^\[(?:not-)?([A-Za-z0-9_.]+)\]$/', $insert, $match)) {
            return $match[1];
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function syntaxSections(): array
    {
        return [
            [
                'id' => 'variables',
                'title' => 'Переменные',
                'description' => 'Подстановка значений из PHP-массива переменных.',
                'examples' => [
                    ['code' => '{series.title}', 'note' => 'Экранированный HTML-текст'],
                    ['code' => '{active_player_url|raw}', 'note' => 'Без экранирования — для URL, HTML, JSON'],
                ],
            ],
            [
                'id' => 'blocks',
                'title' => 'Условные блоки',
                'description' => 'Блок показывается, если переменная не пустая (не null, не false, не "", не пустой массив).',
                'examples' => [
                    ['code' => "[series.description]\n  <p>{series.description}</p>\n[/series.description]"],
                    ['code' => "[auth.logged_in]\n  ...\n[/auth.logged_in]"],
                    ['code' => "[not-series.description]\n  <p>Описание скоро появится</p>\n[/not-series.description]", 'note' => 'Инверсия — когда переменная пустая'],
                    ['code' => "[type-1]фильм[/type-1][type-4]мультсериал[/type-4]", 'note' => 'На странице сериала — текст по типу контента. На главной [type-N] — если есть секция с карточками этого типа'],
                    ['code' => "[loop content_type_sections]\n  {item.block_html|raw}\n[/loop]", 'note' => 'Главная: все непустые секции по типам'],
                    ['code' => "[type-5]{content_type_section_5.block_html|raw}[/type-5]", 'note' => 'Главная: только секция аниме'],
                ],
            ],
            [
                'id' => 'loops',
                'title' => 'Циклы',
                'description' => 'Повторение фрагмента для каждого элемента массива. Внутри доступны {item.*} и поля списка.',
                'examples' => [
                    ['code' => "[loop series_list]\n  <a href=\"{item.url}\">{item.title}</a>\n[/loop]"],
                    ['code' => "{item.kp_rating}", 'note' => 'Внутри цикла — поля текущего item'],
                ],
            ],
            [
                'id' => 'meta',
                'title' => 'SEO-блоки (только page-шаблоны)',
                'description' => 'Задаются в начале .tpl страницы. Не выводятся в HTML, попадают в <head>. Поддерживают те же теги, что и тело шаблона.',
                'examples' => [
                    ['code' => '[meta-title]{series.title} смотреть онлайн[/meta-title]'],
                    ['code' => '[meta-description]{series.short_description}[/meta-description]'],
                    ['code' => '[meta-canonical]{seo.canonical|raw}[/meta-canonical]'],
                    ['code' => '[meta-robots]noindex, nofollow[/meta-robots]'],
                ],
            ],
            [
                'id' => 'order',
                'title' => 'Порядок обработки',
                'description' => 'Сначала циклы → блоки [not-] → блоки [] → переменные { }.',
                'examples' => [],
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function contexts(): array
    {
        return [
            'global' => self::context(
                'Общие (layout, partials, все страницы)',
                'Доступны на каждой странице через commonVars() и layout.',
                self::globalVariables(),
                self::globalFlags(),
                self::globalLoops(),
            ),
            'layout' => self::context(
                'layout.tpl',
                'Обёртка сайта: head, header, content, footer.',
                array_merge(self::globalVariables(), [
                    self::docVar('content', 'HTML тела страницы'),
                    self::docVar('header', 'HTML шапки'),
                    self::docVar('footer', 'HTML подвала'),
                    self::docVar('auth_overlay', 'HTML модалки авторизации'),
                    self::docVar('speedbar_block', 'HTML хлебных крошек'),
                    self::docVar('meta.title', 'SEO заголовок'),
                    self::docVar('meta.description', 'SEO описание'),
                    self::docVar('meta.canonical', 'Canonical URL'),
                    self::docVar('meta.robots', 'Директива robots'),
                    self::docVar('meta.og', 'Open Graph теги (HTML)'),
                    self::docVar('meta.twitter', 'Twitter Card теги (HTML)'),
                    self::docVar('theme.stylesheets', 'Массив URL CSS'),
                    self::docVar('theme.scripts', 'Массив URL JS (site.js)'),
                    self::docVar('theme.js', 'URL site.js (совместимость)'),
                    self::docVar('theme.home_carousels_js', 'URL Swiper-бандла каруселей (ленивая подгрузка на главной)'),
                    self::docVar('theme.home_carousels_css', 'URL CSS каруселей'),
                    self::docVar('theme.logo', 'URL логотипа'),
                    self::docVar('THEME', 'Базовый путь темы: {THEME}/assets/logo.svg'),
                    self::docVar('seo_google_verification', 'Код верификации Google Search Console'),
                    self::docVar('seo_yandex_verification', 'Код верификации Яндекс Вебмастер'),
                    self::docVar('seo_counters_code', 'HTML/JS счётчиков и метрик'),
                    self::docVar('ad_vpaid_code', 'VPAID-реклама из настроек (VideoRoll и др.)'),
                    self::docVar('ad_header_code', 'Реклама под шапкой (настройки)'),
                    self::docVar('ad_catalog_grid_code', 'Реклама в сетке каталога (настройки)'),
                    self::docVar('ad_below_player_code', 'Реклама под плеером (настройки)'),
                ]),
                ['speedbar_block', 'seo_jsonld', 'meta.robots', 'meta.prev', 'meta.next', 'theme.stylesheets', 'theme.scripts', 'theme.js', 'seo_google_verification', 'seo_yandex_verification', 'seo_counters_code', 'ad_vpaid_code', 'ad_header_code', 'ad_catalog_grid_code', 'ad_below_player_code', 'site.has_favicon', 'site.has_background', 'has_notifications'],
                [
                    self::loop('theme.stylesheets', 'Подключённые CSS темы'),
                ],
            ),
            'home' => self::context(
                'home.tpl',
                'Главная страница (первая страница).',
                array_merge(self::globalVariables(), [
                    self::docVar('page.heading', 'Заголовок страницы'),
                    self::docVar('page.lead', 'Подзаголовок'),
                    self::docVar('is_home_first', 'true на первой главной (legacy)'),
                    self::docVar('home_seo_html', 'HTML SEO-текста главной'),
                    self::docVar('pagination_block', 'HTML пагинации'),
                    self::docVar('seo.title', 'Fallback SEO из контроллера'),
                    self::docVar('seo.description', 'Fallback SEO описание'),
                    self::docVar('seo.canonical', 'Fallback canonical'),
                    self::docVar('seo.prev', 'Ссылка prev'),
                    self::docVar('seo.next', 'Ссылка next'),
                    self::docVar('popular_cards_html', 'HTML карточек карусели «Популярное»'),
                    self::docVar('home_popular_title', 'Заголовок карусели «Популярное» (настройка каталога)'),
                    self::docVar('new_episodes_list', 'Сериалы с вышедшими сериями за N дней (из графика)'),
                    self::docVar('new_episodes_cards_html', 'HTML карточек карусели «Новые серии»'),
                    self::docVar('new_episodes_block', 'HTML блока «Новые серии»'),
                    self::docVar('has_schedule_calendar', 'Показывать календарь выхода на главной'),
                    self::docVar('schedule_calendar', 'Данные месяца: year, month, month_label, today, days'),
                    self::docVar('schedule_calendar_json', 'JSON данных календаря для JS'),
                    self::docVar('schedule_calendar_block', 'HTML блока календаря выхода серий'),
                    self::docVar('content_type_sections', 'Секции по типам контента (только непустые)'),
                    self::docVar('content_type_section_1', 'Секция type-1: фильм (title, cards_html, block_html, content_type)'),
                    self::docVar('content_type_section_2', 'Секция type-2: сериал'),
                    self::docVar('content_type_section_3', 'Секция type-3: мультфильм'),
                    self::docVar('content_type_section_4', 'Секция type-4: мультсериал'),
                    self::docVar('content_type_section_5', 'Секция type-5: аниме'),
                    self::docVar('content_type_section_6', 'Секция type-6: дорама'),
                    self::docVar('content_type_section_7', 'Секция type-7: тв-шоу'),
                ] + self::seriesCardItemVariables()),
                ['is_home_first', 'popular_list', 'popular_cards_html', 'home_popular_title', 'new_episodes_list', 'new_episodes_cards_html', 'new_episodes_block', 'has_schedule_calendar', 'schedule_calendar', 'schedule_calendar_json', 'schedule_calendar_block', 'home_sections', 'category_sections', 'custom_home_sections', 'content_type_sections', 'promo_collections', 'promo_studios', 'series_list', 'pagination_block', 'home_seo_html', 'page.lead', 'has_watch_history', 'type-1', 'type-2', 'type-3', 'type-4', 'type-5', 'type-6', 'type-7', 'item.badge_new_episode', 'item.badge_popular', 'item.season_badge', 'item.episode_badge', 'item.top_reaction_emoji'],
                [
                    self::loop('popular_list', 'Карусель «Популярное»'),
                    self::loop('new_episodes_list', 'Карусель «Новые серии»'),
                    self::loop('home_sections', 'Секции таксономий (жанр, страна, …)'),
                    self::loop('category_sections', 'Алиас home_sections'),
                    self::loop('custom_home_sections', 'Секции из админки «Секции главной»'),
                    self::loop('content_type_sections', 'Секции по типу контента: item.title, item.cards_html, item.content_type, item.type_index'),
                    self::loop('promo_collections', 'Промо-подборки'),
                    self::loop('promo_studios', 'Студии на главной'),
                    self::loop('series_list', 'Сетка сериалов'),
                ],
                ['home', 'global'],
            ),
            'catalog' => self::context(
                'catalog.tpl',
                'Каталог, пагинация главной и страницы таксономий.',
                array_merge(self::globalVariables(), [
                    self::docVar('page.heading', 'Заголовок страницы'),
                    self::docVar('page.lead', 'Подзаголовок / описание категории'),
                    self::docVar('is_home_first', 'false на страницах каталога (legacy)'),
                    self::docVar('is_taxonomy_page', 'true на страницах жанра/страны/года/персоны'),
                    self::docVar('taxonomy_type', 'Тип таксономии: genre|country|year|person|voice'),
                    self::docVar('taxonomy_slug', 'Slug таксономии'),
                    self::docVar('pagination_block', 'HTML пагинации'),
                    self::docVar('catalog_filters_block', 'HTML фильтров каталога'),
                    self::docVar('catalog_series_grid', 'HTML сетки сериалов каталога'),
                    self::docVar('catalog_total', 'Общее число результатов'),
                    self::docVar('browse_api_path', 'API path для AJAX-фильтрации'),
                    self::docVar('filter_fields', 'Массив фильтров: item.key, item.type, item.html'),
                    self::docVar('category_seo_html', 'HTML SEO-блока таксономии/каталога (внизу страницы)'),
                    self::docVar('ad_catalog_grid_code', 'Реклама в области сетки каталога'),
                    self::docVar('seo.title', 'Fallback SEO из контроллера'),
                    self::docVar('seo.description', 'Fallback SEO описание'),
                    self::docVar('seo.canonical', 'Fallback canonical'),
                    self::docVar('seo.prev', 'Ссылка prev'),
                    self::docVar('seo.next', 'Ссылка next'),
                    self::docVar('seo.robots', 'Robots meta'),
                ] + self::seriesCardItemVariables()),
                ['is_home_first', 'is_taxonomy_page', 'catalog_filters_block', 'catalog_series_grid', 'catalog_total', 'filter_fields', 'category_seo_html', 'pagination_block', 'page.lead', 'has_active', 'ad_catalog_grid_code', 'item.badge_new_episode', 'item.badge_popular', 'item.season_badge', 'item.episode_badge', 'item.top_reaction_emoji'],
                [
                    self::loop('series_list', 'Сетка сериалов (каталог)'),
                    self::loop('filter_fields', 'Поля фильтров каталога'),
                ],
                ['catalog', 'global'],
            ),
            'calendar' => self::context(
                'calendar/index.tpl',
                'Страница календаря выхода серий.',
                array_merge(self::globalVariables(), [
                    self::docVar('page.heading', 'H1 из настроек (calendar_heading)'),
                    self::docVar('page.lead', 'Подзаголовок из настроек (calendar_lead)'),
                    self::docVar('is_calendar_page', 'true на отдельной странице календаря'),
                    self::docVar('has_schedule_calendar', 'Показывать виджет календаря'),
                    self::docVar('schedule_calendar', 'Данные месяца: year, month, month_label, today, days, timeline'),
                    self::docVar('schedule_calendar_json', 'JSON данных календаря для JS'),
                    self::docVar('schedule_calendar_block', 'HTML виджета календаря'),
                    self::docVar('schedule_timeline', 'Дни месяца с сериями для списка'),
                    self::docVar('schedule_timeline_block', 'HTML списка серий по дням'),
                    self::docVar('schedule_episode_count', 'Число серий в выбранном месяце'),
                    self::docVar('calendar_seo_html', 'SEO-блок внизу страницы'),
                    self::docVar('seo.title', 'Meta title из настроек'),
                    self::docVar('seo.description', 'Meta description из настроек'),
                    self::docVar('seo.canonical', 'Canonical /kalendar/'),
                    self::docVar('seo.robots', 'Robots meta'),
                ]),
                ['is_calendar_page', 'has_schedule_calendar', 'schedule_calendar', 'schedule_timeline', 'schedule_episode_count', 'calendar_seo_html', 'page.lead'],
                [
                    self::loop('schedule_timeline', 'Дни: item.date, item.date_label, item.weekday, item.episodes'),
                ],
                ['calendar', 'global'],
            ),
            'series' => self::context(
                'series/show.tpl',
                'Страница сериала с плеером.',
                array_merge(self::globalVariables(), [
                    self::docVar('series.title', 'Название'),
                    self::docVar('series.slug', 'URL-slug'),
                    self::docVar('series.year', 'Год'),
                    self::docVar('series.description', 'Полное описание'),
                    self::docVar('series.short_description', 'Краткое описание'),
                    self::docVar('series.poster_url', 'URL постера'),
                    self::docVar('series.kp_rating', 'Рейтинг КП'),
                    self::docVar('series.imdb_rating', 'Рейтинг IMDb'),
                    self::docVar('series.content_type', 'Тип контента (slug: film, series, cartoon, …)'),
                    self::docVar('series.content_type_label', 'Название типа («Сериал», «Аниме», …)'),
                    self::docVar('series.genres_text', 'Жанры строкой'),
                    self::docVar('series.countries_text', 'Страны строкой'),
                    self::docVar('series.voices_text', 'Озвучки строкой'),
                    self::docVar('series.actors_text', 'Актёры строкой'),
                    self::docVar('series.directors_text', 'Режиссёры строкой'),
                    self::docVar('series.studios_text', 'Студии строкой'),
                    self::docVar('series.collections_text', 'Подборки строкой'),
                    self::docVar('series.year_label', 'Год (число)'),
                    self::docVar('series.year_url', 'URL страницы года'),
                    self::docVar('series.premiere_is_year_only', 'Дата выхода — только год'),
                    self::docVar('series.premiere_day_month_label', 'День и месяц премьеры'),
                    self::docVar('series.age_limit_label', 'Возрастной рейтинг (18+)'),
                    self::docVar('series.age_limit_tooltip', 'Подсказка возрастного рейтинга'),
                    self::docVar('series.broadcast_status_label', 'Статус эфира'),
                    self::docVar('series.episode_progress_label', 'Прогресс серий («1 сезон, 3 серия»); при наличии вышедших в графике — из графика'),
                    self::docVar('series.next_episode_reminder', 'Напоминание о ближайшей серии из графика («3 серия выйдет через 2 дня»)'),
                    self::docVar('series.next_episode_season', 'Номер сезона ближайшей запланированной серии'),
                    self::docVar('series.next_episode_number', 'Номер ближайшей запланированной серии'),
                    self::docVar('series.next_episode_days_until', 'Сколько дней до выхода'),
                    self::docVar('season-type-1', 'Текущий сезон: «5 сезон»'),
                    self::docVar('season-type-2', 'Список сезонов: «1, 2, 3 сезон»'),
                    self::docVar('season-type-3', 'Диапазон: «1-3 сезон»'),
                    self::docVar('season-type-4', 'Диапазон с текущим: «1-4, 5 сезон»'),
                    self::docVar('season-type-5', 'Диапазон «по»: «1 по 5 сезон»'),
                    self::docVar('episode-type-1', 'Текущая серия'),
                    self::docVar('episode-type-2', 'Список серий через запятую'),
                    self::docVar('episode-type-3', 'Диапазон серий'),
                    self::docVar('active_player_url', 'URL iframe плеера'),
                    self::docVar('reactions_widget', 'HTML виджета реакций'),
                    self::docVar('episodes_modal', 'HTML модалки серий'),
                    self::docVar('has_related', 'Есть блок рекомендаций'),
                    self::docVar('related_cards_html', 'HTML карточек «Рекомендуем посмотреть»'),
                    self::docVar('series_seo_html', 'HTML SEO-блока внизу карточки сериала'),
                    self::docVar('seo.canonical', 'Canonical URL'),
                    self::docVar('ad_vpaid_code', 'VPAID-реклама из настроек (VideoRoll и др.)'),
                    self::docVar('ad_below_player_code', 'Реклама под плеером'),
                ]),
                ['series.description', 'series.year', 'series.year_url', 'series.studios', 'series.collections', 'series.voices', 'has_player', 'has_players', 'has_reactions', 'has_schedule', 'has_comments', 'has_comments_vote', 'has_series_vote', 'has_notifications', 'has_watchlists', 'has_favourites', 'has_coming_soon', 'has_telegram', 'has_related', 'episodes_modal', 'reactions_widget', 'related_cards_html', 'series_seo_html', 'ad_vpaid_code', 'ad_below_player_code', 'type-1', 'type-2', 'type-3', 'type-4', 'type-5', 'type-6', 'type-7'],
                [
                    self::loop('series.genres', 'Жанры сериала (name, slug, url, is_last)'),
                    self::loop('series.voices', 'Озвучки (name, slug, url, is_last)'),
                    self::loop('series.countries', 'Страны (name, slug, url, is_last)'),
                    self::loop('series.actors', 'Актёры (name, slug, url, photo_url, is_last)'),
                    self::loop('series.directors', 'Режиссёры (name, slug, url, photo_url, is_last)'),
                    self::loop('series.studios', 'Студии (title, slug, url, logo_url, is_last)'),
                    self::loop('series.collections', 'Подборки (title, slug, url, is_last)'),
                    self::loop('schedule', 'Расписание выхода серий'),
                    self::loop('notify_voices', 'Озвучки для уведомлений'),
                    self::loop('players', 'Вкладки плееров (label, url, index, is_first)'),
                ],
                ['series', 'global'],
            ),
            'search' => self::context(
                'search.tpl',
                'Страница поиска.',
                array_merge(self::globalVariables(), [
                    self::docVar('query', 'Поисковый запрос'),
                    self::docVar('pagination_block', 'HTML пагинации'),
                    self::docVar('seo.title', 'Fallback SEO'),
                    self::docVar('seo.canonical', 'Canonical'),
                ]),
                ['query', 'series_list', 'taxonomy_groups', 'popular_searches', 'pagination_block', 'has_results'],
                [
                    self::loop('series_list', 'Результаты поиска'),
                    self::loop('taxonomy_groups', 'Группы таксономии'),
                    self::loop('popular_searches', 'Популярные запросы'),
                ],
                ['search', 'global'],
            ),
            'collections' => self::context(
                'collections/*.tpl',
                'Подборки сериалов.',
                array_merge(self::globalVariables(), [
                    self::docVar('collection.title', 'Название подборки'),
                    self::docVar('collection.slug', 'Slug подборки'),
                    self::docVar('collection.source_updated_at', 'Дата обновления'),
                    self::docVar('page.heading', 'Заголовок (index)'),
                    self::docVar('pagination_block', 'HTML пагинации'),
                    self::docVar('seo.canonical', 'Canonical'),
                ]),
                ['collection.source_updated_at', 'collection_items', 'collections_list', 'pagination_block'],
                [
                    self::loop('collections_list', 'Список подборок (index)'),
                    self::loop('collection_items', 'Сериалы в подборке (show)'),
                ],
                ['collections', 'global'],
            ),
            'studios' => self::context(
                'studios/*.tpl',
                'Студии и их каталог сериалов.',
                array_merge(self::globalVariables(), [
                    self::docVar('page.heading', 'Заголовок страницы списка (index)'),
                    self::docVar('studios_total', 'Число студий на странице (index)'),
                    self::docVar('studios_total_word', 'Слово «студия/студии/студий» (index)'),
                    self::docVar('studio.title', 'Название студии (show)'),
                    self::docVar('studio.slug', 'Slug студии (show)'),
                    self::docVar('studio.description', 'Описание студии (show)'),
                    self::docVar('studio.logo_url', 'URL логотипа (show)'),
                    self::docVar('studio_total', 'Число сериалов студии (show)'),
                    self::docVar('studio_total_word', 'Слово «сериал/сериала/сериалов» (show)'),
                    self::docVar('studio_seo_html', 'HTML SEO-блока (show)'),
                    self::docVar('pagination_block', 'HTML пагинации (show)'),
                    self::docVar('seo.title', 'SEO заголовок'),
                    self::docVar('seo.description', 'SEO описание'),
                    self::docVar('seo.canonical', 'Canonical URL'),
                    self::docVar('seo.robots', 'Директива robots (show)'),
                    self::docVar('seo.prev', 'Ссылка prev (show)'),
                    self::docVar('seo.next', 'Ссылка next (show)'),
                ] + self::studioCardItemVariables()),
                [
                    'studios_total',
                    'studio.description',
                    'studio_seo_html',
                    'studio_has_items',
                    'studios_card_show_title',
                    'studios_card_show_description',
                    'pagination_block',
                ],
                [
                    self::loop('studios_list', 'Карточки студий (index): item.slug, item.title, item.description, item.logo_url, item.items_count, item.items_count_word'),
                    self::loop('studio_items', 'Сериалы студии (show): item.slug, item.title, item.poster_url, item.url, item.kp_rating, item.imdb_rating, item.season_badge, item.episode_badge'),
                ],
                ['studios', 'global'],
            ),
            'profile' => self::context(
                'profile/show.tpl',
                'Личный кабинет (только авторизованные).',
                array_merge(self::globalVariables(), [
                    self::docVar('profile.name', 'Имя пользователя'),
                    self::docVar('profile.email', 'Email'),
                    self::docVar('profile.initial', 'Инициал для аватара'),
                    self::docVar('profile.registered_at', 'Дата регистрации'),
                    self::docVar('profile_stats.lists', 'Число списков'),
                    self::docVar('profile_stats.items', 'Число сериалов в списках'),
                    self::docVar('profile_stats.comments', 'Число комментариев'),
                    self::docVar('profile_stats.reviews', 'Число рецензий'),
                    self::docVar('profile_stats.reviews_pending', 'Рецензий на модерации'),
                    self::docVar('flash_success', 'Flash-сообщение'),
                ]),
                ['flash_success', 'has_notifications', 'has_profile_reviews', 'has_reviews_pending'],
                [
                    self::loop('watchlists', 'Списки «Буду смотреть»'),
                    self::loop('profile_comments', 'Комментарии пользователя'),
                    self::loop('profile_reviews', 'Рецензии пользователя (включая pending)'),
                ],
                ['profile', 'global'],
            ),
            'reactions' => self::context(
                'partials/reactions_widget.tpl',
                'Виджет реакций под плеером.',
                [
                    self::docVar('series.slug', 'Slug сериала для AJAX'),
                    self::docVar('reactions.badge', 'Бейдж «ОЦЕНИТЕ»'),
                    self::docVar('reactions.title', 'Заголовок виджета'),
                    self::docVar('reactions.total_label', '«N голосов»'),
                ],
                [],
                [self::loop('reactions.items', 'Кнопки реакций: item.emoji, item.label, item.count_label, item.percent, item.is_selected')],
                ['reactions', 'series', 'global'],
            ),
            'partials' => self::context(
                'partials/*.tpl',
                'Частичные шаблоны — переменные зависят от места подключения.',
                array_merge(self::globalVariables(), self::seriesCardItemVariables()),
                ['item.badge_new_episode', 'item.badge_popular', 'item.season_badge', 'item.episode_badge', 'item.top_reaction_emoji', 'has_notifications', 'has_active', 'has_comments_vote', 'has_schedule_calendar'],
                [
                    self::loop('series_list', 'Сетка сериалов'),
                ],
                ['global'],
            ),
            'errors' => self::context(
                'errors/*.tpl',
                'Страницы ошибок.',
                array_merge(self::globalVariables(), [
                    self::docVar('error_code', 'HTTP-код'),
                    self::docVar('error_title', 'Заголовок'),
                    self::docVar('error_message', 'Текст ошибки'),
                ]),
                ['has_notifications', 'has_favicon', 'has_logo'],
                [],
                ['errors', 'global'],
            ),
        ];
    }

    /**
     * @param list<array<string, string>> $variables
     * @param list<string> $flags
     * @param list<array<string, string>> $loops
     * @param list<string> $hintContexts
     * @return array<string, mixed>
     */
    private static function context(
        string $title,
        string $description,
        array $variables,
        array $flags,
        array $loops,
        array $hintContexts = [],
    ): array {
        return [
            'title' => $title,
            'description' => $description,
            'variables' => $variables,
            'flags' => array_map(fn (string $f) => ['name' => $f, 'syntax' => "[{$f}]...[/{$f}]"], $flags),
            'not_flags' => array_map(fn (string $f) => ['name' => $f, 'syntax' => "[not-{$f}]...[/not-{$f}]"], $flags),
            'loops' => $loops,
            'hint_contexts' => $hintContexts,
        ];
    }

    /**
     * @return list<array{name: string, description: string, example?: string}>
     */
    private static function globalVariables(): array
    {
        return [
            self::docVar('site.name', 'Название сайта'),
            self::docVar('site.tagline', 'Слоган'),
            self::docVar('site.footer_text', 'Текст в подвале'),
            self::docVar('site.year', 'Текущий год'),
            self::docVar('site.favicon', 'URL favicon из настроек брендинга'),
            self::docVar('site.has_favicon', 'Загружен favicon (блок [site.has_favicon])'),
            self::docVar('csrf_token', 'CSRF-токен'),
            self::docVar('THEME', 'Базовый URL-путь активной темы от корня сайта'),
            self::docVar('search_query', 'Текущий поисковый запрос'),
            self::docVar('auth.logged_in', 'Пользователь авторизован'),
            self::docVar('auth.is_admin', 'Пользователь — администратор'),
            self::docVar('auth.name', 'Имя пользователя'),
            self::docVar('auth.email', 'Email пользователя'),
            self::docVar('admin_url', 'Базовый URL админ-панели'),
            self::docVar('auth_panel', 'login | register | forgot | reset'),
            self::docVar('mega_category_slug', 'Устарело: slug категории для mega-menu (legacy)'),
            self::docVar('ad_vpaid_code', 'VPAID-реклама из настроек (VideoRoll и др.)'),
            self::docVar('ad_header_code', 'Реклама под шапкой'),
            self::docVar('ad_catalog_grid_code', 'Реклама в сетке каталога'),
            self::docVar('ad_below_player_code', 'Реклама под плеером'),
        ];
    }

    /**
     * @return list<string>
     */
    private static function globalFlags(): array
    {
        return [
            'auth.logged_in',
            'auth.is_admin',
            'ad_vpaid_code',
            'ad_header_code',
            'ad_catalog_grid_code',
            'ad_below_player_code',
            'has_notifications',
            'site.has_favicon',
            'site.has_background',
        ];
    }

    /**
     * @return list<array<string, string>>
     */
    private static function globalLoops(): array
    {
        return [
            self::loop('nav_desktop_items', 'Пункты меню (desktop): item.title, item.url, item.has_mega, item.mega_buttons, item.mega_sections'),
            self::loop('nav_mobile_items', 'Пункты меню (mobile): item.title, item.url'),
            self::loop('categories_list', 'Устаревший алиас nav_desktop_items'),
            self::loop('speedbar.items', 'Хлебные крошки: item.title, item.url, item.is_last'),
        ];
    }

    /**
     * @return list<array<string, string>>
     */
    private static function hints(): array
    {
        $hints = [];

        $add = static function (string $insert, string $label, string $kind, string $detail = '', array $contexts = []) use (&$hints): void {
            $hints[] = [
                'insert' => $insert,
                'label' => $label,
                'kind' => $kind,
                'detail' => $detail,
                'contexts' => $contexts,
            ];
        };

        foreach (self::metaTags() as $tag => $desc) {
            $add("[{$tag}]", "[{$tag}]", 'meta', $desc, []);
        }

        foreach (self::contexts() as $ctxKey => $ctx) {
            $contexts = $ctx['hint_contexts'] ?? [$ctxKey];

            foreach ($ctx['variables'] as $v) {
                $name = $v['name'];
                $add('{' . $name . '}', '{' . $name . '}', 'variable', $v['description'], $contexts);
                $add('{' . $name . '|raw}', '{' . $name . '|raw}', 'variable', $v['description'] . ' (без экранирования)', $contexts);
            }

            foreach ($ctx['flags'] ?? [] as $f) {
                $name = $f['name'];
                $detail = self::flagDetail($name);
                $add("[{$name}]", "[{$name}]", 'block', $detail, $contexts);
                $add("[not-{$name}]", "[not-{$name}]", 'block', 'Инверсия: ' . mb_strtolower($detail), $contexts);
            }

            foreach ($ctx['loops'] ?? [] as $l) {
                $list = preg_replace('/^loop\s+/', '', $l['syntax']);
                $add("[loop {$list}]", "[loop {$list}]", 'loop', $l['description'], $contexts);
            }
        }

        return $hints;
    }

    /**
     * @return array<string, string>
     */
    private static function metaTags(): array
    {
        return [
            'meta-title' => 'SEO <title>',
            'meta-description' => 'meta description',
            'meta-canonical' => 'canonical URL',
            'meta-robots' => 'robots',
            'meta-image' => 'og:image / twitter:image',
            'meta-prev' => 'link rel=prev',
            'meta-next' => 'link rel=next',
            'meta-og' => 'Произвольные OG-теги (HTML)',
            'meta-twitter' => 'Произвольные Twitter-теги (HTML)',
        ];
    }

    private static function flagDetail(string $name): string
    {
        return match ($name) {
            'has_notifications' => 'Уведомления включены в настройках',
            'has_watch_history' => 'История просмотров включена',
            'has_schedule_calendar' => 'Календарь выхода серий на главной или отдельной странице',
            'is_calendar_page' => 'Отдельная страница календаря /kalendar/',
            'has_active' => 'Есть активные фильтры каталога',
            'has_player', 'has_players' => 'Есть хотя бы один плеер',
            'has_reactions' => 'Виджет реакций включён',
            'has_schedule' => 'Есть расписание серий',
            'has_comments' => 'Комментарии включены',
            'has_comments_vote' => 'Голосование за комментарии включено',
            'has_series_vote' => 'Оценка сериала включена',
            'has_watchlists' => 'Списки «Буду смотреть» включены',
            'has_favourites' => 'Избранное включено',
            'has_coming_soon' => 'Контент скоро выйдет (anticipation)',
            'has_telegram' => 'Указана ссылка Telegram',
            'has_related' => 'Есть блок рекомендаций',
            'has_results' => 'Есть результаты поиска',
            'has_favicon', 'site.has_favicon' => 'Загружен favicon',
            'site.has_background' => 'Задан фоновый фон сайта',
            'has_logo' => 'Задан логотип',
            'auth.logged_in' => 'Пользователь авторизован',
            'auth.is_admin' => 'Пользователь — администратор',
            'ad_vpaid_code' => 'Есть VPAID-реклама',
            'ad_header_code' => 'Есть реклама под шапкой',
            'ad_catalog_grid_code' => 'Есть реклама в сетке каталога',
            'ad_below_player_code' => 'Есть реклама под плеером',
            default => 'Показать если значение не пусто',
        };
    }

    /**
     * @return array<int, array{name: string, description: string}>
     */
    private static function seriesCardItemVariables(): array
    {
        return [
            self::docVar('item.slug', 'Slug сериала'),
            self::docVar('item.category', 'Устарело: slug категории (используйте item.url)'),
            self::docVar('item.title', 'Название'),
            self::docVar('item.poster_url', 'URL постера'),
            self::docVar('item.year', 'Год'),
            self::docVar('item.short_description', 'Краткое описание (для карточек)'),
            self::docVar('item.kp_rating', 'Рейтинг КП'),
            self::docVar('item.imdb_rating', 'Рейтинг IMDb'),
            self::docVar('item.season_badge', 'Бейдж сезона, напр. S5'),
            self::docVar('item.episode_badge', 'Бейдж серии, напр. E12'),
                    self::docVar('item.episode_progress_label', 'Текст «5 сезон, 12 серия» (из графика, если есть вышедшие)'),
            self::docVar('item.top_reaction_emoji', 'Топ-эмоджи реакции пользователей'),
            self::docVar('item.badge_new_episode', 'Флаг бейджа «Новая серия»'),
            self::docVar('item.badge_new_episode_label', 'Текст бейджа новой серии'),
            self::docVar('item.badge_popular', 'Флаг бейджа «Популярно»'),
            self::docVar('item.badge_popular_label', 'Текст бейджа популярности'),
        ];
    }

    /**
     * @return array<int, array{name: string, description: string}>
     */
    private static function studioCardItemVariables(): array
    {
        return [
            self::docVar('item.slug', 'Slug студии'),
            self::docVar('item.title', 'Название студии'),
            self::docVar('item.description', 'Описание студии'),
            self::docVar('item.logo_url', 'URL логотипа'),
            self::docVar('item.items_count', 'Число сериалов в студии'),
            self::docVar('item.items_count_word', 'Слово «сериал/сериала/сериалов»'),
        ];
    }

    /**
     * @return array{name: string, description: string}
     */
    private static function docVar(string $name, string $description): array
    {
        return ['name' => $name, 'description' => $description];
    }

    /**
     * @return array{syntax: string, description: string}
     */
    private static function loop(string $listKey, string $description): array
    {
        return [
            'syntax' => "loop {$listKey}",
            'description' => $description,
        ];
    }

    /**
     * @return list<string>
     */
    public static function contextsForTemplatePath(string $path): array
    {
        $path = str_replace('\\', '/', ltrim($path, '/'));

        $map = [
            'layout.tpl' => ['layout', 'global'],
            'home.tpl' => ['home', 'global'],
            'catalog.tpl' => ['catalog', 'global'],
            'partials/home_new_episodes.tpl' => ['home', 'global'],
            'partials/home_schedule_calendar.tpl' => ['home', 'calendar', 'global'],
            'partials/schedule_calendar_timeline.tpl' => ['calendar', 'global'],
            'calendar/index.tpl' => ['calendar', 'global'],
            'search.tpl' => ['search', 'global'],
            'series/show.tpl' => ['series', 'global'],
            'collections/index.tpl' => ['collections', 'global'],
            'collections/show.tpl' => ['collections', 'global'],
            'studios/index.tpl' => ['studios', 'global'],
            'studios/show.tpl' => ['studios', 'global'],
            'profile/show.tpl' => ['profile', 'global'],
            'partials/reactions_widget.tpl' => ['reactions', 'series', 'global'],
            'partials/series_cards.tpl' => ['partials', 'home', 'catalog', 'global'],
            'partials/catalog_series_grid.tpl' => ['partials', 'catalog', 'global'],
            'partials/series_card_overlays.tpl' => ['partials', 'home', 'catalog', 'global'],
        ];

        if (isset($map[$path])) {
            return $map[$path];
        }

        if (str_starts_with($path, 'errors/')) {
            return ['errors', 'global'];
        }

        if (str_starts_with($path, 'partials/')) {
            return ['partials', 'global'];
        }

        if (str_starts_with($path, 'series/')) {
            return ['series', 'global'];
        }

        if (str_starts_with($path, 'collections/')) {
            return ['collections', 'global'];
        }

        if (str_starts_with($path, 'studios/')) {
            return ['studios', 'global'];
        }

        if (str_starts_with($path, 'calendar/')) {
            return ['calendar', 'global'];
        }

        return ['global'];
    }
}
