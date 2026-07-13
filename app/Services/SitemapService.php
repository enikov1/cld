<?php

namespace App\Services;

use App\Models\Collection;
use App\Models\Country;
use App\Models\Genre;
use App\Models\Person;
use App\Models\Series;
use App\Models\Studio;
use App\Models\Year;
use App\Support\SeriesUrl;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

class SitemapService
{
    private const DIRTY_CACHE_KEY = 'sitemap.dirty';

    public function path(): string
    {
        return public_path('sitemap.xml');
    }

    public function markDirty(): void
    {
        Cache::put(self::DIRTY_CACHE_KEY, true, now()->addDay());
    }

    public function isDirty(): bool
    {
        return (bool) Cache::get(self::DIRTY_CACHE_KEY, false);
    }

    public function clearDirty(): void
    {
        Cache::forget(self::DIRTY_CACHE_KEY);
    }

    public function isStale(?int $maxAgeMinutes = null): bool
    {
        $path = $this->path();
        if (!is_file($path)) {
            return true;
        }

        $maxAgeMinutes ??= (int) config('sitemap.max_age_minutes', 60);

        return filemtime($path) < now()->subMinutes($maxAgeMinutes)->getTimestamp();
    }

    public function shouldRegenerate(?int $maxAgeMinutes = null): bool
    {
        return $this->isDirty() || $this->isStale($maxAgeMinutes);
    }

    public function ensureFresh(?int $maxAgeMinutes = null): bool
    {
        if (!$this->shouldRegenerate($maxAgeMinutes)) {
            return false;
        }

        return $this->generate();
    }

    public function generate(): bool
    {
        $base = rtrim(config('app.url'), '/');
        $urls = [];
        $now = now()->toAtomString();

        $urls[] = ['loc' => $base . '/', 'priority' => '1.0', 'lastmod' => $now];
        $urls[] = ['loc' => $base . '/catalog/', 'priority' => '0.9', 'lastmod' => $now];
        $urls[] = ['loc' => $base . '/collections/', 'priority' => '0.8', 'lastmod' => $now];
        $urls[] = ['loc' => $base . '/studios/', 'priority' => '0.8', 'lastmod' => $now];

        foreach (Genre::query()->where('is_active', true)->where('is_hidden', false)->where('noindex', false)->orderBy('sort_order')->get() as $item) {
            $urls[] = [
                'loc' => $base . '/genre/' . $item->slug . '/',
                'priority' => '0.7',
                'lastmod' => optional($item->updated_at)->toAtomString() ?? $now,
            ];
        }

        foreach (Country::query()->where('is_active', true)->where('is_hidden', false)->where('noindex', false)->orderBy('sort_order')->get() as $item) {
            $urls[] = [
                'loc' => $base . '/country/' . $item->slug . '/',
                'priority' => '0.7',
                'lastmod' => optional($item->updated_at)->toAtomString() ?? $now,
            ];
        }

        foreach (Person::query()->where('is_active', true)->where('is_hidden', false)->where('noindex', false)->orderBy('sort_order')->get() as $item) {
            $urls[] = [
                'loc' => $base . '/person/' . $item->slug . '/',
                'priority' => '0.6',
                'lastmod' => optional($item->updated_at)->toAtomString() ?? $now,
            ];
        }

        foreach (Year::query()->where('is_active', true)->where('is_hidden', false)->where('noindex', false)->orderByDesc('sort_order')->get() as $item) {
            $urls[] = [
                'loc' => $base . '/year/' . $item->slug . '/',
                'priority' => '0.6',
                'lastmod' => optional($item->updated_at)->toAtomString() ?? $now,
            ];
        }

        foreach (Collection::query()->where('is_active', true)->where('is_hidden', false)->where('noindex', false)->orderBy('id')->get() as $col) {
            $urls[] = [
                'loc' => $base . '/collections/' . $col->slug . '/',
                'priority' => '0.7',
                'lastmod' => optional($col->updated_at)->toAtomString() ?? $now,
            ];
        }

        foreach (Studio::query()->where('is_active', true)->where('is_hidden', false)->where('noindex', false)->orderBy('id')->get() as $studio) {
            $urls[] = [
                'loc' => $base . '/studios/' . $studio->slug . '/',
                'priority' => '0.7',
                'lastmod' => optional($studio->updated_at)->toAtomString() ?? $now,
            ];
        }

        foreach (Series::query()->where('is_active', true)->where('is_hidden', false)->where('noindex', false)->orderBy('id')->get() as $series) {
            $urls[] = [
                'loc' => $base . SeriesUrl::path($series),
                'priority' => '0.8',
                'lastmod' => optional($series->updated_at)->toAtomString() ?? $now,
            ];
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($urls as $url) {
            $xml .= "  <url>\n";
            $xml .= '    <loc>' . htmlspecialchars($url['loc'], ENT_XML1) . "</loc>\n";
            if (!empty($url['lastmod'])) {
                $xml .= '    <lastmod>' . htmlspecialchars($url['lastmod'], ENT_XML1) . "</lastmod>\n";
            }
            $xml .= '    <priority>' . $url['priority'] . "</priority>\n";
            $xml .= "  </url>\n";
        }

        $xml .= "</urlset>\n";

        $this->writeXml($xml);

        $this->clearDirty();

        return true;
    }

    private function writeXml(string $xml): void
    {
        $path = $this->path();
        $directory = dirname($path);

        if (!is_dir($directory)) {
            throw new \RuntimeException('Каталог public/ не найден.');
        }

        if (!is_writable($directory) && !(is_file($path) && is_writable($path))) {
            throw new \RuntimeException(
                'Нет прав на запись sitemap.xml в public/. '
                . 'На сервере выполните: chgrp www-data public && chmod 775 public'
            );
        }

        $tempPath = $path . '.tmp.' . getmypid();
        if (file_put_contents($tempPath, $xml) === false) {
            throw new \RuntimeException('Не удалось записать временный файл sitemap.xml.');
        }

        if (!@rename($tempPath, $path)) {
            @unlink($tempPath);
            throw new \RuntimeException('Не удалось сохранить public/sitemap.xml.');
        }

        $this->writeLegacyMirror($xml);
    }

    /**
     * Optional mirror for local layouts (e.g. OSPanel). Must never break generation on VPS.
     */
    private function writeLegacyMirror(string $xml): void
    {
        $rootPath = base_path('../sitemap.xml');
        $parentDir = dirname($rootPath);
        $projectDir = realpath(base_path()) ?: base_path();

        if ($parentDir === $projectDir || !is_dir($parentDir) || !is_writable($parentDir)) {
            return;
        }

        try {
            File::put($rootPath, $xml);
        } catch (\Throwable) {
            // Ignore — main sitemap in public/ is already written.
        }
    }

    public function urlCount(): int
    {
        $path = $this->path();
        if (!is_file($path)) {
            return 0;
        }

        return substr_count(file_get_contents($path) ?: '', '<url>');
    }
}
