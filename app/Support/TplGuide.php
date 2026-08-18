<?php

namespace App\Support;

/**
 * Полноценная справка по TPL для верстальщиков (стиль DLE-DOC).
 * Источник правды по тегам — TplDocumentation; здесь — гайды и сборка UI/офлайн HTML.
 */
class TplGuide
{
    public const VERSION = '1.0';

    /**
     * @return array<string, mixed>
     */
    public static function payload(): array
    {
        $articles = array_merge(self::guideArticles(), self::referenceArticles());

        return [
            'version' => self::VERSION,
            'title' => 'TPL-DOC',
            'subtitle' => 'Справка по шаблонам и интеграции HTML',
            'nav' => self::nav($articles),
            'articles' => $articles,
            'search_index' => self::buildSearchIndex($articles),
        ];
    }

    public static function downloadHtml(): string
    {
        $payload = self::payload();
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);
        if ($json === false) {
            $json = '{}';
        }

        $css = self::offlineCss();
        $js = self::offlineJs();

        return <<<HTML
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>TPL-DOC — справка по шаблонам</title>
<style>{$css}</style>
</head>
<body>
<div class="app">
  <aside class="sidebar">
    <div class="brand">
      <strong>TPL-DOC</strong>
      <span>v{$payload['version']}</span>
    </div>
    <input type="search" id="q" class="search" placeholder="Поиск по тегам и статьям…" autocomplete="off">
    <nav id="nav" class="nav"></nav>
  </aside>
  <main class="main">
    <div id="search-results" class="search-results" hidden></div>
    <article id="content" class="content"></article>
  </main>
</div>
<script type="application/json" id="docs-data">{$json}</script>
<script>{$js}</script>
</body>
</html>
HTML;
    }

    /**
     * @param list<array<string, mixed>> $articles
     * @return list<array{group: string, items: list<array{id: string, title: string}>}>
     */
    private static function nav(array $articles): array
    {
        $groups = [];
        foreach ($articles as $article) {
            $group = (string)($article['group'] ?? 'Прочее');
            $groups[$group][] = [
                'id' => $article['id'],
                'title' => $article['title'],
            ];
        }

        $order = [
            'Старт',
            'Синтаксис',
            'Файлы шаблона',
            'Справочник тегов',
            'Интеграция',
        ];

        $nav = [];
        foreach ($order as $name) {
            if (!isset($groups[$name])) {
                continue;
            }
            $nav[] = ['group' => $name, 'items' => $groups[$name]];
            unset($groups[$name]);
        }
        foreach ($groups as $name => $items) {
            $nav[] = ['group' => $name, 'items' => $items];
        }

        return $nav;
    }

    /**
     * @param list<array<string, mixed>> $articles
     * @return list<array{id: string, title: string, group: string, text: string, anchors: list<array{id: string, title: string}>}>
     */
    private static function buildSearchIndex(array $articles): array
    {
        $index = [];
        foreach ($articles as $article) {
            $parts = [$article['title'], $article['summary'] ?? ''];
            $anchors = [];
            foreach ($article['blocks'] ?? [] as $block) {
                $type = $block['type'] ?? '';
                if ($type === 'h2' || $type === 'h3') {
                    $anchors[] = [
                        'id' => (string)($block['id'] ?? ''),
                        'title' => (string)($block['text'] ?? ''),
                    ];
                    $parts[] = $block['text'] ?? '';
                } elseif ($type === 'p' || $type === 'note') {
                    $parts[] = $block['text'] ?? '';
                } elseif ($type === 'code') {
                    $parts[] = $block['code'] ?? '';
                    $parts[] = $block['caption'] ?? '';
                } elseif ($type === 'table') {
                    foreach ($block['rows'] ?? [] as $row) {
                        $parts[] = implode(' ', $row);
                    }
                    foreach ($block['headers'] ?? [] as $h) {
                        $parts[] = $h;
                    }
                } elseif ($type === 'ul' || $type === 'ol') {
                    foreach ($block['items'] ?? [] as $item) {
                        $parts[] = $item;
                    }
                } elseif ($type === 'tags') {
                    foreach ($block['items'] ?? [] as $tag) {
                        $parts[] = $tag['name'] ?? '';
                        $parts[] = $tag['description'] ?? '';
                        $parts[] = $tag['syntax'] ?? '';
                    }
                }
            }
            $index[] = [
                'id' => $article['id'],
                'title' => $article['title'],
                'group' => $article['group'] ?? '',
                'text' => mb_strtolower(trim(preg_replace('/\s+/u', ' ', implode(' ', $parts)) ?? '')),
                'anchors' => $anchors,
            ];
        }

        return $index;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function guideArticles(): array
    {
        return [
            self::article('intro', 'Старт', 'Что такое TPL-DOC', 'Краткая справка для верстальщика: как натянуть любой HTML на движок.', [
                self::h2('Зачем эта справка', 'why'),
                self::p('TPL — шаблонный движок сайта в стиле DLE: теги `{переменная}`, условные блоки `[флаг]…[/флаг]` и циклы `[loop список]…[/loop]`. Если вы умеете верстать HTML/CSS, этого достаточно, чтобы перенести макет.'),
                self::p('В админке есть редактор файлов темы («Шаблоны») и эта документация. Можно скачать офлайн HTML и работать без доступа к панели.'),
                self::h2('С чего начать', 'start'),
                self::ol([
                    'Скопируйте тему `default` или `russia-default` в новую папку в `resources/tpl/`.',
                    'Откройте `layout.tpl` — это «каркас» всего сайта (как `main.tpl` в DLE).',
                    'Подключите свои CSS/JS в `assets/` и замените разметку шапки/подвала в `partials/`.',
                    'Перенесите страницы: `home.tpl`, `catalog.tpl`, `series/show.tpl` и остальные.',
                    'Активируйте тему в настройках сайта.',
                ]),
                self::note('Важно: в TPL нет тега `{include}`. Частичные шаблоны (`partials/*.tpl`) подключает PHP и отдаёт в layout как готовый HTML (`{header|raw}`, `{footer|raw}` и т.д.).'),
                self::h2('Карта разделов', 'map'),
                self::ul([
                    '«Синтаксис» — переменные, блоки, циклы, SEO-meta, `|raw`.',
                    '«Файлы шаблона» — роль каждого `.tpl` и минимальные примеры.',
                    '«Справочник тегов» — полный список переменных и флагов по контекстам.',
                    '«Интеграция» — пошаговый перенос HTML и типичные ошибки.',
                ]),
            ]),

            self::article('porting', 'Старт', 'Как натянуть HTML на TPL', 'Пошаговый алгоритм переноса готовой вёрстки на движок.', [
                self::h2('Общий алгоритм', 'algo'),
                self::ol([
                    'Возьмите главный HTML-файл макета и перенесите `<head>` + оболочку `<body>` в `layout.tpl`.',
                    'Вместо статичного контента страницы поставьте `{content|raw}`.',
                    'Шапку вынесите в `partials/header.tpl`, подвал — в `partials/footer.tpl` (их HTML уже подставляется в layout).',
                    'Пути к CSS/JS замените на `{THEME}/assets/...` или используйте готовые циклы `[loop theme.stylesheets]`.',
                    'Страницы каталога/сериала/поиска перенесите в соответствующие `.tpl`, заменив демо-данные на теги.',
                    'Карточки сериалов обычно живут в `partials/series_cards.tpl` / `series_card.tpl` — один шаблон на все списки.',
                ]),
                self::h2('Минимальный layout.tpl', 'min-layout'),
                self::code(<<<'TPL'
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <title>{meta.title}</title>
  <meta name="description" content="{meta.description}">
  <link rel="canonical" href="{meta.canonical}">
  [loop theme.stylesheets]
  <link rel="stylesheet" href="{item|raw}">
  [/loop]
  <meta name="csrf-token" content="{csrf_token|raw}">
</head>
<body>
  {header|raw}
  [speedbar_block]<div class="speedbar">{speedbar_block|raw}</div>[/speedbar_block]
  {content|raw}
  {footer|raw}
  {auth_overlay|raw}
  [loop theme.scripts]
  <script src="{item|raw}"></script>
  [/loop]
</body>
</html>
TPL, 'Каркас сайта'),
                self::h2('Что класть куда', 'where'),
                self::table(
                    ['Файл', 'Что переносить из HTML'],
                    [
                        ['layout.tpl', 'Общая оболочка: head, обёртки, подключение CSS/JS'],
                        ['partials/header.tpl', 'Шапка, меню, поиск, кнопки входа'],
                        ['partials/footer.tpl', 'Подвал, копирайт, ссылки'],
                        ['home.tpl', 'Разметка главной (без шапки/подвала)'],
                        ['catalog.tpl', 'Сетка каталога + фильтры'],
                        ['series/show.tpl', 'Страница тайтла: описание, плеер, комментарии'],
                        ['assets/*', 'CSS, JS, шрифты, картинки темы'],
                    ]
                ),
                self::h2('Статику → теги', 'replace'),
                self::table(
                    ['В вёрстке', 'В TPL'],
                    [
                        ['Название сайта', '{site.name}'],
                        ['Логотип', '{theme.logo|raw} или {THEME}/assets/logo.svg'],
                        ['Ссылка на сериал', '{item.url} внутри [loop series_list]'],
                        ['Постер', '{item.poster_url|raw}'],
                        ['Блок «если авторизован»', '[auth.logged_in]…[/auth.logged_in]'],
                        ['Готовый HTML-блок от движка', '{pagination_block|raw}, {catalog_series_grid|raw}'],
                    ]
                ),
                self::note('Для URL, iframe src, JSON и уже собранного HTML всегда используйте `|raw`. Обычный текст — без `|raw` (экранируется).'),
            ]),

            self::article('syntax-vars', 'Синтаксис', 'Переменные {…}', 'Подстановка значений и фильтр |raw.', [
                self::h2('Описание', 'desc'),
                self::p('Переменные подставляются из массива данных страницы. Точка — путь во вложенный массив: `{series.title}`, `{auth.name}`.'),
                self::h2('Экранирование', 'escape'),
                self::code("{series.title}\n{active_player_url|raw}", 'Текст экранируется; |raw — как есть'),
                self::ul([
                    'Без `|raw` — безопасный HTML-текст (названия, описания).',
                    'С `|raw` — URL, HTML-фрагменты (`*_html`, `*_block`, реклама), JSON-LD, CSRF.',
                ]),
                self::h2('Примеры', 'examples'),
                self::code(<<<'TPL'
<h1>{series.title}</h1>
<img src="{series.poster_url|raw}" alt="{series.title}">
<iframe src="{active_player_url|raw}" allowfullscreen></iframe>
TPL),
            ]),

            self::article('syntax-blocks', 'Синтаксис', 'Условные блоки […]', 'Показ фрагмента, если значение непустое; инверсия [not-…].', [
                self::h2('Описание', 'desc'),
                self::p('Блок `[имя]…[/имя]` выводится, если переменная «истинна»: не null, не false, не пустая строка и не пустой массив. `[not-имя]` — наоборот.'),
                self::code(<<<'TPL'
[series.description]
  <div class="desc">{series.description}</div>
[/series.description]

[not-series.description]
  <p>Описание скоро появится</p>
[/not-series.description]

[auth.logged_in]
  Привет, {auth.name}!
[/auth.logged_in]
TPL),
                self::h2('Специальные флаги type-N', 'type-n'),
                self::p('На странице сериала `[type-1]`…`[type-7]` соответствуют типу контента (фильм, сериал, мультфильм, мультсериал, аниме, дорама, тв-шоу). На главной те же теги показывают секцию с карточками этого типа.'),
                self::code("[type-5]Аниме[/type-5]\n[type-2]Сериал[/type-2]"),
            ]),

            self::article('syntax-loops', 'Синтаксис', 'Циклы [loop]', 'Повтор разметки для каждого элемента списка.', [
                self::h2('Описание', 'desc'),
                self::p('Синтаксис: `[loop имя_списка]…[/loop]`. Внутри доступны поля текущего элемента как `{item.*}`. Циклы можно вкладывать.'),
                self::code(<<<'TPL'
[loop series_list]
  <a class="card" href="{item.url}">
    <img src="{item.poster_url|raw}" alt="{item.title}">
    <span>{item.title}</span>
    [item.kp_rating]<b>{item.kp_rating}</b>[/item.kp_rating]
  </a>
[/loop]
TPL),
                self::h2('Частые списки', 'lists'),
                self::ul([
                    '`series_list` — сетка сериалов (главная, каталог, поиск).',
                    '`nav_desktop_items` / `nav_mobile_items` — меню.',
                    '`players` — вкладки плееров на странице сериала.',
                    '`series.genres`, `series.actors` — таксономии тайтла.',
                    '`theme.stylesheets` — CSS темы в layout.',
                ]),
            ]),

            self::article('syntax-meta', 'Синтаксис', 'SEO-блоки [meta-*]', 'Задаются в начале page-шаблона и попадают в <head>, не в HTML тела.', [
                self::h2('Описание', 'desc'),
                self::p('В начале `home.tpl`, `catalog.tpl`, `series/show.tpl` и других page-шаблонов можно задать SEO. Движок вырезает эти блоки из тела и подставляет в layout.'),
                self::code(<<<'TPL'
[meta-title]{series.title} смотреть онлайн[/meta-title]
[meta-description]{series.short_description}[/meta-description]
[meta-canonical]{seo.canonical|raw}[/meta-canonical]
[meta-robots]index, follow[/meta-robots]
[meta-image]{series.poster_url|raw}[/meta-image]

<!-- дальше обычная вёрстка страницы -->
<article>…</article>
TPL),
                self::table(
                    ['Тег', 'Назначение'],
                    [
                        ['[meta-title]', '<title>'],
                        ['[meta-description]', 'meta description'],
                        ['[meta-canonical]', 'canonical URL'],
                        ['[meta-robots]', 'robots'],
                        ['[meta-image]', 'og:image / twitter:image'],
                        ['[meta-prev] / [meta-next]', 'пагинация'],
                        ['[meta-og] / [meta-twitter]', 'произвольный HTML OG/Twitter'],
                    ]
                ),
            ]),

            self::article('syntax-order', 'Синтаксис', 'Порядок обработки', 'Как движок разбирает шаблон.', [
                self::ol([
                    'Сначала циклы `[loop …]`.',
                    'Затем инверсии `[not-…]`.',
                    'Затем обычные блоки `[…]`.',
                    'В конце переменные `{…}` / `{…|raw}`.',
                ]),
                self::note('Из-за порядка можно писать условия внутри циклов и переменные внутри условий — это штатный сценарий.'),
            ]),

            self::article('file-layout', 'Файлы шаблона', 'layout.tpl', 'Основа темы: head, шапка, контент, подвал.', [
                self::h2('Описание', 'desc'),
                self::p('Аналог `main.tpl` в DLE. Здесь вся HTML-оболочка сайта. Обязательные «дыры» для движка: `{content|raw}`, обычно также `{header|raw}` и `{footer|raw}`.'),
                self::h2('Ключевые теги', 'tags'),
                self::table(
                    ['Тег', 'Что выводит'],
                    [
                        ['{content|raw}', 'HTML текущей страницы (home/catalog/series/…)'],
                        ['{header|raw}', 'Результат partials/header.tpl'],
                        ['{footer|raw}', 'partials/footer.tpl'],
                        ['{auth_overlay|raw}', 'Модалка входа/регистрации'],
                        ['{speedbar_block|raw}', 'Хлебные крошки'],
                        ['{meta.title}', 'SEO title'],
                        ['{THEME}', 'Базовый путь темы, напр. /theme-assets/default'],
                        ['[loop theme.stylesheets]', 'Подключение CSS темы'],
                    ]
                ),
                self::h2('Обязательный минимум', 'must'),
                self::ul([
                    'Файл `layout.tpl` должен существовать — иначе тема невалидна.',
                    'В `<head>` нужны `{meta.title}`, description, canonical (или ваши [meta-*] с страниц).',
                    'CSRF: `<meta name="csrf-token" content="{csrf_token|raw}">` — для AJAX форм.',
                ]),
            ]),

            self::article('file-home', 'Файлы шаблона', 'home.tpl', 'Главная страница.', [
                self::p('Контент первой страницы сайта. Карусели, секции по типам контента, календарь выхода, SEO-текст.'),
                self::h2('Типичные блоки', 'blocks'),
                self::ul([
                    '`{popular_cards_html|raw}` / `[loop popular_list]` — популярное.',
                    '`{new_episodes_block|raw}` — новые серии.',
                    '`{schedule_calendar_block|raw}` — календарь.',
                    '`[loop content_type_sections]{item.block_html|raw}[/loop]` — секции по типам.',
                    '`{home_seo_html|raw}` — SEO-текст внизу.',
                ]),
                self::code(<<<'TPL'
[meta-title]{seo.title}[/meta-title]
[meta-description]{seo.description}[/meta-description]

<section class="popular">
  <h2>{home_popular_title}</h2>
  {popular_cards_html|raw}
</section>

[loop content_type_sections]
  {item.block_html|raw}
[/loop]

[home_seo_html]
<div class="seo">{home_seo_html|raw}</div>
[/home_seo_html]
TPL),
            ]),

            self::article('file-catalog', 'Файлы шаблона', 'catalog.tpl', 'Каталог, таксономии и пагинация главной.', [
                self::p('Используется для `/catalog`, страниц жанров/стран/годов/персон и продолжения главной со 2-й страницы.'),
                self::code(<<<'TPL'
<h1>{page.heading}</h1>
[page.lead]<p>{page.lead}</p>[/page.lead]

{catalog_filters_block|raw}

[ad_catalog_grid_code]
<div class="ad">{ad_catalog_grid_code|raw}</div>
[/ad_catalog_grid_code]

{catalog_series_grid|raw}
{pagination_block|raw}

[category_seo_html]
<div class="seo">{category_seo_html|raw}</div>
[/category_seo_html]
TPL),
                self::note('Фильтры и сетка часто уже собраны PHP в `*_block` / `*_grid`. Можно вместо этого крутить `[loop series_list]` и `[loop filter_fields]` вручную.'),
            ]),

            self::article('file-series', 'Файлы шаблона', 'series/show.tpl', 'Страница сериала/фильма с плеером.', [
                self::p('Полная карточка тайтла: метаданные, таксономии, плеер, реакции, комментарии, похожие.'),
                self::h2('Плеер', 'player'),
                self::code(<<<'TPL'
[has_player]
  <div class="tabs">
    [loop players]
      <button data-url="{item.url|raw}"[item.is_first] class="active"[/item.is_first]>{item.label}</button>
    [/loop]
  </div>
  <iframe src="{active_player_url|raw}" allowfullscreen></iframe>
[/has_player]
[not-has_player]
  <p>Плеер скоро появится</p>
[/not-has_player]
TPL),
                self::h2('Метаданные', 'meta-data'),
                self::code(<<<'TPL'
<h1>{series.title} {series.year_full}</h1>
[series.year]<a href="{series.year_url|raw}">{series.year}</a>[/series.year]
[series.year_full]{series.year_full}[/series.year_full]
<div>{series.genres_text}</div>
[loop series.genres]
  <a href="{item.url}">{item.name}</a>[not-item.is_last], [/not-item.is_last]
[/loop]
TPL),
                self::p('Также доступны `season-type-1…5`, `episode-type-1…3`, виджет `{reactions_widget|raw}`, `{related_cards_html|raw}`, `{episodes_modal|raw}`. Подробности — в справочнике «series».'),
            ]),

            self::article('file-partials', 'Файлы шаблона', 'partials/*.tpl', 'Частичные шаблоны: шапка, карточки, фильтры.', [
                self::p('Partials не подключаются тегом include. Контроллер рендерит их и передаёт HTML в родительский шаблон.'),
                self::table(
                    ['Файл', 'Роль'],
                    [
                        ['header.tpl / footer.tpl', 'Шапка и подвал → {header|raw}, {footer|raw}'],
                        ['series_cards.tpl / series_card.tpl', 'Карточки в сетках и каруселях'],
                        ['catalog_filters.tpl', 'Панель фильтров каталога'],
                        ['catalog_series_grid.tpl', 'Сетка результатов каталога'],
                        ['pagination.tpl', 'Пагинация'],
                        ['speedbar.tpl', 'Хлебные крошки'],
                        ['reactions_widget.tpl', 'Реакции под плеером'],
                        ['auth_overlay.tpl', 'Модалка авторизации'],
                    ]
                ),
                self::note('Правите карточку один раз в partial — она обновится на главной, в каталоге и в похожих (если они используют этот partial).'),
            ]),

            self::article('file-other', 'Файлы шаблона', 'Остальные страницы', 'Поиск, подборки, студии, профиль, ошибки.', [
                self::table(
                    ['Файл', 'Назначение'],
                    [
                        ['search.tpl', 'Результаты поиска'],
                        ['collections/index.tpl, show.tpl', 'Список и страница подборки'],
                        ['studios/index.tpl, show.tpl', 'Студии'],
                        ['profile/show.tpl', 'Личный кабинет'],
                        ['favourites/index.tpl', 'Избранное'],
                        ['coming_soon/index.tpl', 'Скоро'],
                        ['calendar/index.tpl', 'Календарь выхода серий'],
                        ['errors/*.tpl', '403/404/419/500/503'],
                        ['maintenance.tpl', 'Режим обслуживания'],
                    ]
                ),
            ]),

            self::article('themes-assets', 'Интеграция', 'Темы и assets', 'Где лежат файлы и как подключать статику.', [
                self::h2('Структура темы', 'structure'),
                self::code("resources/tpl/имя-темы/\n  layout.tpl          ← обязательно\n  home.tpl\n  catalog.tpl\n  series/show.tpl\n  partials/…\n  assets/\n    site.css\n    site.js\n    logo.svg\n    …"),
                self::ul([
                    'Активная тема задаётся в настройках сайта.',
                    '`{THEME}` — URL-префикс файлов темы.',
                    'Редактирование — раздел «Шаблоны» в админке.',
                ]),
                self::h2('CSS и JS', 'css-js'),
                self::p('Предпочтительно класть стили/скрипты в `assets/` темы — движок сам соберёт `theme.stylesheets` и `theme.scripts`. Либо подключайте вручную:'),
                self::code('<link rel="stylesheet" href="{THEME}/assets/my.css">\n<script src="{THEME}/assets/my.js"></script>'),
            ]),

            self::article('pitfalls', 'Интеграция', 'Типичные ошибки', 'Что чаще всего ломает вёрстку при переносе.', [
                self::ul([
                    'Забыли `|raw` у URL/iframe/HTML — получаете `&amp;` и битые ссылки.',
                    'Ищете `{include}` — его нет; правьте partials и layout-переменные.',
                    'Кладёте шапку внутрь `home.tpl` — дубль с `{header|raw}` в layout.',
                    'SEO пишете обычным HTML в body вместо `[meta-title]` — title не попадёт в head.',
                    'Ломаете `data-` атрибуты и классы, на которые завязан `site.js` (плеер, фильтры, модалки) — смотрите разметку default-темы.',
                    'Нет `layout.tpl` в новой теме — тема не активируется.',
                ]),
            ]),

            self::article('cheatsheet', 'Интеграция', 'Шпаргалка', 'Самые нужные теги на одной странице.', [
                self::table(
                    ['Задача', 'Тег'],
                    [
                        ['Название сайта', '{site.name}'],
                        ['Контент страницы', '{content|raw}'],
                        ['Меню', '[loop nav_desktop_items]…[/loop]'],
                        ['Поиск (значение)', '{search_query}'],
                        ['Авторизован?', '[auth.logged_in]…[/auth.logged_in]'],
                        ['Карточка в списке', '[loop series_list] {item.title} {item.url} [/loop]'],
                        ['Плеер', '{active_player_url|raw}'],
                        ['Пагинация', '{pagination_block|raw}'],
                        ['Тема / CSS', '{THEME}/assets/…'],
                        ['SEO title страницы', '[meta-title]…[/meta-title]'],
                    ]
                ),
            ]),
        ];
    }

    /**
     * Автогенерация справочника из TplDocumentation.
     *
     * @return list<array<string, mixed>>
     */
    private static function referenceArticles(): array
    {
        $docs = TplDocumentation::payload();
        $articles = [];

        $articles[] = self::article('ref-index', 'Справочник тегов', 'Обзор справочника', 'Все контексты переменных и флагов.', [
            self::p('Ниже — полный перечень по контекстам страниц. Контекст зависит от файла: в `series/show.tpl` доступны теги series + global, в `home.tpl` — home + global.'),
            self::p('В редакторе шаблонов подсказки фильтруются по открытому файлу; здесь показан полный список.'),
            self::ul(array_map(
                static fn (string $key, array $ctx): string => "**{$ctx['title']}** (`{$key}`) — {$ctx['description']}",
                array_keys($docs['contexts']),
                array_values($docs['contexts'])
            )),
        ]);

        foreach ($docs['contexts'] as $key => $ctx) {
            $blocks = [
                self::p((string)$ctx['description']),
            ];

            if (($ctx['variables'] ?? []) !== []) {
                $blocks[] = self::h2('Переменные', 'vars');
                $blocks[] = [
                    'type' => 'tags',
                    'kind' => 'variable',
                    'items' => array_map(static function (array $v): array {
                        return [
                            'name' => '{' . $v['name'] . '}',
                            'syntax' => '{' . $v['name'] . '}',
                            'raw' => '{' . $v['name'] . '|raw}',
                            'description' => $v['description'],
                        ];
                    }, $ctx['variables']),
                ];
            }

            if (($ctx['flags'] ?? []) !== []) {
                $blocks[] = self::h2('Условия', 'flags');
                $blocks[] = [
                    'type' => 'tags',
                    'kind' => 'block',
                    'items' => array_map(static function (array $f): array {
                        return [
                            'name' => '[' . $f['name'] . ']',
                            'syntax' => $f['syntax'],
                            'description' => 'Показать блок, если значение непустое. Инверсия: [not-' . $f['name'] . ']…[/not-' . $f['name'] . ']',
                        ];
                    }, $ctx['flags']),
                ];
            }

            if (($ctx['loops'] ?? []) !== []) {
                $blocks[] = self::h2('Циклы', 'loops');
                $blocks[] = [
                    'type' => 'tags',
                    'kind' => 'loop',
                    'items' => array_map(static function (array $l): array {
                        return [
                            'name' => '[' . $l['syntax'] . ']',
                            'syntax' => '[' . $l['syntax'] . ']…[/loop]',
                            'description' => $l['description'],
                        ];
                    }, $ctx['loops']),
                ];
            }

            $articles[] = self::article(
                'ref-' . $key,
                'Справочник тегов',
                (string)$ctx['title'],
                (string)$ctx['description'],
                $blocks
            );
        }

        return $articles;
    }

    /**
     * @param list<array<string, mixed>> $blocks
     * @return array<string, mixed>
     */
    private static function article(string $id, string $group, string $title, string $summary, array $blocks): array
    {
        return [
            'id' => $id,
            'group' => $group,
            'title' => $title,
            'summary' => $summary,
            'blocks' => $blocks,
        ];
    }

    /** @return array<string, mixed> */
    private static function h2(string $text, string $id): array
    {
        return ['type' => 'h2', 'id' => $id, 'text' => $text];
    }

    /** @return array<string, mixed> */
    private static function p(string $text): array
    {
        return ['type' => 'p', 'text' => $text];
    }

    /** @return array<string, mixed> */
    private static function note(string $text): array
    {
        return ['type' => 'note', 'text' => $text];
    }

    /** @return array<string, mixed> */
    private static function code(string $code, string $caption = ''): array
    {
        return ['type' => 'code', 'code' => $code, 'caption' => $caption];
    }

    /**
     * @param list<string> $items
     * @return array<string, mixed>
     */
    private static function ul(array $items): array
    {
        return ['type' => 'ul', 'items' => $items];
    }

    /**
     * @param list<string> $items
     * @return array<string, mixed>
     */
    private static function ol(array $items): array
    {
        return ['type' => 'ol', 'items' => $items];
    }

    /**
     * @param list<string> $headers
     * @param list<list<string>> $rows
     * @return array<string, mixed>
     */
    private static function table(array $headers, array $rows): array
    {
        return ['type' => 'table', 'headers' => $headers, 'rows' => $rows];
    }

    private static function offlineCss(): string
    {
        return <<<'CSS'
:root{--bg:#0f1419;--panel:#171d25;--line:#2a3441;--text:#e7eef7;--muted:#9aa8b8;--accent:#3b82f6;--code:#0b1020;--note:#1d2a1f;--note-border:#3d6b45}
*{box-sizing:border-box}
body{margin:0;font:15px/1.55 system-ui,-apple-system,Segoe UI,Roboto,sans-serif;background:var(--bg);color:var(--text)}
.app{display:grid;grid-template-columns:300px 1fr;min-height:100vh}
.sidebar{border-right:1px solid var(--line);background:var(--panel);padding:16px;position:sticky;top:0;height:100vh;overflow:auto}
.brand{display:flex;justify-content:space-between;align-items:baseline;margin-bottom:12px}
.brand span{color:var(--muted);font-size:12px}
.search{width:100%;padding:10px 12px;border-radius:8px;border:1px solid var(--line);background:#0f1419;color:var(--text);margin-bottom:14px}
.nav-group{margin:0 0 14px}
.nav-group h3{margin:0 0 6px;font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:var(--muted)}
.nav a{display:block;padding:7px 10px;border-radius:6px;color:var(--text);text-decoration:none}
.nav a:hover,.nav a.active{background:#243041}
.main{padding:28px 36px 64px;max-width:920px}
.content h1{margin:0 0 8px;font-size:28px}
.summary{color:var(--muted);margin:0 0 24px}
.content h2{margin:28px 0 10px;font-size:20px;scroll-margin-top:16px}
.content p{margin:0 0 12px}
.content ul,.content ol{margin:0 0 14px;padding-left:22px}
.content li{margin:0 0 6px}
.note{margin:14px 0;padding:12px 14px;border-left:3px solid var(--note-border);background:var(--note);border-radius:0 8px 8px 0}
.pre{background:var(--code);border:1px solid var(--line);border-radius:10px;padding:12px 14px;overflow:auto;margin:0 0 14px}
.pre code{font:13px/1.45 ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;white-space:pre}
.caption{color:var(--muted);font-size:12px;margin:0 0 6px}
table{width:100%;border-collapse:collapse;margin:0 0 16px;font-size:14px}
th,td{border:1px solid var(--line);padding:8px 10px;vertical-align:top;text-align:left}
th{background:#1b2430}
code.inline{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;background:#1b2430;padding:1px 5px;border-radius:4px}
.tag{border:1px solid var(--line);border-radius:10px;padding:12px;margin:0 0 10px;background:#121821}
.tag-name{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;color:#93c5fd;font-weight:600}
.tag-desc{color:var(--muted);margin-top:4px;font-size:13px}
.search-results{margin-bottom:20px}
.search-hit{display:block;padding:10px 12px;border:1px solid var(--line);border-radius:8px;margin:0 0 8px;color:var(--text);text-decoration:none;background:#121821}
.search-hit:hover{border-color:var(--accent)}
.search-hit small{display:block;color:var(--muted);margin-top:2px}
@media(max-width:900px){.app{grid-template-columns:1fr}.sidebar{position:relative;height:auto}}
CSS;
    }

    private static function offlineJs(): string
    {
        return <<<'JS'
(function(){
  const data=JSON.parse(document.getElementById('docs-data').textContent);
  const navEl=document.getElementById('nav');
  const content=document.getElementById('content');
  const results=document.getElementById('search-results');
  const q=document.getElementById('q');
  const articles=Object.fromEntries(data.articles.map(a=>[a.id,a]));

  function esc(s){return String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}
  function inline(s){return esc(s).replace(/`([^`]+)`/g,'<code class="inline">$1</code>').replace(/\*\*([^*]+)\*\*/g,'<strong>$1</strong>');}

  function renderNav(active){
    navEl.innerHTML=data.nav.map(g=>`<div class="nav-group"><h3>${esc(g.group)}</h3>${g.items.map(i=>`<a href="#${esc(i.id)}" class="${i.id===active?'active':''}" data-id="${esc(i.id)}">${esc(i.title)}</a>`).join('')}</div>`).join('');
  }

  function renderBlock(b){
    if(b.type==='h2')return `<h2 id="${esc(b.id)}">${esc(b.text)}</h2>`;
    if(b.type==='h3')return `<h3 id="${esc(b.id)}">${esc(b.text)}</h3>`;
    if(b.type==='p')return `<p>${inline(b.text)}</p>`;
    if(b.type==='note')return `<div class="note">${inline(b.text)}</div>`;
    if(b.type==='code')return `${b.caption?`<div class="caption">${esc(b.caption)}</div>`:''}<pre class="pre"><code>${esc(b.code)}</code></pre>`;
    if(b.type==='ul')return `<ul>${b.items.map(i=>`<li>${inline(i)}</li>`).join('')}</ul>`;
    if(b.type==='ol')return `<ol>${b.items.map(i=>`<li>${inline(i)}</li>`).join('')}</ol>`;
    if(b.type==='table')return `<table><thead><tr>${b.headers.map(h=>`<th>${esc(h)}</th>`).join('')}</tr></thead><tbody>${b.rows.map(r=>`<tr>${r.map(c=>`<td>${inline(c)}</td>`).join('')}</tr>`).join('')}</tbody></table>`;
    if(b.type==='tags')return b.items.map(t=>`<div class="tag"><div class="tag-name">${esc(t.name)}</div>${t.syntax&&t.syntax!==t.name?`<div><code class="inline">${esc(t.syntax)}</code>${t.raw?` · <code class="inline">${esc(t.raw)}</code>`:''}</div>`:''}<div class="tag-desc">${esc(t.description||'')}</div></div>`).join('');
    return '';
  }

  function showArticle(id){
    const a=articles[id]||data.articles[0];
    if(!a)return;
    location.hash=a.id;
    renderNav(a.id);
    results.hidden=true;content.hidden=false;
    content.innerHTML=`<h1>${esc(a.title)}</h1><p class="summary">${esc(a.summary||'')}</p>${(a.blocks||[]).map(renderBlock).join('')}`;
    window.scrollTo(0,0);
  }

  function doSearch(query){
    const qq=query.trim().toLowerCase();
    if(!qq){results.hidden=true;content.hidden=false;return;}
    const hits=data.search_index.filter(i=>i.text.includes(qq)||i.title.toLowerCase().includes(qq)).slice(0,40);
    content.hidden=true;results.hidden=false;
    results.innerHTML=hits.length?hits.map(h=>`<a class="search-hit" href="#${esc(h.id)}"><strong>${esc(h.title)}</strong><small>${esc(h.group)}</small></a>`).join(''):`<p class="summary">Ничего не найдено</p>`;
  }

  navEl.addEventListener('click',e=>{const a=e.target.closest('a[data-id]');if(!a)return;e.preventDefault();showArticle(a.dataset.id);});
  results.addEventListener('click',e=>{const a=e.target.closest('a.search-hit');if(!a)return;e.preventDefault();q.value='';showArticle(a.getAttribute('href').slice(1));});
  q.addEventListener('input',()=>doSearch(q.value));
  window.addEventListener('hashchange',()=>showArticle((location.hash||'#').slice(1)||data.articles[0].id));
  showArticle((location.hash||'#').slice(1)||data.articles[0].id);
})();
JS;
    }
}
