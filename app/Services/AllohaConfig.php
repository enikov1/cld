<?php

namespace App\Services;

use App\Models\SiteSetting;

class AllohaConfig
{
    public const SETTING_KEY = 'alloha_api_token';

    public static function apiToken(): string
    {
        $fromSettings = trim((string)SiteSetting::get(self::SETTING_KEY, ''));
        if ($fromSettings !== '') {
            return $fromSettings;
        }

        return trim((string)config('alloha.api_token', ''));
    }

    public static function isConfigured(): bool
    {
        return self::apiToken() !== '';
    }
}
