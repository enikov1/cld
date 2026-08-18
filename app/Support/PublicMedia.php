<?php

namespace App\Support;

class PublicMedia
{
    public const PREFIX = '/media/';

    private const IMAGE_EXT = 'jpe?g|png|gif|webp|ico|svg';

    /** @var array<string, int> */
    private static array $mtimeCache = [];

    public static function url(?string $url): string
    {
        $url = trim((string) $url);
        if ($url === '' || !str_starts_with($url, '/storage/')) {
            return $url;
        }

        $relative = ltrim(substr($url, strlen('/storage/')), '/');
        if ($relative === '' || str_contains($relative, '..') || !self::isAllowedRelative($relative)) {
            return $url;
        }

        $out = self::PREFIX . $relative;
        $mtime = self::mtime($relative);

        return $mtime > 0 ? $out . '?v=' . $mtime : $out;
    }

    public static function rewriteInText(string $text): string
    {
        if (!str_contains($text, '/storage/')) {
            return $text;
        }

        $rewritten = preg_replace_callback(
            '#((?:https?://[^/\s"\']+)?)(/storage/([a-zA-Z0-9_./-]+\.(?:' . self::IMAGE_EXT . ')))#i',
            static function (array $m): string {
                return $m[1] . self::url($m[2]);
            },
            $text
        );

        return is_string($rewritten) ? $rewritten : $text;
    }

    public static function resolveDiskPath(string $relative): ?string
    {
        $relative = ltrim(str_replace('\\', '/', $relative), '/');
        if ($relative === '' || str_contains($relative, '..') || !self::isAllowedRelative($relative)) {
            return null;
        }

        $root = realpath(storage_path('app/public'));
        if ($root === false) {
            return null;
        }

        $full = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        $real = realpath($full);
        if ($real === false || !is_file($real) || !str_starts_with($real, $root . DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $real;
    }

    private static function isAllowedRelative(string $relative): bool
    {
        return (bool) preg_match('#^[a-zA-Z0-9_./-]+\.(?:' . self::IMAGE_EXT . ')$#i', $relative);
    }

    private static function mtime(string $relative): int
    {
        if (!array_key_exists($relative, self::$mtimeCache)) {
            $path = storage_path('app/public/' . str_replace('/', DIRECTORY_SEPARATOR, $relative));
            self::$mtimeCache[$relative] = is_file($path) ? (int) filemtime($path) : 0;
        }

        return self::$mtimeCache[$relative];
    }
}
