<?php

namespace App\Services;

use App\Models\SiteSetting;

class TmdbAutoSyncSettings
{
    public const SETTING_KEY = 'tmdb_auto_sync';
    public const LAST_RUN_KEY = 'tmdb_auto_sync_last_run';

    /**
     * @return array{enabled: bool}
     */
    public static function get(): array
    {
        $raw = SiteSetting::get(self::SETTING_KEY, '');
        $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;

        return self::normalize(is_array($decoded) ? $decoded : []);
    }

    /**
     * @param array<string,mixed> $input
     * @return array{enabled: bool}
     */
    public static function normalize(array $input): array
    {
        return [
            'enabled' => (bool)($input['enabled'] ?? self::defaults()['enabled']),
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

        return (time() - $last) >= 86400;
    }

    /**
     * @return array{enabled: bool}
     */
    public static function defaults(): array
    {
        return [
            'enabled' => false,
        ];
    }
}
