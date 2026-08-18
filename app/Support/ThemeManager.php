<?php

namespace App\Support;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\File;

class ThemeManager
{
    public static function themesRoot(): string
    {
        return resource_path('tpl');
    }

    /**
     * @return list<array{name: string, label: string}>
     */
    public static function listThemes(): array
    {
        $root = self::themesRoot();
        if (!is_dir($root)) {
            return [];
        }

        $themes = [];
        foreach (File::directories($root) as $dir) {
            $name = basename($dir);
            if (!self::isValidThemeName($name)) {
                continue;
            }
            if (!is_file($dir . DIRECTORY_SEPARATOR . 'layout.tpl')) {
                continue;
            }
            $themes[] = [
                'name' => $name,
                'label' => $name,
            ];
        }

        usort($themes, fn ($a, $b) => strcmp($a['name'], $b['name']));

        return $themes;
    }

    public static function activeName(): string
    {
        $configured = SiteSetting::get('active_theme');
        if (is_string($configured) && $configured !== '' && self::themeExists($configured)) {
            return $configured;
        }

        $themes = self::listThemes();
        if (count($themes) > 0) {
            return $themes[0]['name'];
        }

        return (string)config('tpl.default_theme', 'default');
    }

    public static function activeBaseDir(): string
    {
        $path = self::themesRoot() . DIRECTORY_SEPARATOR . self::activeName();
        if (is_dir($path)) {
            return $path;
        }

        return self::themesRoot();
    }

    public static function themeExists(string $name): bool
    {
        if (!self::isValidThemeName($name)) {
            return false;
        }

        $path = self::themesRoot() . DIRECTORY_SEPARATOR . $name;

        return is_dir($path) && is_file($path . DIRECTORY_SEPARATOR . 'layout.tpl');
    }

    public static function setActive(string $name): void
    {
        if (!self::themeExists($name)) {
            throw new \InvalidArgumentException("Theme not found: {$name}");
        }

        SiteSetting::set('active_theme', $name);
    }

    public static function assetsDir(string $themeName): string
    {
        return self::themesRoot() . DIRECTORY_SEPARATOR . $themeName . DIRECTORY_SEPARATOR . 'assets';
    }

    public static function assetPath(string $file, ?string $themeName = null): ?string
    {
        $themeName = $themeName ?? self::activeName();
        $path = self::assetsDir($themeName) . DIRECTORY_SEPARATOR . $file;

        return is_file($path) ? $path : null;
    }

    public static function resolveAssetDiskPath(string $file, ?string $themeName = null): ?string
    {
        $file = ltrim(str_replace('\\', '/', $file), '/');
        $basename = basename($file);
        $path = self::assetPath($basename, $themeName);
        if ($path === null) {
            return null;
        }

        if (self::shouldUseMinifiedAssets() && preg_match('/\.(css|js)$/i', $basename) && !preg_match('/\.min\.(css|js)$/i', $basename)) {
            $minPath = preg_replace('/\.(css|js)$/i', '.min.$1', $path);
            if (is_file($minPath)) {
                return $minPath;
            }
        }

        return $path;
    }

    public static function shouldUseMinifiedAssets(): bool
    {
        return !config('app.debug');
    }

    public static function webPath(?string $themeName = null): string
    {
        $themeName = $themeName ?? self::activeName();

        return '/theme-assets/' . $themeName;
    }

    public static function assetUrl(string $file, ?string $themeName = null): string
    {
        $themeName = $themeName ?? self::activeName();
        $file = ltrim(str_replace('\\', '/', $file), '/');
        if (!str_starts_with($file, 'assets/')) {
            $file = 'assets/' . $file;
        }

        // Relative URL so HTTPS pages never pull http:// assets (mixed content).
        $url = route('theme.asset', [
            'theme' => $themeName,
            'path' => $file,
        ], false);

        $path = self::resolveAssetDiskPath($file, $themeName);
        if ($path && is_file($path)) {
            $url .= (str_contains($url, '?') ? '&' : '?') . 'v=' . filemtime($path);
        }

        return $url;
    }

    /**
     * @return array{name: string, stylesheets?: list<string>, js?: string}
     */
    public static function assetVars(): array
    {
        $name = self::activeName();
        $vars = ['name' => $name];

        $stylesheets = [];
        $deferredStylesheets = [];
        foreach (['fonts.css', 'style.formated.css', 'new-dark.css', 'site.css', 'auth.css', 'theme-overrides.css'] as $file) {
            if (self::assetPath($file, $name)) {
                $stylesheets[] = self::assetUrl($file, $name);
            }
        }
        if (self::assetPath('font-awesome.min.css', $name)) {
            $deferredStylesheets[] = self::assetUrl('font-awesome.min.css', $name);
        }
        if ($stylesheets) {
            $vars['stylesheets'] = $stylesheets;
        }
        if ($deferredStylesheets) {
            $vars['deferred_stylesheets'] = $deferredStylesheets;
        }

        $fontPreloads = [];
        foreach (['fonts/open-sans-400-cyrillic.woff2', 'fonts/open-sans-400-latin.woff2'] as $font) {
            if (self::assetPath($font, $name)) {
                $fontPreloads[] = self::assetUrl($font, $name);
            }
        }
        if ($fontPreloads) {
            $vars['font_preloads'] = $fontPreloads;
        }

        if (self::assetPath('logo.svg', $name)) {
            $vars['logo'] = self::assetUrl('logo.svg', $name);
        }

        // Swiper-карусели главной — URL для ленивой подгрузки (не в общем layout).
        if (self::assetPath('home-carousels.js', $name)) {
            $vars['home_carousels_js'] = self::assetUrl('home-carousels.js', $name);
        }
        if (self::assetPath('home-carousels.css', $name)) {
            $vars['home_carousels_css'] = self::assetUrl('home-carousels.css', $name);
        }

        $scripts = [];
        if (self::assetPath('libs.js', $name)) {
            $scripts[] = self::assetUrl('libs.js', $name);
        }
        if (self::assetPath('site.js', $name)) {
            $scripts[] = self::assetUrl('site.js', $name);
        }
        if ($scripts) {
            $vars['scripts'] = $scripts;
            $vars['js'] = $scripts[array_key_last($scripts)];
        }

        return $vars;
    }

    private static function isValidThemeName(string $name): bool
    {
        return (bool)preg_match('/^[a-zA-Z0-9_-]+$/', $name);
    }
}
