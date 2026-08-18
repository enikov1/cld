<?php

namespace App\Services;

use App\Models\SiteSetting;

use App\Support\SiteConfig;

class AllohaAutoSyncSettings
{
    public const SETTING_KEY = 'alloha_auto_sync';
    public const LAST_RUN_KEY = 'alloha_auto_sync_last_run';

    /**
     * @return array{
     *     enabled: bool,
     *     interval_minutes: int,
     *     latest_days: int,
     *     auto_add_new: bool,
     *     new_is_hidden: bool,
     *     new_is_active: bool,
     *     download_poster_new: bool,
     *     update_existing: bool,
     *     update_ratings: bool,
     *     update_players: bool,
     *     update_voices: bool,
     *     update_metadata: bool,
     *     update_poster: bool,
     *     update_genres_countries: bool,
     *     fill_empty_only: bool,
     *     bump_date_on_update: bool
     * }
     */
    public static function get(): array
    {
        $raw = SiteSetting::get(self::SETTING_KEY, '');
        $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;

        return self::normalize(is_array($decoded) ? $decoded : []);
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public static function normalize(array $input): array
    {
        $defaults = self::defaults();
        $interval = (int)($input['interval_minutes'] ?? $defaults['interval_minutes']);
        $allowedIntervals = array_column(self::intervalOptions(), 'value');
        if (!in_array($interval, $allowedIntervals, true)) {
            $interval = $defaults['interval_minutes'];
        }

        $days = max(1, min(30, (int)($input['latest_days'] ?? $defaults['latest_days'])));

        return [
            'enabled' => (bool)($input['enabled'] ?? $defaults['enabled']),
            'interval_minutes' => $interval,
            'latest_days' => $days,
            'auto_add_new' => (bool)($input['auto_add_new'] ?? $defaults['auto_add_new']),
            'new_is_hidden' => (bool)($input['new_is_hidden'] ?? $defaults['new_is_hidden']),
            'new_is_active' => (bool)($input['new_is_active'] ?? $defaults['new_is_active']),
            'download_poster_new' => (bool)($input['download_poster_new'] ?? $defaults['download_poster_new']),
            'update_existing' => (bool)($input['update_existing'] ?? $defaults['update_existing']),
            'update_ratings' => (bool)($input['update_ratings'] ?? $defaults['update_ratings']),
            'update_players' => (bool)($input['update_players'] ?? $defaults['update_players']),
            'update_voices' => (bool)($input['update_voices'] ?? $input['update_players'] ?? $defaults['update_voices']),
            'update_metadata' => (bool)($input['update_metadata'] ?? $defaults['update_metadata']),
            'update_poster' => (bool)($input['update_poster'] ?? $defaults['update_poster']),
            'update_genres_countries' => (bool)($input['update_genres_countries'] ?? $defaults['update_genres_countries']),
            'fill_empty_only' => (bool)($input['fill_empty_only'] ?? $defaults['fill_empty_only']),
            'bump_date_on_update' => (bool)($input['bump_date_on_update'] ?? $defaults['bump_date_on_update']),
        ];
    }

    /**
     * @param array<string,mixed> $settings
     */
    public static function save(array $settings): void
    {
        SiteSetting::set(self::SETTING_KEY, json_encode(self::normalize($settings), JSON_UNESCAPED_UNICODE));
    }

    public static function isEnabled(): bool
    {
        return self::get()['enabled'];
    }

    public static function lastRunAt(): ?int
    {
        $value = SiteSetting::get(self::LAST_RUN_KEY, '');

        return is_numeric($value) && (int)$value > 0 ? (int)$value : null;
    }

    public static function markRun(): void
    {
        SiteSetting::set(self::LAST_RUN_KEY, (string)time());
    }

    public static function isDue(): bool
    {
        if (!self::isEnabled()) {
            return false;
        }

        $last = self::lastRunAt();
        if ($last === null) {
            return true;
        }

        $interval = self::get()['interval_minutes'] * 60;

        return (time() - $last) >= $interval;
    }

    /**
     * @return array<string,mixed>
     */
    public static function defaults(): array
    {
        return [
            'enabled' => false,
            'interval_minutes' => 60,
            'latest_days' => 7,
            'auto_add_new' => true,
            'new_is_hidden' => true,
            'new_is_active' => true,
            'download_poster_new' => true,
            'update_existing' => true,
            'update_ratings' => true,
            'update_players' => true,
            'update_voices' => true,
            'update_metadata' => false,
            'update_poster' => false,
            'update_genres_countries' => false,
            'fill_empty_only' => true,
            'bump_date_on_update' => false,
        ];
    }

    /**
     * @return list<array{value: int, label: string}>
     */
    public static function intervalOptions(): array
    {
        return [
            ['value' => 15, 'label' => 'Каждые 15 минут'],
            ['value' => 30, 'label' => 'Каждые 30 минут'],
            ['value' => 60, 'label' => 'Каждый час'],
            ['value' => 180, 'label' => 'Каждые 3 часа'],
            ['value' => 360, 'label' => 'Каждые 6 часов'],
            ['value' => 720, 'label' => 'Каждые 12 часов'],
            ['value' => 1440, 'label' => 'Раз в сутки'],
        ];
    }

    /**
     * @return array<string,bool>
     */
    public static function toImportFlags(array $settings, bool $isNew): array
    {
        $allohaPlayersEnabled = SiteConfig::bool('player_alloha_sync_enabled');

        if ($isNew) {
            return [
                'sync_ratings' => true,
                'sync_players' => $allohaPlayersEnabled,
                'sync_voices' => true,
                'sync_metadata' => true,
                'sync_poster' => $settings['download_poster_new'],
                'sync_genres_countries' => true,
                'fill_empty_only' => false,
                'bump_date' => false,
            ];
        }

        return [
            'sync_ratings' => $settings['update_ratings'],
            'sync_players' => $allohaPlayersEnabled && $settings['update_players'],
            'sync_voices' => (bool) $settings['update_voices'],
            'sync_metadata' => $settings['update_metadata'],
            'sync_poster' => $settings['update_poster'],
            'sync_genres_countries' => $settings['update_genres_countries'],
            'fill_empty_only' => $settings['fill_empty_only'],
            'bump_date' => $settings['bump_date_on_update'],
        ];
    }
}
