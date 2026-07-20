<?php

namespace App\Support;

use App\Models\Collection;
use App\Models\Series;
use App\Support\AdminPath;
use App\Support\PluralRu;
use App\Support\SeriesUrl;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\ReactionWidgetService;
use App\Services\SeriesCardMapper;
use Illuminate\Support\Facades\Auth;

class TplSampleValues
{
    /**
     * @return array{values: array<string, string>, source: string}
     */
    public static function forTemplatePath(string $path): array
    {
        $path = str_replace('\\', '/', ltrim($path, '/'));
        $values = self::globalValues();
        $source = 'Общие настройки сайта';

        if ($path === 'home.tpl') {
            $values = array_merge($values, self::homeValues());
            $source = 'Главная страница (текущие настройки)';
        } elseif (in_array($path, ['partials/series_cards.tpl', 'partials/catalog_series_grid.tpl', 'partials/series_card_overlays.tpl'], true)) {
            $values = array_merge($values, self::seriesCardItemSampleValues());
            $source = 'Пример карточки сериала в сетке';
        } elseif ($path === 'search.tpl') {
            $values = array_merge($values, self::searchValues());
            $source = 'Страница поиска (пример без запроса)';
        } elseif (str_starts_with($path, 'series/') || $path === 'partials/reactions_widget.tpl') {
            $seriesValues = self::seriesValues();
            $values = array_merge($values, $seriesValues['values']);
            $source = $seriesValues['source'];
            if ($path === 'partials/reactions_widget.tpl') {
                $values = array_merge($values, self::reactionsValues($seriesValues['series_id'] ?? null));
            }
        } elseif (str_starts_with($path, 'collections/')) {
            $values = array_merge($values, self::collectionsValues($path));
            $source = $path === 'collections/index.tpl'
                ? 'Список подборок'
                : 'Пример подборки из каталога';
        } elseif (str_starts_with($path, 'studios/')) {
            $values = array_merge($values, self::studiosValues($path));
            $source = $path === 'studios/index.tpl'
                ? 'Список студий'
                : 'Пример студии из каталога';
        } elseif (str_starts_with($path, 'profile/')) {
            $values = array_merge($values, self::profileValues());
            $source = 'Профиль текущего администратора';
        } elseif (str_starts_with($path, 'errors/')) {
            $values = array_merge($values, self::errorValues($path));
            $source = 'Страница ошибки';
        } elseif ($path === 'layout.tpl') {
            $values = array_merge($values, self::layoutValues());
            $source = 'Layout (meta из примера главной/сериала)';
        }

        foreach ($values as $key => $value) {
            $values[$key] = self::stringify($value);
        }

        return [
            'values' => $values,
            'source' => $source,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function globalValues(): array
    {
        return [
            'site.name' => SiteSetting::get('site_name', config('app.name', 'LordSerial')),
            'site.tagline' => SiteSetting::get('site_tagline', 'Сериалы онлайн'),
            'site.footer_text' => SiteSetting::get('footer_text', 'Сериалы онлайн в HD качестве'),
            'site.year' => (string)date('Y'),
            'site.logo' => \App\Support\SiteBranding::logoUrl() ?? '',
            'site.background' => \App\Support\SiteBranding::backgroundUrl() ?? '',
            'site.has_background' => \App\Support\SiteBranding::backgroundUrl() ? '1' : '',
            'site.favicon' => \App\Support\SiteBranding::faviconUrl() ?? '',
            'site.has_favicon' => \App\Support\SiteBranding::faviconUrl() ? '1' : '',
            'site.background_header_offset' => (string)\App\Support\SiteBranding::headerOffset(),
            'site.background_color' => \App\Support\SiteBranding::backgroundColor(),
            'site.background_hide_mobile' => \App\Support\SiteBranding::hideBackgroundOnMobile() ? '1' : '',
            'site.body_class' => \App\Support\SiteBranding::siteVars()['body_class'],
            'csrf_token' => '(генерируется для каждой сессии)',
            'search_query' => '',
            'auth.logged_in' => Auth::check() ? '1' : '',
            'auth.is_admin' => Auth::user()?->isAdmin() ? '1' : '',
            'auth.name' => Auth::user()?->name ?? '',
            'auth.email' => Auth::user()?->email ?? '',
            'admin_url' => AdminPath::base(),
            'auth_panel' => '',
            'mega_category_slug' => 'genre',
            'THEME' => ThemeManager::webPath(),
            'theme.stylesheets' => [ThemeManager::assetUrl('site.css')],
            'theme.scripts' => [
                ThemeManager::assetUrl('site.js'),
            ],
            'theme.js' => ThemeManager::assetUrl('site.js'),
            'theme.home_carousels_js' => ThemeManager::assetUrl('home-carousels.js'),
            'theme.home_carousels_css' => ThemeManager::assetUrl('home-carousels.css'),
            'theme.logo' => ThemeManager::assetPath('logo.svg') ? ThemeManager::assetUrl('logo.svg') : '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function homeValues(): array
    {
        return [
            'is_home_first' => '1',
            'page.heading' => SiteSetting::get('home_heading', 'Сериалы онлайн'),
            'page.lead' => '',
            'home_seo_html' => mb_substr(strip_tags(SiteSetting::get('home_seo_html', '') ?: ''), 0, 120),
            'seo.title' => 'Сериалы онлайн, смотреть в хорошем HD качестве бесплатно',
            'seo.description' => 'Lordserials — смотреть новые серии любимых сериалов в хорошем переводе бесплатно.',
            'seo.canonical' => url('/'),
            'seo.prev' => '',
            'seo.next' => '',
            'has_schedule_calendar' => '1',
            'schedule_calendar.month_label' => 'Июль 2026',
            'new_episodes_list' => [],
            'new_episodes_block' => '',
            'schedule_calendar_block' => '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function searchValues(): array
    {
        $series = Series::query()->published()->first();

        return [
            'query' => '',
            'popular_searches' => [
                ['query' => 'игра престолов', 'url' => '/search?q=' . rawurlencode('игра престолов'), 'hits' => 42],
                ['query' => 'вайнона райдер', 'url' => '/search?q=' . rawurlencode('вайнона райдер'), 'hits' => 18],
            ],
            'seo.title' => 'Поиск по сериалам',
            'seo.description' => 'Поиск сериалов и фильмов по названию и описанию',
            'seo.canonical' => url('/search'),
            'item.title' => $series?->title ?? '',
            'item.slug' => $series?->slug ?? '',
            'item.url' => $series ? SeriesUrl::path($series) : '',
            'item.poster_url' => $series?->poster_url ?? '',
        ] + ($series ? self::seriesCardFieldsFromSeries($series) : self::seriesCardItemSampleValues());
    }

    /**
     * @return array{values: array<string, mixed>, source: string, series_id?: int}
     */
    private static function seriesValues(): array
    {
        $series = Series::query()
            ->with(['genres', 'countries', 'actors'])
            ->published()
            ->orderByDesc('id')
            ->first();

        if (!$series) {
            return [
                'values' => [
                    'seo.canonical' => url('/'),
                ],
                'source' => 'Нет опубликованных сериалов в базе',
            ];
        }

        $canonical = url(SeriesUrl::path($series));

        return [
            'series_id' => $series->id,
            'source' => 'Пример: «' . $series->title . '»',
            'values' => [
                'series.url' => SeriesUrl::path($series),
                'series.slug' => $series->slug,
                'series.title' => $series->title,
                'series.title_en' => $series->title_en ?? '',
                'series.title_original' => $series->title_original ?? '',
                'series.year' => $series->year ? (string)$series->year : '',
                'series.description' => $series->description ?? '',
                'series.short_description' => $series->short_description ?? '',
                'series.poster_url' => $series->poster_url ?? '',
                'series.kp_rating' => $series->kp_rating !== null ? (string)$series->kp_rating : '',
                'series.imdb_rating' => $series->imdb_rating !== null ? (string)$series->imdb_rating : '',
                'series.user_rating' => $series->userRatingLabel() ?? '',
                'series.genres_text' => $series->genres->pluck('name')->implode(', '),
                'series.countries_text' => $series->countries->pluck('name')->implode(', '),
                'series.actors_text' => $series->actors->pluck('name')->implode(', '),
                'series.broadcast_status_label' => $series->broadcastStatusLabel() ?? '',
                'series.episode_progress_label' => $series->episodeProgressLabel(),
                'series.slogan' => $series->slogan ?? '',
                'active_player_url' => $series->player_url ?? '',
                'has_player' => $series->player_url ? '1' : '',
                'has_reactions' => ReactionWidgetService::isEnabled() ? '1' : '',
                'has_schedule' => '',
                'seo.canonical' => $canonical,
                'seo.title' => $series->title . ' смотреть онлайн в хорошем HD качестве бесплатно',
                'seo.description' => mb_substr($series->short_description ?: ($series->description ?? ''), 0, 160),
                'item.title' => $series->title,
                'item.slug' => $series->slug,
                'item.url' => SeriesUrl::path($series),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function reactionsValues(?int $seriesId): array
    {
        if (!$seriesId) {
            return [];
        }

        $series = Series::query()->find($seriesId);
        if (!$series) {
            return [];
        }

        $payload = ReactionWidgetService::payloadForSeries($series, request());
        $firstItem = ($payload['items'][0] ?? null);

        return [
            'reactions.badge' => $payload['badge'] ?? '',
            'reactions.title' => $payload['title'] ?? '',
            'reactions.total_label' => $payload['total_label'] ?? '',
            'item.emoji' => $firstItem['emoji'] ?? '',
            'item.label' => $firstItem['label'] ?? '',
            'item.count_label' => $firstItem['count_label'] ?? '',
            'item.percent' => isset($firstItem['percent']) ? (string)$firstItem['percent'] : '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function collectionsValues(string $path): array
    {
        $collection = Collection::query()->where('is_active', true)->orderByDesc('id')->first();
        if (!$collection) {
            return [
                'page.heading' => 'Подборки сериалов',
            ];
        }

        $values = [
            'page.heading' => 'Подборки сериалов',
            'collection.title' => $collection->title,
            'collection.slug' => $collection->slug,
            'collection.source_updated_at' => $collection->source_updated_at?->format('d.m.Y H:i') ?? '',
            'seo.canonical' => url('/collections/' . $collection->slug . '/'),
            'seo.title' => $collection->title,
        ];

        if ($path !== 'collections/index.tpl') {
            $item = $collection->items()
                ->with(['series'])
                ->whereHas('series', fn ($q) => $q->where('is_active', true))
                ->orderBy('rank_order')
                ->first();

            if ($item?->series) {
                $values['item.title'] = $item->series->title;
                $values['item.slug'] = $item->series->slug;
                $values['item.url'] = SeriesUrl::path($item->series);
                $values['item.poster_url'] = $item->series->poster_url ?? '';
            }
        }

        return $values;
    }

    /**
     * @return array<string, mixed>
     */
    private static function studiosValues(string $path): array
    {
        $studio = \App\Models\Studio::query()
            ->where('is_active', true)
            ->where('is_hidden', false)
            ->withCount(['items as items_count' => function ($query) {
                $query->whereHas('series', function ($q) {
                    $q->where('is_active', true)
                        ->where('is_hidden', false);
                });
            }])
            ->orderByDesc('id')
            ->first();

        if (!$studio) {
            return [
                'page.heading' => 'Студии',
            ];
        }

        $itemsCount = (int)($studio->items_count ?? 0);

        $values = [
            'page.heading' => 'Студии',
            'studios_total' => '1',
            'studios_total_word' => PluralRu::studios(1),
            'item.slug' => $studio->slug,
            'item.title' => $studio->title,
            'item.description' => $studio->description ?? '',
            'item.logo_url' => $studio->logo_url ?? '',
            'item.items_count' => (string)$itemsCount,
            'item.items_count_word' => PluralRu::series($itemsCount),
            'seo.title' => 'Студии — каталог сериалов по студиям',
            'seo.description' => 'Сериалы по студиям — смотреть онлайн бесплатно.',
            'seo.canonical' => url('/studios/'),
        ];

        if ($path !== 'studios/index.tpl') {
            $values['studio.title'] = $studio->title;
            $values['studio.slug'] = $studio->slug;
            $values['studio.description'] = $studio->description ?? '';
            $values['studio.logo_url'] = $studio->logo_url ?? '';
            $values['studio_total'] = (string)$itemsCount;
            $values['studio_total_word'] = PluralRu::series($itemsCount);
            $values['studio_has_items'] = $itemsCount > 0 ? '1' : '';
            $values['seo.canonical'] = url('/studios/' . $studio->slug . '/');
            $values['seo.title'] = $studio->title . ' — студия';

            $item = $studio->items()
                ->with(['series'])
                ->whereHas('series', fn ($q) => $q->where('is_active', true)->where('is_hidden', false))
                ->orderBy('rank_order')
                ->first();

            if ($item?->series) {
                $values['item.title'] = $item->series->title;
                $values['item.slug'] = $item->series->slug;
                $values['item.url'] = SeriesUrl::path($item->series);
                $values['item.poster_url'] = $item->series->poster_url ?? '';
            }
        }

        return $values;
    }

    /**
     * @return array<string, mixed>
     */
    private static function profileValues(): array
    {
        $user = Auth::user() ?? User::query()->orderBy('id')->first();
        if (!$user) {
            return [];
        }

        return [
            'profile.name' => $user->name,
            'profile.email' => $user->email,
            'profile.initial' => mb_strtoupper(mb_substr($user->name, 0, 1)),
            'profile.registered_at' => $user->created_at?->format('d.m.Y') ?? '',
            'profile_stats.lists' => '3',
            'profile_stats.items' => '0',
            'profile_stats.comments' => '0',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function errorValues(string $path): array
    {
        $code = (int)basename($path, '.tpl');

        return [
            'error_code' => (string)$code,
            'error_title' => match ($code) {
                403 => 'Доступ запрещён',
                404 => 'Страница не найдена',
                419 => 'Сессия истекла',
                500 => 'Ошибка сервера',
                503 => 'Сайт на обслуживании',
                default => 'Ошибка',
            },
            'error_message' => 'Пример текста ошибки для шаблона',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function layoutValues(): array
    {
        $series = self::seriesValues();
        $home = self::homeValues();

        return [
            'meta.title' => $series['values']['seo.title'] ?? $home['seo.title'],
            'meta.description' => $series['values']['seo.description'] ?? $home['seo.description'],
            'meta.canonical' => $series['values']['seo.canonical'] ?? $home['seo.canonical'],
            'content' => '(HTML страницы)',
            'header' => '(HTML шапки)',
            'footer' => '(HTML подвала)',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function seriesCardItemSampleValues(): array
    {
        return [
            'item.season_badge' => 'S5',
            'item.episode_badge' => 'E12',
            'item.episode_progress_label' => '5 сезон, 12 серия',
            'item.top_reaction_emoji' => '🔥',
            'item.badge_new_episode' => '1',
            'item.badge_new_episode_label' => 'Новая серия',
            'item.badge_popular' => '1',
            'item.badge_popular_label' => 'Популярно',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function seriesCardFieldsFromSeries(Series $series): array
    {
        $mapped = SeriesCardMapper::mapSeries([$series])[0] ?? [];

        $out = [];
        foreach ($mapped as $key => $value) {
            if (is_bool($value)) {
                $out['item.' . $key] = $value ? '1' : '';
                continue;
            }
            $out['item.' . $key] = $value;
        }

        return $out;
    }

    private static function stringify(mixed $value): string
    {
        if ($value === null || $value === false || $value === '') {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '';
        }

        return (string)$value;
    }

    public static function truncate(string $value, int $max = 80): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
        if ($value === '') {
            return '(пусто)';
        }

        if (mb_strlen($value) <= $max) {
            return $value;
        }

        return mb_substr($value, 0, $max - 1) . '…';
    }
}
