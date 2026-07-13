<?php

namespace App\Services;

use App\Models\SiteSetting;

class KinoPoiskConfig
{
    public const SETTING_KEY = 'kinopoisk_api_key';

    public static function apiKey(): string
    {
        $fromSettings = trim((string)SiteSetting::get(self::SETTING_KEY, ''));
        if ($fromSettings !== '') {
            return $fromSettings;
        }

        return trim((string)config('kinopoisk.api_key', ''));
    }

    public static function isConfigured(): bool
    {
        return self::apiKey() !== '';
    }
}
