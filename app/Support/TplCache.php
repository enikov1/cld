<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\Cache;

class TplCache
{
    private const REGISTRY_TTL = 604800;

    public static function seriesKey(int $seriesId, string $authKey): string
    {
        return 'tpl:series:' . $seriesId . ':auth:' . $authKey . ':gv:' . self::globalVersion();
    }

    public static function registryKey(int $seriesId): string
    {
        return 'tpl:series:keys:' . $seriesId;
    }

    /**
     * @template T
     * @param Closure(): T $callback
     * @return T
     */
    public static function rememberSeriesPage(int $seriesId, string $authKey, int $ttl, Closure $callback): mixed
    {
        $key = self::seriesKey($seriesId, $authKey);
        $html = Cache::remember($key, $ttl, $callback);
        self::registerSeriesKey($seriesId, $key);

        return $html;
    }

    public static function forgetSeries(int $seriesId): void
    {
        $registryKey = self::registryKey($seriesId);
        $keys = Cache::get($registryKey, []);

        if (is_array($keys)) {
            foreach ($keys as $key) {
                if (is_string($key) && $key !== '') {
                    Cache::forget($key);
                }
            }
        }

        Cache::forget($registryKey);
        Cache::forget(self::seriesKey($seriesId, 'guest'));
        self::forgetHome();
    }

    public static function forgetHome(): void
    {
        $version = (int)Cache::get('tpl:home:version', 0) + 1;
        Cache::forever('tpl:home:version', $version);
    }

    public static function homeVersion(): int
    {
        return (int)Cache::get('tpl:home:version', 0);
    }

    public static function globalVersion(): int
    {
        return (int)Cache::get('tpl:global:version', 0);
    }

    public static function bumpGlobalVersion(): void
    {
        Cache::forever('tpl:global:version', self::globalVersion() + 1);
        self::forgetHome();
    }

    private static function registerSeriesKey(int $seriesId, string $cacheKey): void
    {
        $registryKey = self::registryKey($seriesId);
        $keys = Cache::get($registryKey, []);

        if (!is_array($keys)) {
            $keys = [];
        }

        if (!in_array($cacheKey, $keys, true)) {
            $keys[] = $cacheKey;
            Cache::put($registryKey, $keys, self::REGISTRY_TTL);
        }
    }
}
