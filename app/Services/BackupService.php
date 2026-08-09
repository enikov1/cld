<?php

namespace App\Services;

use App\Models\CronRun;
use App\Support\TplCache;
use App\Support\Utf8;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Process\Process;
use ZipArchive;

class BackupService
{
    private const ALREADY_COMPRESSED = [
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'zip', 'gz', 'bz2', '7z',
        'mp4', 'webm', 'mp3', 'ogg', 'woff', 'woff2',
    ];

    public function __construct(
        private readonly BackupRemoteStorage $remoteStorage,
    ) {}

    /**
     * @return array{
     *     status: string,
     *     counts?: array<string, int|float>,
     *     message?: string,
     *     error?: string,
     *     log?: list<string>
     * }
     */
    public function run(): array
    {
        $this->prepareLongRunningProcess();

        $settings = BackupSettings::get();
        $log = [];

        if (!$settings['include_database'] && !$settings['include_files']) {
            return [
                'status' => CronRun::STATUS_SKIPPED,
                'message' => 'Бэкап пропущен: не выбрано ни БД, ни файлы сайта.',
            ];
        }

        $this->ensureBackupDirectory();
        $lock = $this->acquireLock('.run.lock', 'Бэкап уже выполняется. Дождитесь завершения.');
        $workDir = storage_path('app/backups/tmp_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)));
        File::makeDirectory($workDir, 0755, true);

        $archiveName = 'backup_' . date('Y-m-d_H-i-s') . '.zip';
        $archivePath = storage_path('app/backups/' . $archiveName);
        $archiveSize = 0;
        $uploaded = false;

        try {
            if ($settings['include_database']) {
                $log[] = 'Дамп базы данных…';
                $this->dumpDatabase($workDir);
            }

            $log[] = 'Создание ZIP-архива…';
            $this->createZipArchive($workDir, $settings['include_files'], $archivePath);
            $archiveSize = is_file($archivePath) ? (int)filesize($archivePath) : 0;
            $log[] = 'Архив: ' . $archiveName . ' (' . $this->formatBytes($archiveSize) . ')';

            if ($settings['remote_enabled']) {
                $log[] = 'Загрузка на удалённый сервер (' . strtoupper($settings['protocol']) . ')…';
                $this->remoteStorage->upload($archivePath, $archiveName);
                $uploaded = true;
                $deletedRemote = $this->remoteStorage->prune($settings['retention_count']);
                if ($deletedRemote > 0) {
                    $log[] = 'Удалено старых копий на сервере: ' . $deletedRemote;
                }
            }

            $deletedLocal = $this->pruneLocal($settings['retention_count']);
            if ($deletedLocal > 0) {
                $log[] = 'Удалено старых локальных копий: ' . $deletedLocal;
            }

            BackupSettings::markRun();

            $parts = [];
            if ($settings['include_database']) {
                $parts[] = 'БД';
            }
            if ($settings['include_files']) {
                $parts[] = 'файлы';
            }
            $message = 'Бэкап (' . implode(' + ', $parts) . '): ' . $archiveName;
            if ($uploaded) {
                $message .= ' → удалённый сервер';
            }

            return [
                'status' => CronRun::STATUS_SUCCESS,
                'counts' => [
                    'size_bytes' => $archiveSize,
                    'uploaded' => $uploaded ? 1 : 0,
                ],
                'message' => $message,
                'log' => $log,
            ];
        } catch (\Throwable $e) {
            if (is_file($archivePath)) {
                @unlink($archivePath);
            }

            return [
                'status' => CronRun::STATUS_FAILED,
                'message' => 'Ошибка бэкапа',
                'error' => (string)Utf8::sanitize($e->getMessage()),
                'log' => Utf8::sanitizeLines($log),
            ];
        } finally {
            File::deleteDirectory($workDir);
            $this->releaseLock($lock);
        }
    }

    /**
     * Copy an arbitrary ZIP into storage/app/backups with a valid backup_*.zip name.
     */
    public function importArchiveFile(string $path): string
    {
        $path = trim($path);
        if ($path === '' || !is_file($path)) {
            throw new RuntimeException('Файл бэкапа не найден: ' . $path);
        }

        $basename = basename($path);
        $this->ensureBackupDirectory();

        if (preg_match('/^backup_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.zip$/', $basename)) {
            $target = storage_path('app/backups/' . $basename);
            if (realpath($path) !== realpath($target)) {
                if (!copy($path, $target)) {
                    throw new RuntimeException('Не удалось скопировать архив в storage/app/backups.');
                }
            }

            return $basename;
        }

        $name = 'backup_' . date('Y-m-d_H-i-s') . '.zip';
        $target = storage_path('app/backups/' . $name);
        if (!copy($path, $target)) {
            throw new RuntimeException('Не удалось скопировать архив в storage/app/backups.');
        }

        return $name;
    }

    /**
     * @return list<array{name: string, size: int, created_at: string}>
     */
    public function listLocalBackups(): array
    {
        $this->ensureBackupDirectory();
        $items = [];

        foreach (glob(storage_path('app/backups/backup_*.zip')) ?: [] as $path) {
            $items[] = [
                'name' => basename($path),
                'size' => (int)filesize($path),
                'created_at' => date('c', (int)filemtime($path)),
            ];
        }

        usort($items, static fn (array $a, array $b) => strcmp($b['name'], $a['name']));

        return $items;
    }

    /**
     * @return list<array{name: string, size: int, created_at: string, source: string}>
     */
    public function listAvailableBackups(): array
    {
        $items = [];
        $seen = [];

        foreach ($this->listLocalBackups() as $backup) {
            $seen[$backup['name']] = true;
            $items[] = array_merge($backup, ['source' => 'local']);
        }

        if (BackupSettings::isRemoteConfigured()) {
            try {
                foreach ($this->remoteStorage->listBackupFilesWithMeta() as $backup) {
                    if (isset($seen[$backup['name']])) {
                        continue;
                    }
                    $items[] = [
                        'name' => $backup['name'],
                        'size' => (int)($backup['size'] ?? 0),
                        'created_at' => $backup['created_at'] ?? $this->parseBackupTimestamp($backup['name']),
                        'source' => 'remote',
                    ];
                }
            } catch (\Throwable) {
                // Remote listing is optional for the page.
            }
        }

        usort($items, static fn (array $a, array $b) => strcmp($b['name'], $a['name']));

        return $items;
    }

    /**
     * @return array{
     *     status: string,
     *     counts?: array<string, int>,
     *     message?: string,
     *     error?: string,
     *     log?: list<string>
     * }
     */
    public function restore(string $name, string $source, bool $restoreDatabase = true, bool $restoreFiles = true): array
    {
        $this->prepareLongRunningProcess();

        $log = [];

        if (!$restoreDatabase && !$restoreFiles) {
            return [
                'status' => CronRun::STATUS_SKIPPED,
                'message' => 'Восстановление пропущено: не выбрано ни БД, ни файлы.',
            ];
        }

        $this->assertValidBackupName($name);
        if (!in_array($source, ['local', 'remote'], true)) {
            throw new RuntimeException('Некорректный источник бэкапа.');
        }

        $this->ensureBackupDirectory();
        $lock = $this->acquireLock('.restore.lock', 'Восстановление уже выполняется. Дождитесь завершения.');

        $archivePath = null;
        $downloaded = false;
        $workDir = storage_path('app/backups/restore_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)));

        try {
            [$archivePath, $downloaded] = $this->resolveArchivePath($name, $source);
            $log[] = $source === 'remote'
                ? 'Скачан архив с удалённого сервера: ' . $name
                : 'Локальный архив: ' . $name;

            File::makeDirectory($workDir, 0755, true);
            $log[] = 'Распаковка архива…';
            $this->extractArchive($archivePath, $workDir);

            $restoredDb = false;
            $restoredFiles = false;

            if ($restoreDatabase) {
                $log[] = 'Восстановление базы данных…';
                $this->restoreDatabaseFromExtract($workDir);
                $restoredDb = true;
            }

            if ($restoreFiles) {
                $log[] = 'Восстановление файлов сайта…';
                $this->restoreSiteFilesFromExtract($workDir);
                TplCache::bumpGlobalVersion();
                $restoredFiles = true;
            }

            $parts = [];
            if ($restoredDb) {
                $parts[] = 'БД';
            }
            if ($restoredFiles) {
                $parts[] = 'файлы';
            }

            return [
                'status' => CronRun::STATUS_SUCCESS,
                'counts' => [
                    'database' => $restoredDb ? 1 : 0,
                    'files' => $restoredFiles ? 1 : 0,
                ],
                'message' => 'Восстановлено из ' . $name . ' (' . implode(' + ', $parts) . ')',
                'log' => $log,
            ];
        } catch (\Throwable $e) {
            return [
                'status' => CronRun::STATUS_FAILED,
                'message' => 'Ошибка восстановления',
                'error' => (string)Utf8::sanitize($e->getMessage()),
                'log' => Utf8::sanitizeLines($log),
            ];
        } finally {
            if ($downloaded && is_string($archivePath) && is_file($archivePath)) {
                @unlink($archivePath);
            }
            if (is_dir($workDir)) {
                File::deleteDirectory($workDir);
            }
            $this->releaseLock($lock);
        }
    }

    private function prepareLongRunningProcess(): void
    {
        @ignore_user_abort(true);
        @set_time_limit(0);
        @ini_set('max_execution_time', '0');
        // Large ZIP builds should stream from disk; keep a modest ceiling for PHP overhead.
        $current = ini_get('memory_limit');
        if (is_string($current) && $current !== '' && $current !== '-1') {
            $bytes = $this->memoryLimitToBytes($current);
            if ($bytes > 0 && $bytes < 512 * 1024 * 1024) {
                @ini_set('memory_limit', '512M');
            }
        }
    }

    private function memoryLimitToBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '' || $value === '-1') {
            return -1;
        }

        $unit = strtolower(substr($value, -1));
        $number = (float)$value;
        return (int) match ($unit) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => $number,
        };
    }

    /**
     * @return array{handle: resource, path: string}
     */
    private function acquireLock(string $filename, string $busyMessage): array
    {
        $path = storage_path('app/backups/' . $filename);
        $handle = fopen($path, 'c+');
        if ($handle === false) {
            throw new RuntimeException('Не удалось создать lock-файл бэкапа.');
        }

        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);
            throw new RuntimeException($busyMessage);
        }

        ftruncate($handle, 0);
        fwrite($handle, (string)getmypid() . "\n" . date('c') . "\n");
        fflush($handle);

        return ['handle' => $handle, 'path' => $path];
    }

    /**
     * @param array{handle: resource, path: string} $lock
     */
    private function releaseLock(array $lock): void
    {
        $handle = $lock['handle'] ?? null;
        if (!is_resource($handle)) {
            return;
        }

        flock($handle, LOCK_UN);
        fclose($handle);
    }

    /**
     * @return array{0: string, 1: bool}
     */
    private function resolveArchivePath(string $name, string $source): array
    {
        if ($source === 'local') {
            $path = storage_path('app/backups/' . $name);
            if (!is_file($path)) {
                throw new RuntimeException('Локальный архив не найден: ' . $name);
            }

            return [$path, false];
        }

        if (!BackupSettings::isRemoteConfigured()) {
            throw new RuntimeException('Удалённое хранилище не настроено.');
        }

        $tempPath = storage_path('app/backups/.restore_' . bin2hex(random_bytes(4)) . '_' . $name);
        $this->remoteStorage->download($name, $tempPath);

        if (!is_file($tempPath)) {
            throw new RuntimeException('Не удалось скачать архив для восстановления.');
        }

        return [$tempPath, true];
    }

    private function assertValidBackupName(string $name): void
    {
        if (!preg_match('/^backup_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.zip$/', $name)) {
            throw new RuntimeException('Некорректное имя архива.');
        }
    }

    private function parseBackupTimestamp(string $name): string
    {
        if (preg_match('/^backup_(\d{4}-\d{2}-\d{2})_(\d{2}-\d{2}-\d{2})\.zip$/', $name, $matches)) {
            $time = str_replace('-', ':', $matches[2]);
            $timestamp = strtotime($matches[1] . ' ' . $time);
            if ($timestamp) {
                return date('c', $timestamp);
            }
        }

        return date('c');
    }

    private function extractArchive(string $archivePath, string $targetDir): void
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('Расширение PHP ZipArchive не установлено.');
        }

        $zip = new ZipArchive();
        if ($zip->open($archivePath) !== true) {
            throw new RuntimeException('Не удалось открыть ZIP-архив.');
        }

        if (!$zip->extractTo($targetDir)) {
            $zip->close();
            throw new RuntimeException('Не удалось распаковать архив.');
        }

        $zip->close();
    }

    private function restoreDatabaseFromExtract(string $workDir): void
    {
        $sqlite = $workDir . '/database.sqlite';
        $sqlFile = $workDir . '/database.sql';

        if (is_file($sqlite)) {
            $this->restoreSqliteDatabase($sqlite);

            return;
        }

        if (is_file($sqlFile)) {
            $this->importMysqlDump($sqlFile);

            return;
        }

        throw new RuntimeException('В архиве нет дампа базы данных.');
    }

    private function restoreSqliteDatabase(string $source): void
    {
        $connection = (string)config('database.default');
        $config = config('database.connections.' . $connection);
        if (!is_array($config)) {
            throw new RuntimeException('Конфигурация БД не найдена.');
        }

        $target = (string)($config['database'] ?? '');
        if ($target === '') {
            throw new RuntimeException('Путь к SQLite не настроен.');
        }

        $targetDir = dirname($target);
        if (!is_dir($targetDir)) {
            File::makeDirectory($targetDir, 0755, true);
        }

        if (is_file($target)) {
            @copy($target, $target . '.before-restore-' . date('Ymd_His'));
        }

        if (!copy($source, $target)) {
            throw new RuntimeException('Не удалось восстановить файл SQLite.');
        }
    }

    private function importMysqlDump(string $sqlFile): void
    {
        $connection = (string)config('database.default');
        $config = config('database.connections.' . $connection);
        if (!is_array($config)) {
            throw new RuntimeException('Конфигурация БД не найдена.');
        }

        $driver = (string)($config['driver'] ?? '');
        if (!in_array($driver, ['mysql', 'mariadb'], true)) {
            throw new RuntimeException('Восстановление MySQL недоступно для драйвера: ' . $driver);
        }

        $mysql = MysqlBinary::resolve();
        $command = [
            $mysql,
            '--host=' . ($config['host'] ?? '127.0.0.1'),
            '--port=' . ($config['port'] ?? '3306'),
            '--user=' . ($config['username'] ?? 'root'),
            '--default-character-set=utf8mb4',
            (string)($config['database'] ?? ''),
        ];

        $handle = fopen($sqlFile, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Не удалось открыть SQL-дамп.');
        }

        $process = new Process($command);
        $process->setTimeout(7200);
        $process->setIdleTimeout(1800);
        $process->setInput($handle);
        if (!empty($config['password'])) {
            $process->setEnv(array_merge($_ENV, ['MYSQL_PWD' => (string)$config['password']]));
        }

        try {
            $process->run();
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }

        if (!$process->isSuccessful()) {
            $details = trim($process->getErrorOutput() ?: $process->getOutput());
            throw new RuntimeException(
                'mysql завершился с ошибкой (' . basename($mysql) . '). '
                . (string)Utf8::sanitize($details)
            );
        }
    }

    private function restoreSiteFilesFromExtract(string $workDir): void
    {
        $filesRoot = $workDir . '/files';
        if (!is_dir($filesRoot)) {
            throw new RuntimeException('В архиве нет файлов сайта.');
        }

        $map = [
            'storage_public' => storage_path('app/public'),
            'templates' => resource_path('tpl'),
        ];

        $restoredAny = false;
        foreach ($map as $label => $target) {
            $source = $filesRoot . '/' . $label;
            if (!is_dir($source)) {
                continue;
            }

            if (!is_dir($target)) {
                File::makeDirectory($target, 0755, true);
            }

            $this->copyDirectory($source, $target);
            $restoredAny = true;
        }

        if (!$restoredAny) {
            throw new RuntimeException('В архиве нет поддерживаемых файлов для восстановления.');
        }
    }

    private function ensureBackupDirectory(): void
    {
        $dir = storage_path('app/backups');
        if (!is_dir($dir)) {
            File::makeDirectory($dir, 0755, true);
        }
    }

    private function dumpDatabase(string $directory): void
    {
        $connection = (string)config('database.default');
        $config = config('database.connections.' . $connection);
        if (!is_array($config)) {
            throw new RuntimeException('Конфигурация БД не найдена.');
        }

        $driver = (string)($config['driver'] ?? '');

        if ($driver === 'sqlite') {
            $source = (string)($config['database'] ?? '');
            if ($source === '' || !is_file($source)) {
                throw new RuntimeException('Файл SQLite не найден.');
            }
            if (!copy($source, $directory . '/database.sqlite')) {
                throw new RuntimeException('Не удалось скопировать файл SQLite.');
            }

            return;
        }

        if (!in_array($driver, ['mysql', 'mariadb'], true)) {
            throw new RuntimeException('Драйвер БД не поддерживается для бэкапа: ' . $driver);
        }

        $sqlFile = $directory . DIRECTORY_SEPARATOR . 'database.sql';
        $mysqldump = MysqldumpBinary::resolve();
        $command = [
            $mysqldump,
            '--host=' . ($config['host'] ?? '127.0.0.1'),
            '--port=' . ($config['port'] ?? '3306'),
            '--user=' . ($config['username'] ?? 'root'),
            '--default-character-set=utf8mb4',
            '--single-transaction',
            '--quick',
            '--lock-tables=false',
            '--routines',
            '--triggers',
            // Stream dump straight to disk — avoids loading multi‑GB SQL into PHP memory.
            '--result-file=' . $sqlFile,
            (string)($config['database'] ?? ''),
        ];

        $process = new Process($command);
        $process->setTimeout(7200);
        $process->setIdleTimeout(1800);
        if (!empty($config['password'])) {
            $process->setEnv(array_merge($_ENV, ['MYSQL_PWD' => (string)$config['password']]));
        }

        $process->run();
        if (!$process->isSuccessful()) {
            $details = trim($process->getErrorOutput() ?: $process->getOutput());
            throw new RuntimeException(
                'mysqldump завершился с ошибкой (' . basename($mysqldump) . '). '
                . (string)Utf8::sanitize($details)
            );
        }

        if (!is_file($sqlFile) || filesize($sqlFile) === 0) {
            throw new RuntimeException('mysqldump вернул пустой результат.');
        }
    }

    /**
     * Build ZIP from DB dump in $workDir and (optionally) live site files — without a full temp copy.
     */
    private function createZipArchive(string $workDir, bool $includeFiles, string $archivePath): void
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('Расширение PHP ZipArchive не установлено.');
        }

        if (is_file($archivePath)) {
            @unlink($archivePath);
        }

        $zip = new ZipArchive();
        if ($zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Не удалось создать ZIP-архив.');
        }

        try {
            $this->addDirectoryToZip($zip, $workDir, '');

            if ($includeFiles) {
                $sources = [
                    'files/storage_public' => storage_path('app/public'),
                    'files/templates' => resource_path('tpl'),
                ];

                foreach ($sources as $prefix => $source) {
                    if (!is_dir($source)) {
                        continue;
                    }
                    $zip->addEmptyDir($prefix);
                    $this->addDirectoryToZip($zip, $source, $prefix);
                }
            }
        } finally {
            $zip->close();
        }

        if (!is_file($archivePath) || filesize($archivePath) === 0) {
            throw new RuntimeException('ZIP-архив пуст или не создан.');
        }
    }

    private function addDirectoryToZip(ZipArchive $zip, string $sourceDir, string $prefix): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );

        $sourceDir = rtrim(str_replace('\\', '/', $sourceDir), '/');

        foreach ($iterator as $item) {
            /** @var \SplFileInfo $item */
            $absolute = str_replace('\\', '/', $item->getPathname());
            $relative = substr($absolute, strlen($sourceDir) + 1);
            if ($relative === false || $relative === '') {
                continue;
            }

            $entry = $prefix !== '' ? $prefix . '/' . $relative : $relative;

            if ($item->isDir()) {
                $zip->addEmptyDir($entry);
                continue;
            }

            if (!$zip->addFile($item->getPathname(), $entry)) {
                throw new RuntimeException(
                    'Не удалось добавить файл в архив: ' . (string)Utf8::sanitize($entry),
                );
            }

            $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
            if (in_array($ext, self::ALREADY_COMPRESSED, true)) {
                $zip->setCompressionName($entry, ZipArchive::CM_STORE);
            } else {
                // Fast deflate — large backups (posters + SQL) finish much sooner than default level 6.
                $zip->setCompressionName($entry, ZipArchive::CM_DEFLATE, 1);
            }
        }
    }

    private function copyDirectory(string $source, string $target): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $item) {
            /** @var \SplFileInfo $item */
            $relative = substr($item->getPathname(), strlen($source) + 1);
            $dest = $target . DIRECTORY_SEPARATOR . $relative;

            if ($item->isDir()) {
                if (!is_dir($dest)) {
                    File::makeDirectory($dest, 0755, true);
                }
                continue;
            }

            $parent = dirname($dest);
            if (!is_dir($parent)) {
                File::makeDirectory($parent, 0755, true);
            }

            if (!copy($item->getPathname(), $dest)) {
                throw new RuntimeException(
                    'Не удалось скопировать файл: ' . (string)Utf8::sanitize($item->getPathname()),
                );
            }
        }
    }

    private function pruneLocal(int $retentionCount): int
    {
        $files = glob(storage_path('app/backups/backup_*.zip')) ?: [];
        rsort($files);
        $toDelete = array_slice($files, $retentionCount);
        $deleted = 0;

        foreach ($toDelete as $file) {
            if (@unlink($file)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }
        if ($bytes < 1024 * 1024 * 1024) {
            return round($bytes / (1024 * 1024), 1) . ' MB';
        }

        return round($bytes / (1024 * 1024 * 1024), 2) . ' GB';
    }
}
