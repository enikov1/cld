<?php

namespace App\Services;

use App\Models\SiteSetting;

class TmdbConfig
{
    public const SETTING_KEY = 'tmdb_api_key';

    public static function apiKey(): string
    {
        $fromSettings = trim((string)SiteSetting::get(self::SETTING_KEY, ''));
        if ($fromSettings !== '') {
            return $fromSettings;
        }

        return trim((string)config('tmdb.api_key', ''));
    }

    public static function isConfigured(): bool
    {
        return self::apiKey() !== '';
    }
}
