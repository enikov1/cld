<?php

namespace App\Support;

class RedirectPath
{
    public static function normalizeFrom(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '/';
        }

        if (preg_match('#^https?://#i', $path)) {
            $parsed = parse_url($path);
            $path = (string) ($parsed['path'] ?? '/');
        }

        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }

        $path = preg_replace('#/+#', '/', $path) ?: '/';

        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        return $path;
    }

    public static function normalizeTo(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '/';
        }

        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        return self::normalizeFrom($path);
    }

    public static function requestPath(string $pathInfo): string
    {
        return self::normalizeFrom($pathInfo !== '' ? $pathInfo : '/');
    }
}
