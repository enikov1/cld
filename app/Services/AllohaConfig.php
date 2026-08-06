<?php

namespace App\Services;

use App\Support\EncryptedSiteSecret;

class AllohaConfig
{
    public const SETTING_KEY = 'alloha_api_token';

    public static function apiToken(): string
    {
        $fromSettings = trim(EncryptedSiteSecret::get(self::SETTING_KEY));
        if ($fromSettings !== '') {
            return $fromSettings;
        }

        return trim((string) config('alloha.api_token', ''));
    }

    public static function isConfigured(): bool
    {
        return self::apiToken() !== '';
    }
}
