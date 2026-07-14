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

    public static function backgroundColor(): string
    {
        $raw = trim((string)SiteSetting::get('site_background_color', '#111'));

        return self::normalizeColor($raw) ?? '#111';
    }

    public static function hideBackgroundOnMobile(): bool
    {
        return SiteSetting::get('site_background_hide_mobile', '0') === '1';
    }

    public static function normalizeColor(mixed $value): ?string
    {
        $raw = trim((string)$value);
        if (preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $raw) === 1) {
            return strtolower($raw);
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    public static function siteVars(): array
    {
        $logo = self::logoUrl();
        $background = self::backgroundUrl();
        $favicon = self::faviconUrl();

        $bodyClasses = [];
        if ($background) {
            $bodyClasses[] = 'has-site-bg';
            if (self::hideBackgroundOnMobile()) {
                $bodyClasses[] = 'site-bg-hide-mobile';
            }
        }

        return [
            'logo' => $logo ?? '',
            'background' => $background ?? '',
            'has_background' => $background ? '1' : '',
            'favicon' => $favicon ?? '',
            'has_favicon' => $favicon ? '1' : '',
            'background_header_offset' => (string)self::headerOffset(),
            'background_color' => self::backgroundColor(),
            'background_hide_mobile' => self::hideBackgroundOnMobile() ? '1' : '',
            'body_class' => implode(' ', $bodyClasses),
        ];
    }
}
