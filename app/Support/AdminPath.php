<?php

namespace App\Support;

use App\Models\SiteSetting;

class AdminPath
{
    public const SETTING_KEY = 'admin_path';

    public const DEFAULT = 'admin';

    public static function path(): string
    {
        try {
            $fromSettings = trim((string)SiteSetting::get(self::SETTING_KEY, ''));
            if ($fromSettings !== '') {
                return self::normalize($fromSettings);
            }
        } catch (\Throwable) {
            // DB may be unavailable during install.
        }

        $fromEnv = trim((string)config('admin.path', ''));
        if ($fromEnv !== '') {
            return self::normalize($fromEnv);
        }

        return self::DEFAULT;
    }

    public static function base(): string
    {
        return '/' . self::path();
    }

    public static function normalize(string $path): string
    {
        $path = strtolower(trim($path, '/'));

        return $path === '' ? self::DEFAULT : $path;
    }

    /**
     * @return list<string>
     */
    public static function reservedSlugs(): array
    {
        $static = ReservedPaths::slugs();

        return array_values(array_unique($static));
    }

    public static function validate(string $path): ?string
    {
        $path = self::normalize($path);

        if (!preg_match('/^[a-z0-9][a-z0-9\-]{0,48}[a-z0-9]$|^[a-z0-9]$/', $path)) {
            return 'URL админки: только латиница, цифры и дефис (2–50 символов, без слэшей).';
        }

        $current = self::path();
        if ($path !== $current && in_array($path, ReservedPaths::slugs(), true)) {
            return 'Этот путь зарезервирован системой.';
        }

        return null;
    }
}
