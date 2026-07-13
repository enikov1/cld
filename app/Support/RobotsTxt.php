<?php

namespace App\Support;

use App\Models\SiteSetting;

class RobotsTxt
{
    public const SETTING_KEY = 'robots_txt';

    public static function defaultTemplate(): string
    {
        return implode("\n", [
            'User-agent: *',
            'Allow: /',
            'Disallow: /{admin_path}/',
            'Disallow: /api/',
            'Disallow: /password/',
            '',
            'Sitemap: {sitemap_url}',
        ]);
    }

    public static function content(): string
    {
        if (SiteConfig::bool('maintenance_enabled')) {
            return self::maintenanceContent();
        }

        $custom = trim((string)SiteSetting::get(self::SETTING_KEY, ''));
        $template = $custom !== '' ? $custom : self::defaultTemplate();

        return self::render($template);
    }

    public static function maintenanceContent(): string
    {
        return implode("\n", [
            'User-agent: *',
            'Disallow: /',
            '',
        ]) . "\n";
    }

    public static function render(string $template): string
    {
        $replacements = [
            '{admin_path}' => AdminPath::path(),
            '{sitemap_url}' => url('/sitemap.xml'),
            '{site_url}' => url('/'),
        ];

        $content = strtr($template, $replacements);

        return rtrim($content) . "\n";
    }
}
