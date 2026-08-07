<?php

namespace App\Services;

use App\Models\SiteRedirect;
use App\Support\RedirectPath;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RedirectService
{
    private const CACHE_KEY = 'site_redirects:active_map';

    private const CACHE_TTL = 300;

    /**
     * @return array<string, array{id: int, to: string, code: int}>
     */
    public function activeMap(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            $items = SiteRedirect::query()
                ->with('series')
                ->where('is_active', true)
                ->get();

            $map = [];
            foreach ($items as $redirect) {
                $target = $redirect->resolveTargetPath();
                if ($target === null) {
                    continue;
                }

                $map[$redirect->from_path] = [
                    'id' => (int) $redirect->id,
                    'to' => $target,
                    'code' => (int) $redirect->status_code,
                ];
            }

            return $map;
        });
    }

    public function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array{id: int, to: string, code: int}|null
     */
    public function match(string $requestPath): ?array
    {
        $path = RedirectPath::requestPath($requestPath);
        $map = $this->activeMap();

        return $map[$path] ?? null;
    }

    public function recordHit(int $id): void
    {
        DB::table('redirects')->where('id', $id)->increment('hits_count');
    }
}
