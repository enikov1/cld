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
                ],
            ],
            [
                'id' => 'loops',
                'title' => 'Циклы',
                'description' => 'Повторение фрагмента для каждого элемента массива. Внутри доступны {item.*} и поля списка.',
                'examples' => [
                    ['code' => "[loop series_list]\n  <a href=\"/{item.category}/{item.slug}.html\">{item.title}</a>\n[/loop]"],
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
                    self::docVar('theme.js', 'URL site.js'),
                    self::docVar('theme.logo', 'URL логотипа'),
                    self::docVar('THEME', 'Базовый путь темы: {THEME}/assets/logo.svg'),
                    self::docVar('seo_google_verification', 'Код верификации Google Search Console'),
                    self::docVar('seo_yandex_verification', 'Код верификации Яндекс Вебмастер'),
                    self::docVar('seo_counters_code', 'HTML/JS счётчиков и метрик'),
                ]),
                ['speedbar_block', 'seo_jsonld', 'meta.robots', 'meta.prev', 'meta.next', 'theme.stylesheets', 'theme.js', 'seo_google_verification', 'seo_yandex_verification', 'seo_counters_code'],
                [
                    self::loop('theme.stylesheets', 'Подключённые CSS темы'),
                ],
            ),
            'home' => self::context(
                'home.tpl',
                'Главная и каталог категории.',
                array_merge(self::globalVariables(), [
                    self::docVar('page.heading', 'Заголовок страницы'),
                    self::docVar('page.lead', 'Подзаголовок / описание категории'),
                    self::docVar('is_home_first', 'true на первой главной'),
                    self::docVar('home_seo_html', 'HTML SEO-текста главной'),
                    self::docVar('pagination_block', 'HTML пагинации'),
                    self::docVar('catalog_filters_block', 'HTML фильтров каталога'),
                    self::docVar('filter_fields', 'Массив фильтров: item.key, item.type, item.html'),
                    self::docVar('category_seo_html', 'HTML SEO-блока категории (внизу страницы)'),
                    self::docVar('seo.title', 'Fallback SEO из контроллера'),
                    self::docVar('seo.description', 'Fallback SEO описание'),
                    self::docVar('seo.canonical', 'Fallback canonical'),
                    self::docVar('seo.prev', 'Ссылка prev'),
                    self::docVar('seo.next', 'Ссылка next'),
                    self::docVar('popular_cards_html', 'HTML карточек карусели «Популярное»'),
                ] + self::seriesCardItemVariables()),
                ['is_home_first', 'popular_list', 'popular_cards_html', 'home_sections', 'category_sections', 'promo_collections', 'series_list', 'pagination_block', 'home_seo_html', 'catalog_filters_block', 'filter_fields', 'category_seo_html', 'page.lead', 'item.badge_new_episode', 'item.badge_popular', 'item.season_badge', 'item.episode_badge', 'item.top_reaction_emoji'],
                [
                    self::loop('popular_list', 'Карусель «Популярное»'),
                    self::loop('home_sections', 'Блоки конструктора главной'),
                    self::loop('category_sections', 'Алиас home_sections'),
                    self::loop('promo_collections', 'Промо-подборки'),
                    self::loop('series_list', 'Сетка сериалов (каталог)'),
                ],
                ['home', 'global'],
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
                    self::docVar('series.genres_text', 'Жанры строкой'),
                    self::docVar('series.countries_text', 'Страны строкой'),
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
                    self::docVar('series.episode_progress_label', 'Прогресс серий'),
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
                    self::docVar('seo.canonical', 'Canonical URL'),
                ]),
                ['series.description', 'series.year', 'series.year_url', 'series.studios', 'series.collections', 'has_player', 'has_reactions', 'has_schedule', 'episodes_modal', 'reactions_widget', 'has_related', 'related_cards_html'],
                [
                    self::loop('series.genres', 'Жанры сериала (name, slug, url, is_last)'),
                    self::loop('series.countries', 'Страны (name, slug, url, is_last)'),
                    self::loop('series.actors', 'Актёры (name, slug, url, is_last)'),
                    self::loop('series.directors', 'Режиссёры (name, slug, url, is_last)'),
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
                ['query', 'series_list', 'taxonomy_groups', 'popular_searches', 'pagination_block'],
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
                    self::loop('studio_items', 'Сериалы студии (show): item.slug, item.category, item.title, item.poster_url, item.url, item.kp_rating, item.imdb_rating, item.season_badge, item.episode_badge'),
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
                    self::docVar('flash_success', 'Flash-сообщение'),
                ]),
                ['flash_success'],
                [
                    self::loop('watchlists', 'Списки «Буду смотреть»'),
                    self::loop('profile_comments', 'Комментарии пользователя'),
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
                ['item.badge_new_episode', 'item.badge_popular', 'item.season_badge', 'item.episode_badge', 'item.top_reaction_emoji'],
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
                [],
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
            self::docVar('mega_category_slug', 'Slug категории для mega-menu'),
        ];
    }

    /**
     * @return list<string>
     */
    private static function globalFlags(): array
    {
        return ['auth.logged_in', 'auth.is_admin'];
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
                $add("[{$name}]", "[{$name}]", 'block', 'Показать если не пусто', $contexts);
                $add("[not-{$name}]", "[not-{$name}]", 'block', 'Показать если пусто', $contexts);
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

    /**
     * @return array<int, array{name: string, description: string}>
     */
    private static function seriesCardItemVariables(): array
    {
        return [
            self::docVar('item.slug', 'Slug сериала'),
            self::docVar('item.category', 'Slug категории'),
            self::docVar('item.title', 'Название'),
            self::docVar('item.poster_url', 'URL постера'),
            self::docVar('item.year', 'Год'),
            self::docVar('item.kp_rating', 'Рейтинг КП'),
            self::docVar('item.imdb_rating', 'Рейтинг IMDb'),
            self::docVar('item.season_badge', 'Бейдж сезона, напр. S5'),
            self::docVar('item.episode_badge', 'Бейдж серии, напр. E12'),
            self::docVar('item.episode_progress_label', 'Текст «5 сезон, 12 серия»'),
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
            'search.tpl' => ['search', 'global'],
            'series/show.tpl' => ['series', 'global'],
            'collections/index.tpl' => ['collections', 'global'],
            'collections/show.tpl' => ['collections', 'global'],
            'studios/index.tpl' => ['studios', 'global'],
            'studios/show.tpl' => ['studios', 'global'],
            'profile/show.tpl' => ['profile', 'global'],
            'partials/reactions_widget.tpl' => ['reactions', 'series', 'global'],
            'partials/series_cards.tpl' => ['partials', 'home', 'global'],
            'partials/catalog_series_grid.tpl' => ['partials', 'home', 'global'],
            'partials/series_card_overlays.tpl' => ['partials', 'home', 'global'],
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

        return ['global'];
    }
}
