<?php

namespace App\Support;

use App\Models\SiteSetting;

class SiteBranding
{
    public static function logoUrl(): ?string
    {
        $custom = trim((string)SiteSetting::get('site_logo_url', ''));
        if ($custom !== '') {
            return $custom;
        }

        $theme = ThemeManager::assetVars();

        return isset($theme['logo']) ? (string)$theme['logo'] : null;
    }

    public static function backgroundUrl(): ?string
    {
        $url = trim((string)SiteSetting::get('site_background_url', ''));

        return $url !== '' ? $url : null;
    }

    public static function faviconUrl(): ?string
    {
        $url = trim((string)SiteSetting::get('site_favicon_url', ''));

        return $url !== '' ? $url : null;
    }

    public static function headerOffset(): int
    {
        $raw = SiteSetting::get('site_background_header_offset', '200');

        return max(0, min(600, (int)$raw));
    }

    /**
     * @return array<string, string>
     */
    public static function siteVars(): array
    {
        $logo = self::logoUrl();
        $background = self::backgroundUrl();
        $favicon = self::faviconUrl();

        return [
            'logo' => $logo ?? '',
            'background' => $background ?? '',
            'has_background' => $background ? '1' : '',
            'favicon' => $favicon ?? '',
            'has_favicon' => $favicon ? '1' : '',
            'background_header_offset' => (string)self::headerOffset(),
            'body_class' => $background ? 'has-site-bg' : '',
        ];
    }
}
