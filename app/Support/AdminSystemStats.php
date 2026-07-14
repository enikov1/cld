<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class AdminSystemStats
{
    /**
     * @return array<string, mixed>
     */
    public static function collect(): array
    {
        return [
            'database' => self::database(),
            'disk' => self::disk(),
            'memory' => self::memory(),
            'cpu' => self::cpu(),
            'php' => self::php(),
            'os' => self::os(),
            'collected_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function database(): array
    {
        $connection = (string)config('database.default', 'mysql');
        $driver = (string)config("database.connections.{$connection}.driver", '');
        $database = (string)config("database.connections.{$connection}.database", '');

        $result = [
            'connection' => $connection,
            'driver' => $driver,
            'name' => $database,
            'version' => null,
            'size_bytes' => null,
            'size_human' => '—',
            'tables' => null,
            'largest_tables' => [],
            'note' => null,
        ];

        try {
            $pdo = DB::connection()->getPdo();
            $result['version'] = (string)($pdo->getAttribute(\PDO::ATTR_SERVER_VERSION) ?: '');

            if (in_array($driver, ['mysql', 'mariadb'], true)) {
                $sizeRow = DB::selectOne(
                    'SELECT
                        COUNT(*) as tables_count,
                        COALESCE(SUM(data_length + index_length), 0) as size_bytes
                     FROM information_schema.tables
                     WHERE table_schema = DATABASE()'
                );

                $sizeBytes = (int)($sizeRow->size_bytes ?? 0);
                $result['tables'] = (int)($sizeRow->tables_count ?? 0);
                $result['size_bytes'] = $sizeBytes;
                $result['size_human'] = self::humanBytes($sizeBytes);

                $largest = DB::select(
                    'SELECT
                        table_name as name,
                        COALESCE(table_rows, 0) as rows_estimate,
                        COALESCE(data_length + index_length, 0) as size_bytes
                     FROM information_schema.tables
                     WHERE table_schema = DATABASE()
                     ORDER BY (data_length + index_length) DESC
                     LIMIT 8'
                );

                $result['largest_tables'] = array_map(static function ($row) {
                    $bytes = (int)($row->size_bytes ?? 0);

                    return [
                        'name' => (string)($row->name ?? ''),
                        'rows' => (int)($row->rows_estimate ?? 0),
                        'size_bytes' => $bytes,
                        'size_human' => self::humanBytes($bytes),
                    ];
                }, $largest);
            } elseif ($driver === 'sqlite') {
                $path = $database;
                if ($path !== '' && is_file($path)) {
                    $bytes = (int)filesize($path);
                    $result['size_bytes'] = $bytes;
                    $result['size_human'] = self::humanBytes($bytes);
                }
                $result['tables'] = (int)DB::selectOne(
                    "SELECT COUNT(*) as c FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'"
                )?->c;
            } else {
                $result['note'] = 'Размер БД для драйвера ' . $driver . ' не поддерживается';
            }
        } catch (\Throwable $e) {
            $result['note'] = 'Не удалось прочитать БД: ' . $e->getMessage();
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private static function disk(): array
    {
        $path = base_path();
        $total = @disk_total_space($path);
        $free = @disk_free_space($path);

        if ($total === false || $free === false || $total <= 0) {
            return [
                'path' => $path,
                'total_bytes' => null,
                'free_bytes' => null,
                'used_bytes' => null,
                'total_human' => '—',
                'free_human' => '—',
                'used_human' => '—',
                'used_percent' => null,
                'note' => 'Не удалось прочитать диск',
            ];
        }

        $totalBytes = (int)$total;
        $freeBytes = (int)$free;
        $usedBytes = max(0, $totalBytes - $freeBytes);
        $usedPercent = $totalBytes > 0 ? round(($usedBytes / $totalBytes) * 100, 1) : null;

        return [
            'path' => $path,
            'mount' => self::diskMountLabel($path),
            'total_bytes' => $totalBytes,
            'free_bytes' => $freeBytes,
            'used_bytes' => $usedBytes,
            'total_human' => self::humanBytes($totalBytes),
            'free_human' => self::humanBytes($freeBytes),
            'used_human' => self::humanBytes($usedBytes),
            'used_percent' => $usedPercent,
            'note' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function memory(): array
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return self::memoryWindows();
        }

        return self::memoryLinux();
    }

    /**
     * @return array<string, mixed>
     */
    private static function memoryLinux(): array
    {
        $info = [
            'total_bytes' => null,
            'available_bytes' => null,
            'used_bytes' => null,
            'total_human' => '—',
            'available_human' => '—',
            'used_human' => '—',
            'used_percent' => null,
            'note' => null,
        ];

        $path = '/proc/meminfo';
        if (!is_readable($path)) {
            $info['note'] = 'Нет доступа к /proc/meminfo';

            return $info;
        }

        $raw = @file_get_contents($path);
        if ($raw === false) {
            $info['note'] = 'Не удалось прочитать /proc/meminfo';

            return $info;
        }

        $kb = [];
        foreach (explode("\n", $raw) as $line) {
            if (preg_match('/^(\w+):\s+(\d+)/', $line, $m)) {
                $kb[$m[1]] = (int)$m[2];
            }
        }

        $totalKb = $kb['MemTotal'] ?? 0;
        $availableKb = $kb['MemAvailable'] ?? (($kb['MemFree'] ?? 0) + ($kb['Buffers'] ?? 0) + ($kb['Cached'] ?? 0));
        if ($totalKb <= 0) {
            $info['note'] = 'Некорректные данные памяти';

            return $info;
        }

        $totalBytes = $totalKb * 1024;
        $availableBytes = max(0, $availableKb * 1024);
        $usedBytes = max(0, $totalBytes - $availableBytes);

        return [
            'total_bytes' => $totalBytes,
            'available_bytes' => $availableBytes,
            'used_bytes' => $usedBytes,
            'total_human' => self::humanBytes($totalBytes),
            'available_human' => self::humanBytes($availableBytes),
            'used_human' => self::humanBytes($usedBytes),
            'used_percent' => round(($usedBytes / $totalBytes) * 100, 1),
            'note' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function memoryWindows(): array
    {
        $info = [
            'total_bytes' => null,
            'available_bytes' => null,
            'used_bytes' => null,
            'total_human' => '—',
            'available_human' => '—',
            'used_human' => '—',
            'used_percent' => null,
            'note' => null,
        ];

        $output = self::shellOutput('wmic OS get FreePhysicalMemory,TotalVisibleMemorySize /Value');
        if ($output === null) {
            $info['note'] = 'Не удалось получить данные RAM (wmic)';

            return $info;
        }

        $totalKb = 0;
        $freeKb = 0;
        if (preg_match('/TotalVisibleMemorySize=(\d+)/i', $output, $m)) {
            $totalKb = (int)$m[1];
        }
        if (preg_match('/FreePhysicalMemory=(\d+)/i', $output, $m)) {
            $freeKb = (int)$m[1];
        }

        if ($totalKb <= 0) {
            $info['note'] = 'Некорректные данные RAM';

            return $info;
        }

        $totalBytes = $totalKb * 1024;
        $availableBytes = $freeKb * 1024;
        $usedBytes = max(0, $totalBytes - $availableBytes);

        return [
            'total_bytes' => $totalBytes,
            'available_bytes' => $availableBytes,
            'used_bytes' => $usedBytes,
            'total_human' => self::humanBytes($totalBytes),
            'available_human' => self::humanBytes($availableBytes),
            'used_human' => self::humanBytes($usedBytes),
            'used_percent' => round(($usedBytes / $totalBytes) * 100, 1),
            'note' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function cpu(): array
    {
        $cores = self::cpuCores();
        $load = self::loadAverage();

        return [
            'cores' => $cores,
            'load_1' => $load[0] ?? null,
            'load_5' => $load[1] ?? null,
            'load_15' => $load[2] ?? null,
            'load_human' => self::formatLoad($load),
            'note' => $load === null && PHP_OS_FAMILY === 'Windows'
                ? 'Load average недоступен на Windows'
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function php(): array
    {
        $limit = self::parseIniBytes((string)ini_get('memory_limit'));
        $usage = memory_get_usage(true);
        $peak = memory_get_peak_usage(true);

        return [
            'version' => PHP_VERSION,
            'sapi' => PHP_SAPI,
            'memory_limit' => (string)ini_get('memory_limit'),
            'memory_limit_bytes' => $limit,
            'memory_usage_bytes' => $usage,
            'memory_usage_human' => self::humanBytes($usage),
            'memory_peak_bytes' => $peak,
            'memory_peak_human' => self::humanBytes($peak),
            'laravel' => app()->version(),
            'timezone' => (string)config('app.timezone', date_default_timezone_get()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function os(): array
    {
        return [
            'family' => PHP_OS_FAMILY,
            'name' => PHP_OS,
            'hostname' => gethostname() ?: null,
            'uname' => php_uname(),
            'uptime_human' => self::uptimeHuman(),
        ];
    }

    private static function cpuCores(): ?int
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $env = getenv('NUMBER_OF_PROCESSORS');
            if ($env !== false && (int)$env > 0) {
                return (int)$env;
            }

            $output = self::shellOutput('wmic cpu get NumberOfLogicalProcessors /Value');
            if ($output && preg_match('/NumberOfLogicalProcessors=(\d+)/i', $output, $m)) {
                return (int)$m[1];
            }

            return null;
        }

        if (is_readable('/proc/cpuinfo')) {
            $raw = @file_get_contents('/proc/cpuinfo');
            if ($raw !== false) {
                $count = preg_match_all('/^processor\s*:/m', $raw);
                if ($count > 0) {
                    return $count;
                }
            }
        }

        if (function_exists('shell_exec')) {
            $nproc = @shell_exec('nproc 2>/dev/null');
            if (is_string($nproc) && (int)trim($nproc) > 0) {
                return (int)trim($nproc);
            }
        }

        return null;
    }

    /**
     * @return array{0?: float, 1?: float, 2?: float}|null
     */
    private static function loadAverage(): ?array
    {
        if (function_exists('sys_getloadavg')) {
            $load = @sys_getloadavg();
            if (is_array($load) && isset($load[0])) {
                return [
                    round((float)$load[0], 2),
                    round((float)($load[1] ?? 0), 2),
                    round((float)($load[2] ?? 0), 2),
                ];
            }
        }

        if (is_readable('/proc/loadavg')) {
            $raw = @file_get_contents('/proc/loadavg');
            if (is_string($raw) && preg_match('/^([\d.]+)\s+([\d.]+)\s+([\d.]+)/', $raw, $m)) {
                return [
                    round((float)$m[1], 2),
                    round((float)$m[2], 2),
                    round((float)$m[3], 2),
                ];
            }
        }

        return null;
    }

    /**
     * @param  array{0?: float, 1?: float, 2?: float}|null  $load
     */
    private static function formatLoad(?array $load): string
    {
        if ($load === null || !isset($load[0])) {
            return '—';
        }

        return ($load[0] ?? 0) . ' / ' . ($load[1] ?? 0) . ' / ' . ($load[2] ?? 0);
    }

    private static function uptimeHuman(): ?string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $output = self::shellOutput('wmic os get LastBootUpTime /Value');
            if ($output && preg_match('/LastBootUpTime=(\d{14})/', $output, $m)) {
                try {
                    $boot = \DateTimeImmutable::createFromFormat('YmdHis', substr($m[1], 0, 14));
                    if ($boot) {
                        return self::formatDuration(time() - $boot->getTimestamp());
                    }
                } catch (\Throwable) {
                    // ignore
                }
            }

            return null;
        }

        if (is_readable('/proc/uptime')) {
            $raw = @file_get_contents('/proc/uptime');
            if (is_string($raw)) {
                $parts = explode(' ', trim($raw));
                $seconds = (int)floatval($parts[0] ?? 0);
                if ($seconds > 0) {
                    return self::formatDuration($seconds);
                }
            }
        }

        return null;
    }

    private static function formatDuration(int $seconds): string
    {
        $seconds = max(0, $seconds);
        $days = intdiv($seconds, 86400);
        $hours = intdiv($seconds % 86400, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        $parts = [];
        if ($days > 0) {
            $parts[] = $days . 'д';
        }
        if ($hours > 0 || $days > 0) {
            $parts[] = $hours . 'ч';
        }
        $parts[] = $minutes . 'м';

        return implode(' ', $parts);
    }

    private static function diskMountLabel(string $path): string
    {
        $normalized = str_replace('\\', '/', $path);
        if (PHP_OS_FAMILY === 'Windows' && preg_match('#^([A-Za-z]:)#', $normalized, $m)) {
            return strtoupper($m[1]) . '\\';
        }

        return '/';
    }

    private static function parseIniBytes(string $value): ?int
    {
        $value = trim($value);
        if ($value === '' || $value === '-1') {
            return null;
        }

        if (!preg_match('/^(\d+)\s*([KMG])?B?$/i', $value, $m)) {
            if (ctype_digit($value)) {
                return (int)$value;
            }

            return null;
        }

        $n = (int)$m[1];
        $unit = strtoupper($m[2] ?? '');

        return match ($unit) {
            'K' => $n * 1024,
            'M' => $n * 1024 * 1024,
            'G' => $n * 1024 * 1024 * 1024,
            default => $n,
        };
    }

    private static function shellOutput(string $command): ?string
    {
        if (!function_exists('shell_exec')) {
            return null;
        }

        try {
            $out = @shell_exec($command);
            if (!is_string($out) || trim($out) === '') {
                return null;
            }

            return $out;
        } catch (\Throwable) {
            return null;
        }
    }

    private static function humanBytes(int $bytes): string
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
