<?php

namespace App\Http\Controllers;

use App\Support\TplCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class AdminCacheController extends Controller
{
    public function info(): JsonResponse
    {
        return response()->json(['ok' => true, 'cache' => $this->collectInfo()]);
    }

    public function clear(): JsonResponse
    {
        Cache::flush();
        Artisan::call('cache:clear');
        Artisan::call('view:clear');

        // Keep template versioning consistent after a full wipe.
        TplCache::bumpGlobalVersion();

        return response()->json([
            'ok' => true,
            'message' => 'Кэш очищен',
            'cache' => $this->collectInfo(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function collectInfo(): array
    {
        $driver = (string)config('cache.default', 'file');
        $store = $this->storeStats($driver);
        $views = $this->directoryStats(storage_path('framework/views'));
        $fileCache = $this->directoryStats(storage_path('framework/cache/data'));
        $configCache = $this->fileStats(base_path('bootstrap/cache/config.php'));
        $routesCache = $this->fileStats(base_path('bootstrap/cache/routes-v7.php'));
        if ($routesCache['exists'] === false) {
            $routesCache = $this->fileStats(base_path('bootstrap/cache/routes.php'));
        }

        $totalBytes = (int)($store['bytes'] ?? 0)
            + (int)($views['bytes'] ?? 0)
            + ($driver === 'file' ? 0 : (int)($fileCache['bytes'] ?? 0))
            + (int)($configCache['bytes'] ?? 0)
            + (int)($routesCache['bytes'] ?? 0);

        return [
            'driver' => $driver,
            'prefix' => (string)config('cache.prefix', ''),
            'total_bytes' => $totalBytes,
            'total_human' => $this->humanBytes($totalBytes),
            'store' => $store,
            'views' => $views,
            'file_cache' => $fileCache,
            'config_cache' => $configCache,
            'routes_cache' => $routesCache,
            'tpl' => [
                'global_version' => TplCache::globalVersion(),
                'home_version' => TplCache::homeVersion(),
            ],
            'collected_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function storeStats(string $driver): array
    {
        return match ($driver) {
            'database' => $this->databaseStoreStats(),
            'file' => $this->directoryStats(storage_path('framework/cache/data')),
            'redis', 'memcached', 'dynamodb', 'octane', 'array' => [
                'driver' => $driver,
                'entries' => null,
                'bytes' => 0,
                'bytes_human' => '—',
                'expired' => null,
                'note' => 'Для этого драйвера точный размер недоступен',
            ],
            default => [
                'driver' => $driver,
                'entries' => null,
                'bytes' => 0,
                'bytes_human' => '—',
                'expired' => null,
                'note' => 'Неизвестный драйвер кэша',
            ],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function databaseStoreStats(): array
    {
        $table = (string)config('cache.stores.database.table', 'cache');
        $now = time();

        try {
            $row = DB::table($table)
                ->selectRaw(
                    'COUNT(*) as entries,
                     COALESCE(SUM(LENGTH(`value`)), 0) as bytes,
                     SUM(CASE WHEN `expiration` > 0 AND `expiration` < ? THEN 1 ELSE 0 END) as expired',
                    [$now]
                )
                ->first();

            $entries = (int)($row->entries ?? 0);
            $bytes = (int)($row->bytes ?? 0);
            $expired = (int)($row->expired ?? 0);

            return [
                'driver' => 'database',
                'table' => $table,
                'entries' => $entries,
                'bytes' => $bytes,
                'bytes_human' => $this->humanBytes($bytes),
                'expired' => $expired,
                'note' => null,
            ];
        } catch (\Throwable $e) {
            return [
                'driver' => 'database',
                'table' => $table,
                'entries' => null,
                'bytes' => 0,
                'bytes_human' => '—',
                'expired' => null,
                'note' => 'Не удалось прочитать таблицу кэша: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function directoryStats(string $path): array
    {
        if (!is_dir($path)) {
            return [
                'path' => $this->relativePath($path),
                'exists' => false,
                'files' => 0,
                'bytes' => 0,
                'bytes_human' => '0 B',
            ];
        }

        $bytes = 0;
        $files = 0;

        try {
            foreach (File::allFiles($path) as $file) {
                $files++;
                $bytes += $file->getSize();
            }
        } catch (\Throwable) {
            // ignore unreadable dirs
        }

        return [
            'path' => $this->relativePath($path),
            'exists' => true,
            'files' => $files,
            'bytes' => $bytes,
            'bytes_human' => $this->humanBytes($bytes),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fileStats(string $path): array
    {
        if (!is_file($path)) {
            return [
                'path' => $this->relativePath($path),
                'exists' => false,
                'bytes' => 0,
                'bytes_human' => '0 B',
            ];
        }

        $bytes = (int)filesize($path);

        return [
            'path' => $this->relativePath($path),
            'exists' => true,
            'bytes' => $bytes,
            'bytes_human' => $this->humanBytes($bytes),
        ];
    }

    private function relativePath(string $path): string
    {
        $base = str_replace('\\', '/', base_path());
        $normalized = str_replace('\\', '/', $path);
        if (str_starts_with($normalized, $base)) {
            return ltrim(substr($normalized, strlen($base)), '/');
        }

        return $normalized;
    }

    private function humanBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        $units = ['KB', 'MB', 'GB', 'TB'];
        $value = (float)$bytes;
        foreach ($units as $unit) {
            $value /= 1024;
            if ($value < 1024) {
                return rtrim(rtrim(number_format($value, $value >= 10 ? 1 : 2, '.', ''), '0'), '.') . ' ' . $unit;
            }
        }

        return number_format($value, 1, '.', '') . ' PB';
    }
}
