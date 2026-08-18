<?php

namespace App\Http\Controllers;

use App\Models\Collection;
use App\Models\Country;
use App\Models\Genre;
use App\Models\Person;
use App\Models\Series;
use App\Models\Studio;
use App\Models\User;
use App\Models\Voice;
use App\Models\Year;
use App\Support\SiteConfig;
use Illuminate\Http\Request;

class AdminGlobalSearchController extends Controller
{
    private const LIMIT_PER_GROUP = 8;

    /** @var list<array{key: string, path: string, title: string, subtitle: string, keywords: string}> */
    private const PAGES = [
        ['key' => 'dashboard', 'path' => '/', 'title' => 'Обзор', 'subtitle' => 'Статистика и быстрые действия', 'keywords' => 'dashboard главная панель'],
        ['key' => 'series', 'path' => '/series', 'title' => 'Сериалы', 'subtitle' => 'Карточки контента и плеер', 'keywords' => 'фильмы контент каталог'],
        ['key' => 'collections', 'path' => '/collections', 'title' => 'Подборки', 'subtitle' => 'Тематические списки сериалов', 'keywords' => 'коллекции'],
        ['key' => 'studios', 'path' => '/studios', 'title' => 'Студии', 'subtitle' => 'Студии и их каталоги', 'keywords' => 'каналы'],
        ['key' => 'taxonomy', 'path' => '/taxonomy', 'title' => 'Справочники', 'subtitle' => 'Жанры, страны, актёры, годы, озвучки', 'keywords' => 'жанры страны актёры годы озвучки taxonomy'],
        ['key' => 'nav-menu', 'path' => '/nav-menu', 'title' => 'Меню', 'subtitle' => 'Навигация и mega-menu', 'keywords' => 'навигация'],
        ['key' => 'home-sections', 'path' => '/home-sections', 'title' => 'Секции главной', 'subtitle' => 'Блоки на главной странице', 'keywords' => 'home секции'],
        ['key' => 'reactions', 'path' => '/reactions', 'title' => 'Реакции', 'subtitle' => 'Оценки под плеером и статистика', 'keywords' => 'эмодзи статистика голоса'],
        ['key' => 'templates', 'path' => '/templates', 'title' => 'Шаблоны', 'subtitle' => 'Редактор .tpl', 'keywords' => 'tpl тема код'],
        ['key' => 'tpl-docs', 'path' => '/tpl-docs', 'title' => 'TPL-DOC', 'subtitle' => 'Справка по шаблонам для верстальщика', 'keywords' => 'документация tpl теги layout верстка'],
        ['key' => 'comments', 'path' => '/comments', 'title' => 'Комментарии', 'subtitle' => 'Модерация отзывов', 'keywords' => 'модерация'],
        ['key' => 'player-reports', 'path' => '/player-reports', 'title' => 'Жалобы на плеер', 'subtitle' => 'Проблемы со страницы сериала', 'keywords' => 'багрепорты'],
        ['key' => 'users', 'path' => '/users', 'title' => 'Пользователи', 'subtitle' => 'Аккаунты и роли', 'keywords' => 'юзеры аккаунты'],
        ['key' => 'search-stats', 'path' => '/search-stats', 'title' => 'Поиск (статистика)', 'subtitle' => 'Поисковые запросы сайта', 'keywords' => 'статистика запросов'],
        ['key' => 'views-stats', 'path' => '/views-stats', 'title' => 'Просмотры', 'subtitle' => 'Динамика и топ сериалов', 'keywords' => 'views статистика'],
        ['key' => 'redirects', 'path' => '/redirects', 'title' => 'Редиректы', 'subtitle' => 'Перенаправления URL', 'keywords' => 'redirect 301'],
        ['key' => 'settings', 'path' => '/settings', 'title' => 'Настройки', 'subtitle' => 'Брендинг, SEO, каталог, интеграции', 'keywords' => 'конфиг опции'],
        ['key' => 'cron-runs', 'path' => '/cron-runs', 'title' => 'История задач', 'subtitle' => 'Лог синхронизаций', 'keywords' => 'cron задачи'],
        ['key' => 'backup', 'path' => '/backup', 'title' => 'Бэкапы', 'subtitle' => 'Резервное копирование', 'keywords' => 'backup резерв'],
        ['key' => 'sync', 'path' => '/sync', 'title' => 'KinoPoisk', 'subtitle' => 'Импорт через kp:sync', 'keywords' => 'кинопоиск sync'],
        ['key' => 'alloha-sync', 'path' => '/alloha-sync', 'title' => 'Alloha', 'subtitle' => 'Автообновление плееров', 'keywords' => 'аллоха'],
        ['key' => 'rutube-sync', 'path' => '/rutube-sync', 'title' => 'Rutube', 'subtitle' => 'Трейлеры', 'keywords' => 'рутуб'],
    ];

    /** @var list<array{key: string, label: string, section: string, keywords: string}> */
    private const EXTRA_SETTINGS = [
        ['key' => 'site_name', 'label' => 'Название сайта', 'section' => 'branding', 'keywords' => 'бренд имя'],
        ['key' => 'site_tagline', 'label' => 'Слоган', 'section' => 'branding', 'keywords' => 'подзаголовок'],
        ['key' => 'footer_text', 'label' => 'Текст в футере', 'section' => 'branding', 'keywords' => 'подвал'],
        ['key' => 'site_logo', 'label' => 'Логотип', 'section' => 'branding', 'keywords' => 'logo'],
        ['key' => 'site_background', 'label' => 'Фон сайта', 'section' => 'branding', 'keywords' => 'background картинка'],
        ['key' => 'site_header_offset_top', 'label' => 'Отступ шапки от верха', 'section' => 'branding', 'keywords' => 'header'],
        ['key' => 'site_background_color', 'label' => 'Цвет фона', 'section' => 'branding', 'keywords' => 'цвет'],
        ['key' => 'site_background_disable_mobile', 'label' => 'Отключить фон на мобильных', 'section' => 'branding', 'keywords' => 'mobile'],
        ['key' => 'active_theme', 'label' => 'Активная тема', 'section' => 'theme', 'keywords' => 'шаблон tpl'],
        ['key' => 'home_heading', 'label' => 'Заголовок каталога на главной', 'section' => 'home', 'keywords' => 'home заголовок'],
        ['key' => 'home_lead', 'label' => 'Подзаголовок главной', 'section' => 'home', 'keywords' => 'home lead'],
        ['key' => 'home_seo_html', 'label' => 'SEO-блок главной (HTML)', 'section' => 'home', 'keywords' => 'seo главная'],
        ['key' => 'robots_txt', 'label' => 'robots.txt', 'section' => 'seo', 'keywords' => 'robots краулер'],
        ['key' => 'sitemap', 'label' => 'sitemap.xml', 'section' => 'seo', 'keywords' => 'карта сайта'],
        ['key' => 'admin_path', 'label' => 'URL-префикс админки', 'section' => 'admin', 'keywords' => 'admin path путь'],
        ['key' => 'comments_auto_approve', 'label' => 'Автоодобрение комментариев', 'section' => 'moderation', 'keywords' => 'модерация'],
        ['key' => 'reviews_auto_approve', 'label' => 'Автоодобрение рецензий', 'section' => 'moderation', 'keywords' => 'модерация рецензии'],
        ['key' => 'kinopoisk_api_key', 'label' => 'KinoPoisk API-ключ', 'section' => 'integrations', 'keywords' => 'кинопоиск api'],
        ['key' => 'alloha_api_token', 'label' => 'Alloha API-токен', 'section' => 'integrations', 'keywords' => 'аллоха api'],
        ['key' => 'tmdb_api_key', 'label' => 'TMDB API-ключ', 'section' => 'integrations', 'keywords' => 'tmdb api'],
        ['key' => 'tmdb_auto_sync', 'label' => 'Автообновление TMDB', 'section' => 'integrations', 'keywords' => 'tmdb sync'],
    ];

    /** @var array<string, string> */
    private const GROUP_SECTION = [
        'auth' => 'auth',
        'comments' => 'comments',
        'ratings' => 'ratings',
        'engagement' => 'engagement',
        'catalog' => 'catalog',
        'optimization' => 'optimization',
        'advertising' => 'advertising',
        'maintenance' => 'maintenance',
        'general' => 'general',
        'integrations' => 'integrations',
        'players' => 'integrations',
        'seo' => 'seo',
    ];

    /** @var array<string, string> */
    private const SECTION_LABELS = [
        'branding' => 'Брендинг',
        'theme' => 'Шаблон сайта',
        'home' => 'Главная страница',
        'seo' => 'SEO',
        'auth' => 'Авторизация',
        'comments' => 'Комментарии',
        'ratings' => 'Рейтинги',
        'engagement' => 'Списки и уведомления',
        'catalog' => 'Каталог',
        'optimization' => 'Оптимизация',
        'advertising' => 'Реклама',
        'maintenance' => 'Обслуживание',
        'general' => 'Общие',
        'admin' => 'Админ-панель',
        'moderation' => 'Модерация',
        'integrations' => 'Интеграции',
    ];

    /** @var array<string, string> */
    private const TAXONOMY_LABELS = [
        'genres' => 'Жанр',
        'countries' => 'Страна',
        'people' => 'Актёр / персона',
        'years' => 'Год',
        'voices' => 'Озвучка',
    ];

    public function search(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 1) {
            return response()->json(['groups' => [], 'q' => $q]);
        }

        $limit = min(20, max(3, (int) $request->query('limit', self::LIMIT_PER_GROUP)));
        $actor = \App\Support\AdminAccess::resolveActor($request);
        if ($actor === null) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $allowedPages = array_flip(\App\Support\AdminPermissions::pageKeysForActor($actor));
        $can = static fn (string $ability): bool => \App\Support\AdminPermissions::actorCan($actor, $ability);

        $groups = [];

        if ($can('admin.settings')) {
            $groups[] = $this->group('settings', 'Настройки', $this->searchSettings($q, $limit));
        }

        $groups[] = $this->group('pages', 'Разделы', $this->searchPages($q, $limit, $allowedPages));

        if ($can('content.series')) {
            $groups[] = $this->group('series', 'Сериалы', $this->searchSeries($q, $limit));
        }
        if ($can('content.collections')) {
            $groups[] = $this->group('collections', 'Подборки', $this->searchCollections($q, $limit));
        }
        if ($can('content.studios')) {
            $groups[] = $this->group('studios', 'Студии', $this->searchStudios($q, $limit));
        }
        if ($can('content.taxonomy')) {
            $groups[] = $this->group('taxonomy', 'Справочники', $this->searchTaxonomy($q, $limit));
        }
        if ($can('moderation.users')) {
            $groups[] = $this->group('users', 'Пользователи', $this->searchUsers($q, min(5, $limit)));
        }

        $groups = array_values(array_filter($groups, fn (array $group) => $group['items'] !== []));

        return response()->json([
            'q' => $q,
            'groups' => $groups,
        ]);
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return array{key: string, label: string, items: list<array<string, mixed>>}
     */
    private function group(string $key, string $label, array $items): array
    {
        return ['key' => $key, 'label' => $label, 'items' => $items];
    }

    /**
     * @param array<string, int> $allowedPages
     * @return list<array<string, mixed>>
     */
    private function searchPages(string $q, int $limit, array $allowedPages): array
    {
        $scored = [];
        foreach (self::PAGES as $page) {
            if (!isset($allowedPages[$page['key']])) {
                continue;
            }
            $haystack = implode(' ', [$page['title'], $page['subtitle'], $page['keywords'], $page['key']]);
            $score = $this->score($haystack, $q);
            if ($score <= 0) {
                continue;
            }
            $scored[] = [
                'score' => $score,
                'item' => [
                    'type' => 'page',
                    'id' => $page['key'],
                    'title' => $page['title'],
                    'subtitle' => $page['subtitle'],
                    'path' => $page['path'],
                ],
            ];
        }

        return $this->takeTop($scored, $limit);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function searchSettings(string $q, int $limit): array
    {
        $scored = [];

        foreach (self::EXTRA_SETTINGS as $field) {
            $haystack = implode(' ', [$field['label'], $field['key'], $field['keywords'], self::SECTION_LABELS[$field['section']] ?? '']);
            $score = $this->score($haystack, $q);
            if ($score <= 0) {
                continue;
            }
            $scored[] = [
                'score' => $score + 5,
                'item' => $this->settingItem($field['key'], $field['label'], $field['section']),
            ];
        }

        foreach (SiteConfig::definitions() as $key => $definition) {
            $group = (string) ($definition['group'] ?? '');
            $section = self::GROUP_SECTION[$group] ?? null;
            if ($section === null) {
                continue;
            }

            $label = (string) ($definition['label'] ?? $key);
            $description = (string) ($definition['description'] ?? '');
            $haystack = implode(' ', [$label, $key, $description, self::SECTION_LABELS[$section] ?? '', $group]);
            $score = $this->score($haystack, $q);
            if ($score <= 0) {
                continue;
            }

            $scored[] = [
                'score' => $score,
                'item' => $this->settingItem($key, $label, $section, $description !== '' ? $description : null),
            ];
        }

        return $this->takeTop($scored, $limit);
    }

    /**
     * @return array<string, mixed>
     */
    private function settingItem(string $key, string $label, string $section, ?string $description = null): array
    {
        $sectionLabel = self::SECTION_LABELS[$section] ?? $section;

        return [
            'type' => 'setting',
            'id' => $key,
            'title' => $label,
            'subtitle' => $description ? "Настройки → {$sectionLabel} · {$description}" : "Настройки → {$sectionLabel}",
            'path' => '/settings?highlight=' . rawurlencode($key) . '#' . rawurlencode($section),
            'meta' => [
                'section' => $section,
                'field' => $key,
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function searchSeries(string $q, int $limit): array
    {
        $like = '%' . $this->escapeLike($q) . '%';
        $rows = Series::query()
            ->where(function ($builder) use ($like) {
                $builder->where('title', 'like', $like)
                    ->orWhere('title_en', 'like', $like)
                    ->orWhere('title_original', 'like', $like)
                    ->orWhere('slug', 'like', $like)
                    ->orWhere('kp_id', 'like', $like)
                    ->orWhere('imdb_id', 'like', $like)
                    ->orWhere('tmdb_id', 'like', $like);
            })
            ->orderByDesc('is_pinned')
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'title', 'slug', 'kp_id', 'year', 'start_year', 'poster_url']);

        return $rows->map(function (Series $row) {
            $year = $row->year ?: $row->start_year;
            $bits = array_filter([
                $row->kp_id ? 'KP ' . $row->kp_id : null,
                $year ? (string) $year : null,
            ]);

            return [
                'type' => 'series',
                'id' => (string) $row->id,
                'title' => (string) $row->title,
                'subtitle' => $bits ? implode(' · ', $bits) : ($row->slug ?: null),
                'path' => '/series?id=' . $row->id,
                'image' => $this->nullableUrl($row->poster_url),
                'image_kind' => 'poster',
            ];
        })->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function searchCollections(string $q, int $limit): array
    {
        $like = '%' . $this->escapeLike($q) . '%';
        $rows = Collection::query()
            ->where(function ($builder) use ($like) {
                $builder->where('title', 'like', $like)
                    ->orWhere('slug', 'like', $like);
            })
            ->catalogOrder()
            ->limit($limit)
            ->get(['id', 'title', 'slug', 'cover_url']);

        return $rows->map(fn (Collection $row) => [
            'type' => 'collection',
            'id' => (string) $row->id,
            'title' => (string) $row->title,
            'subtitle' => $row->slug ? '/' . $row->slug . '/' : null,
            'path' => '/collections?id=' . $row->id,
            'image' => $this->nullableUrl($row->cover_url),
            'image_kind' => 'cover',
        ])->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function searchStudios(string $q, int $limit): array
    {
        $like = '%' . $this->escapeLike($q) . '%';
        $rows = Studio::query()
            ->where(function ($builder) use ($like) {
                $builder->where('title', 'like', $like)
                    ->orWhere('slug', 'like', $like);
            })
            ->catalogOrder()
            ->limit($limit)
            ->get(['id', 'title', 'slug', 'logo_url']);

        return $rows->map(fn (Studio $row) => [
            'type' => 'studio',
            'id' => (string) $row->id,
            'title' => (string) $row->title,
            'subtitle' => $row->slug ? '/' . $row->slug . '/' : null,
            'path' => '/studios?id=' . $row->id,
            'image' => $this->nullableUrl($row->logo_url),
            'image_kind' => 'logo',
        ])->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function searchTaxonomy(string $q, int $limit): array
    {
        $like = '%' . $this->escapeLike($q) . '%';
        $perType = max(2, (int) ceil($limit / 2));
        $items = [];

        $maps = [
            'genres' => Genre::class,
            'countries' => Country::class,
            'people' => Person::class,
            'years' => Year::class,
            'voices' => Voice::class,
        ];

        foreach ($maps as $type => $model) {
            $columns = ['id', 'name', 'slug'];
            if ($type === 'people') {
                $columns[] = 'photo_url';
            }

            $rows = $model::query()
                ->where(function ($builder) use ($like) {
                    $builder->where('name', 'like', $like)
                        ->orWhere('slug', 'like', $like);
                })
                ->orderBy('name')
                ->limit($perType)
                ->get($columns);

            foreach ($rows as $row) {
                $image = $type === 'people' ? $this->nullableUrl($row->photo_url ?? null) : null;
                $items[] = [
                    'type' => 'taxonomy',
                    'id' => (string) $row->id,
                    'title' => (string) $row->name,
                    'subtitle' => (self::TAXONOMY_LABELS[$type] ?? $type) . ($row->slug ? ' · ' . $row->slug : ''),
                    'path' => '/taxonomy?type=' . rawurlencode($type) . '&id=' . $row->id,
                    'image' => $image,
                    'image_kind' => $type === 'people' ? 'avatar' : null,
                    'meta' => [
                        'taxonomy_type' => $type,
                    ],
                ];
            }
        }

        return array_slice($items, 0, $limit);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function searchUsers(string $q, int $limit): array
    {
        $like = '%' . $this->escapeLike($q) . '%';
        $rows = User::query()
            ->where(function ($builder) use ($like) {
                $builder->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like);
            })
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'name', 'email']);

        return $rows->map(fn (User $row) => [
            'type' => 'user',
            'id' => (string) $row->id,
            'title' => (string) ($row->name ?: $row->email),
            'subtitle' => $row->email,
            'path' => $row->email
                ? '/users?email=' . rawurlencode((string) $row->email)
                : '/users?name=' . rawurlencode((string) $row->name),
            'image' => null,
            'image_kind' => 'avatar',
        ])->all();
    }

    /**
     * @param list<array{score: int, item: array<string, mixed>}> $scored
     * @return list<array<string, mixed>>
     */
    private function takeTop(array $scored, int $limit): array
    {
        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);
        $seen = [];
        $items = [];
        foreach ($scored as $row) {
            $id = (string) ($row['item']['id'] ?? '');
            if ($id !== '' && isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $items[] = $row['item'];
            if (count($items) >= $limit) {
                break;
            }
        }

        return $items;
    }

    private function score(string $haystack, string $query): int
    {
        $haystack = mb_strtolower($haystack);
        $query = mb_strtolower(trim($query));
        if ($query === '') {
            return 0;
        }

        if (str_contains($haystack, $query)) {
            return 100 + (str_starts_with($haystack, $query) ? 20 : 0);
        }

        $words = preg_split('/\s+/u', $query) ?: [];
        $words = array_values(array_filter($words, fn ($w) => mb_strlen($w) >= 2));
        if ($words === []) {
            return 0;
        }

        $hits = 0;
        foreach ($words as $word) {
            if (str_contains($haystack, $word)) {
                $hits++;
            }
        }

        if ($hits === 0) {
            return 0;
        }

        return (int) round(70 * ($hits / count($words)));
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    private function nullableUrl(mixed $value): ?string
    {
        $url = trim((string) ($value ?? ''));

        return $url !== '' ? $url : null;
    }
}
