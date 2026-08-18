<?php

namespace App\Support;

use App\Models\SiteSetting;
use Illuminate\Http\Response;

class MaintenancePageRenderer
{
    public static function response(): Response
    {
        $baseDir = ThemeManager::activeBaseDir();
        $renderer = new TplRenderer($baseDir);
        $tpl = 'maintenance.tpl';

        if (!is_file($baseDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $tpl))) {
            return self::fallbackResponse();
        }

        $siteName = SiteSetting::get('site_name', config('app.name', 'LordSerial'));
        $logoUrl = trim((string)SiteSetting::get('site_logo_url', ''));
        $faviconUrl = trim((string)SiteSetting::get('site_favicon_url', ''));

        $vars = [
            'site_name' => $siteName,
            'site_logo_url' => $logoUrl,
            'has_logo' => $logoUrl !== '',
            'site_favicon_url' => $faviconUrl,
            'has_favicon' => $faviconUrl !== '',
            'maintenance_title' => SiteConfig::str('maintenance_title') ?: 'Сайт на техническом обслуживании',
            'maintenance_message' => SiteConfig::str('maintenance_message') ?: 'Мы проводим технические работы. Скоро вернёмся!',
            'year' => (string)date('Y'),
            'fonts_css' => ThemeManager::assetPath('fonts.css') ? ThemeManager::assetUrl('fonts.css') : '',
            'has_fonts_css' => ThemeManager::assetPath('fonts.css') ? '1' : '',
        ];

        $html = $renderer->render($tpl, $vars);

        return response($html, 503)
            ->header('Content-Type', 'text/html; charset=utf-8')
            ->header('Retry-After', '3600')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    private static function fallbackResponse(): Response
    {
        $title = SiteConfig::str('maintenance_title') ?: 'Сайт на техническом обслуживании';
        $message = SiteConfig::str('maintenance_message') ?: 'Мы проводим технические работы. Скоро вернёмся!';
        $html = '<!doctype html><html lang="ru"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<meta name="robots" content="noindex,nofollow">'
            . '<title>' . htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</title></head><body>'
            . '<h1>' . htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</h1>'
            . '<p>' . htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>'
            . '</body></html>';

        return response($html, 503)
            ->header('Content-Type', 'text/html; charset=utf-8')
            ->header('Retry-After', '3600')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
