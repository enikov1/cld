<?php

namespace App\Support;

use Illuminate\Http\Request;

class AdminPermissions
{
    public const ABILITY_ALL = '*';

    /** Sentinel: route exists under /api/admin but is not mapped — deny everyone. */
    public const ABILITY_DENY = '__deny__';

    /**
     * Catalog of assignable abilities (API gates + UI pages).
     *
     * @return list<array{key: string, label: string, group: string, pages: list<string>}>
     */
    public static function catalog(): array
    {
        return [
            ['key' => 'content.series', 'label' => 'Сериалы', 'group' => 'Контент', 'pages' => ['series']],
            ['key' => 'content.collections', 'label' => 'Подборки', 'group' => 'Контент', 'pages' => ['collections']],
            ['key' => 'content.studios', 'label' => 'Студии', 'group' => 'Контент', 'pages' => ['studios']],
            ['key' => 'content.taxonomy', 'label' => 'Справочники', 'group' => 'Контент', 'pages' => ['taxonomy']],
            ['key' => 'content.media', 'label' => 'Медиатека и брендинг', 'group' => 'Контент', 'pages' => ['media']],
            ['key' => 'content.home_sections', 'label' => 'Секции главной', 'group' => 'Сайт', 'pages' => ['home-sections']],
            ['key' => 'content.nav', 'label' => 'Меню', 'group' => 'Сайт', 'pages' => ['nav-menu']],
            ['key' => 'content.reactions', 'label' => 'Реакции', 'group' => 'Сайт', 'pages' => ['reactions']],
            ['key' => 'content.templates', 'label' => 'Шаблоны', 'group' => 'Сайт', 'pages' => ['templates', 'tpl-docs']],
            ['key' => 'content.redirects', 'label' => 'Редиректы', 'group' => 'Сайт', 'pages' => ['redirects']],
            ['key' => 'content.sync', 'label' => 'Синхронизации (KP / Alloha / Rutube / плееры)', 'group' => 'Интеграции', 'pages' => ['sync', 'alloha-sync', 'rutube-sync']],

            ['key' => 'moderation.comments', 'label' => 'Комментарии', 'group' => 'Модерация', 'pages' => ['comments']],
            ['key' => 'moderation.player_reports', 'label' => 'Жалобы на плеер', 'group' => 'Модерация', 'pages' => ['player-reports']],
            ['key' => 'moderation.users', 'label' => 'Пользователи', 'group' => 'Модерация', 'pages' => ['users']],

            ['key' => 'admin.settings', 'label' => 'Настройки сайта', 'group' => 'Система', 'pages' => ['settings']],
            ['key' => 'admin.backup', 'label' => 'Бэкапы', 'group' => 'Система', 'pages' => ['backup']],
            ['key' => 'admin.tokens', 'label' => 'Токены доступа', 'group' => 'Система', 'pages' => ['admin-access']],
            ['key' => 'admin.audit', 'label' => 'Журнал аудита', 'group' => 'Система', 'pages' => ['audit-log']],
            ['key' => 'admin.stats', 'label' => 'Обзор и статистика просмотров', 'group' => 'Система', 'pages' => ['dashboard', 'views-stats']],
            ['key' => 'admin.cache', 'label' => 'Управление кэшем', 'group' => 'Система', 'pages' => []],
            ['key' => 'admin.system', 'label' => 'Информация о системе', 'group' => 'Система', 'pages' => []],
            ['key' => 'admin.cron', 'label' => 'История cron-задач', 'group' => 'Система', 'pages' => ['cron-runs']],
            ['key' => 'admin.search', 'label' => 'Глобальный поиск и статистика поиска', 'group' => 'Система', 'pages' => ['search-stats']],
        ];
    }

    /**
     * @return list<string>
     */
    public static function allAbilityKeys(): array
    {
        return array_values(array_map(static fn (array $row) => $row['key'], self::catalog()));
    }

    /**
     * Preset ability sets for quick selection in UI.
     *
     * @return array<string, list<string>>
     */
    public static function presets(): array
    {
        $content = array_values(array_filter(
            self::allAbilityKeys(),
            static fn (string $key) => str_starts_with($key, 'content.'),
        ));
        $moderation = array_values(array_filter(
            self::allAbilityKeys(),
            static fn (string $key) => str_starts_with($key, 'moderation.'),
        ));

        return [
            'full' => [self::ABILITY_ALL],
            'content' => $content,
            'moderation' => $moderation,
            'custom' => [],
        ];
    }

    /**
     * @param list<string>|null $abilities
     * @return list<string>
     */
    public static function normalizeAbilities(?array $abilities, ?string $role = null): array
    {
        if (is_array($abilities) && $abilities !== []) {
            $allowed = array_flip(array_merge(self::allAbilityKeys(), [self::ABILITY_ALL]));
            $out = [];
            foreach ($abilities as $ability) {
                $key = trim((string) $ability);
                if ($key !== '' && isset($allowed[$key])) {
                    $out[] = $key;
                }
            }
            $out = array_values(array_unique($out));
            if (in_array(self::ABILITY_ALL, $out, true)) {
                return [self::ABILITY_ALL];
            }

            return $out;
        }

        $role = $role ?: 'custom';
        $presets = self::presets();

        return $presets[$role] ?? [];
    }

    /**
     * Infer display role from abilities list.
     *
     * @param list<string> $abilities
     */
    public static function inferRole(array $abilities): string
    {
        $normalized = self::normalizeAbilities($abilities);
        if ($normalized === [self::ABILITY_ALL]) {
            return 'full';
        }

        $presets = self::presets();
        foreach (['content', 'moderation'] as $preset) {
            $want = $presets[$preset] ?? [];
            sort($want);
            $have = $normalized;
            sort($have);
            if ($want === $have) {
                return $preset;
            }
        }

        return 'custom';
    }

    /**
     * Map an admin API request to a required ability, or null if any authenticated actor may access.
     * Returns ABILITY_DENY for unmapped admin routes (deny-by-default).
     */
    public static function requiredAbility(Request $request): ?string
    {
        $path = trim($request->path(), '/');
        if (!str_starts_with($path, 'api/admin/') && $path !== 'api/admin') {
            return null;
        }

        $relative = $path === 'api/admin' ? '' : substr($path, strlen('api/admin/'));
        $segment = explode('/', $relative)[0] ?? '';

        return match ($segment) {
            '', 'site-access', 'me' => null,

            'stats', 'views-stats' => 'admin.stats',
            'cache' => 'admin.cache',
            'system' => 'admin.system',
            'cron-runs' => 'admin.cron',
            'global-search', 'search-stats' => 'admin.search',

            'admin-tokens' => 'admin.tokens',
            'audit-logs' => 'admin.audit',
            'settings' => 'admin.settings',
            'backup' => 'admin.backup',
            'themes' => 'admin.settings',

            'comments' => 'moderation.comments',
            'player-reports' => 'moderation.player_reports',
            'users' => 'moderation.users',

            'series' => 'content.series',
            'collections' => 'content.collections',
            'studios' => 'content.studios',
            'taxonomies' => 'content.taxonomy',
            'home-sections' => 'content.home_sections',
            'nav' => 'content.nav',
            'reactions' => 'content.reactions',
            'templates' => 'content.templates',
            'redirects' => 'content.redirects',
            'branding', 'media' => 'content.media',
            'sync', 'players', 'alloha', 'tmdb', 'kinopoisk' => 'content.sync',
            'sitemap' => 'admin.settings',

            default => self::ABILITY_DENY,
        };
    }

    /**
     * @param list<string> $abilities
     */
    public static function abilitiesCan(array $abilities, string $ability): bool
    {
        if ($ability === self::ABILITY_DENY) {
            return false;
        }

        if ($ability === self::ABILITY_ALL) {
            return true;
        }

        $abilities = self::normalizeAbilities($abilities);
        if (in_array(self::ABILITY_ALL, $abilities, true)) {
            return true;
        }

        if (in_array($ability, $abilities, true)) {
            return true;
        }

        // Prefix grants: content → content.*
        $prefix = explode('.', $ability)[0] ?? '';
        if ($prefix !== '' && in_array($prefix, $abilities, true)) {
            return true;
        }

        return false;
    }

    /**
     * Legacy role-only check (used when abilities missing).
     */
    public static function roleCan(string $role, string $ability): bool
    {
        return self::abilitiesCan(self::normalizeAbilities(null, $role), $ability);
    }

    /**
     * Whether the actor may grant the given ability set to another token.
     * Grants must be a subset of the actor's own abilities; `*` / `admin.tokens` only for master or `*`.
     *
     * @param array{type?: string, role?: string, abilities?: list<string>|null} $actor
     * @param list<string> $grantAbilities
     */
    public static function actorMayGrant(array $actor, array $grantAbilities): bool
    {
        $grantAbilities = self::normalizeAbilities($grantAbilities);
        if ($grantAbilities === []) {
            return false;
        }

        if (($actor['type'] ?? '') === 'master') {
            return true;
        }

        $actorAbilities = self::normalizeAbilities(
            is_array($actor['abilities'] ?? null) ? $actor['abilities'] : null,
            $actor['role'] ?? 'custom',
        );

        if (in_array(self::ABILITY_ALL, $actorAbilities, true)) {
            return true;
        }

        if (in_array(self::ABILITY_ALL, $grantAbilities, true)) {
            return false;
        }

        foreach ($grantAbilities as $ability) {
            if ($ability === 'admin.tokens' && !in_array('admin.tokens', $actorAbilities, true)) {
                return false;
            }
            if (!self::abilitiesCan($actorAbilities, $ability)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array{type?: string, role?: string, abilities?: list<string>|null} $actor
     */
    public static function actorCan(array $actor, string $ability): bool
    {
        if ($ability === self::ABILITY_DENY) {
            return false;
        }

        if (($actor['type'] ?? '') === 'master') {
            return true;
        }

        $abilities = $actor['abilities'] ?? null;
        if (!is_array($abilities) || $abilities === []) {
            return self::roleCan((string) ($actor['role'] ?? 'custom'), $ability);
        }

        return self::abilitiesCan($abilities, $ability);
    }

    /**
     * UI page keys for an actor.
     *
     * @param array{type?: string, role?: string, abilities?: list<string>|null} $actor
     * @return list<string>
     */
    public static function pageKeysForActor(array $actor): array
    {
        if (($actor['type'] ?? '') === 'master') {
            return self::allPageKeys();
        }

        $abilities = self::normalizeAbilities(
            is_array($actor['abilities'] ?? null) ? $actor['abilities'] : null,
            $actor['role'] ?? 'custom',
        );

        if (in_array(self::ABILITY_ALL, $abilities, true)) {
            return self::allPageKeys();
        }

        $pages = [];
        foreach (self::catalog() as $row) {
            if (!self::abilitiesCan($abilities, $row['key'])) {
                continue;
            }
            foreach ($row['pages'] as $page) {
                $pages[] = $page;
            }
        }

        return array_values(array_unique($pages));
    }

    /**
     * @deprecated Use pageKeysForActor
     * @return list<string>
     */
    public static function pageKeysForRole(string $role): array
    {
        return self::pageKeysForActor(['role' => $role, 'abilities' => self::normalizeAbilities(null, $role)]);
    }

    /**
     * @return list<string>
     */
    public static function allPageKeys(): array
    {
        $pages = [];
        foreach (self::catalog() as $row) {
            foreach ($row['pages'] as $page) {
                $pages[] = $page;
            }
        }

        return array_values(array_unique($pages));
    }
}
