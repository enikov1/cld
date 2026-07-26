<?php

namespace App\Http\Controllers;

use App\Support\TplCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

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
            // Avoid SUM(LENGTH(value)) — full blob scan hangs on large stores.
            $entries = (int)DB::table($table)->count();
            $expired = (int)DB::table($table)
                ->where('expiration', '>', 0)
                ->where('expiration', '<', $now)
                ->count();
            $bytes = $this->databaseTableBytes($table);

            return [
                'driver' => 'database',
                'table' => $table,
                'entries' => $entries,
                'bytes' => $bytes,
                'bytes_human' => $bytes > 0 ? $this->humanBytes($bytes) : '—',
                'expired' => $expired,
                'note' => $bytes > 0 ? 'Размер приблизительный (по таблице)' : null,
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

    private function databaseTableBytes(string $table): int
    {
        $driver = DB::connection()->getDriverName();
        if (!in_array($driver, ['mysql', 'mariadb'], true)) {
            return 0;
        }

        $row = DB::selectOne(
            'SELECT COALESCE(data_length + index_length, 0) as size_bytes
             FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = ?
             LIMIT 1',
            [$table]
        );

        return (int)($row->size_bytes ?? 0);
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
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if (!$file->isFile()) {
                    continue;
                }
                $files++;
                $bytes += (int)$file->getSize();
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
