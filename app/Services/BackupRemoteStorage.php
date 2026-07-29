<?php

namespace App\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class BackupRemoteStorage
{
    public function disk(): Filesystem
    {
        $settings = BackupSettings::get();

        if (!$settings['remote_enabled']) {
            throw new RuntimeException('Удалённое хранилище не включено в настройках.');
        }

        if ($settings['protocol'] === 's3') {
            return $this->buildS3Disk($settings);
        }

        if ($settings['host'] === '' || $settings['username'] === '') {
            throw new RuntimeException('Укажите хост и логин для удалённого сервера.');
        }

        if (!BackupSettings::hasPassword()) {
            throw new RuntimeException('Укажите пароль для удалённого сервера.');
        }

        $config = [
            'driver' => $settings['protocol'],
            'host' => $settings['host'],
            'port' => $settings['port'],
            'username' => $settings['username'],
            'password' => BackupSettings::password(),
            'root' => $settings['remote_path'],
            'timeout' => 120,
            'throw' => true,
        ];

        if ($settings['protocol'] === 'ftp') {
            $config['passive'] = $settings['passive'];
            $config['ssl'] = false;
        }

        return Storage::build($config);
    }

    /**
     * @param array<string,mixed> $settings
     */
    private function buildS3Disk(array $settings): Filesystem
    {
        if ($settings['s3_key'] === '' || $settings['s3_bucket'] === '' || $settings['s3_region'] === '') {
            throw new RuntimeException('Укажите Access Key, регион и bucket для S3.');
        }

        if (!BackupSettings::hasS3Secret()) {
            throw new RuntimeException('Укажите Secret Key для S3.');
        }

        $config = [
            'driver' => 's3',
            'key' => $settings['s3_key'],
            'secret' => BackupSettings::s3Secret(),
            'region' => $settings['s3_region'],
            'bucket' => $settings['s3_bucket'],
            'root' => $settings['remote_path'],
            'throw' => true,
        ];

        if ($settings['s3_endpoint'] !== '') {
            $config['endpoint'] = $settings['s3_endpoint'];
            // S3-совместимые провайдеры (Adman, MinIO и т.п.) обычно требуют path-style.
            $config['use_path_style_endpoint'] = true;
        }

        if ($settings['s3_path_style']) {
            $config['use_path_style_endpoint'] = true;
        }

        return Storage::build($config);
    }

    public function testConnection(): void
    {
        $disk = $this->disk();
        $probe = '.backup_probe_' . bin2hex(random_bytes(4)) . '.txt';

        try {
            $disk->put($probe, 'ok');
            $disk->delete($probe);
        } catch (\Throwable $e) {
            throw new RuntimeException(self::humanizeS3Error($e), 0, $e);
        }
    }

    private static function humanizeS3Error(\Throwable $e): string
    {
        $message = $e->getMessage();
        $settings = BackupSettings::get();
        $bucket = (string)($settings['s3_bucket'] ?? '');

        if (stripos($message, 'NoSuchBucket') !== false) {
            return 'Бакет «' . $bucket . '» не найден на S3. '
                . 'Создайте его в панели провайдера или укажите точное имя существующего бакета. '
                . 'Префикс (папка) — это не имя бакета.';
        }

        if (stripos($message, 'InvalidAccessKeyId') !== false || stripos($message, 'SignatureDoesNotMatch') !== false) {
            return 'Неверный Access Key или Secret Key для S3.';
        }

        if (stripos($message, 'AccessDenied') !== false) {
            return 'Доступ к бакету «' . $bucket . '» запрещён. Проверьте права ключа.';
        }

        return 'Не удалось подключиться: ' . $message;
    }

    public function upload(string $localPath, string $remoteName): void
    {
        $stream = fopen($localPath, 'rb');
        if ($stream === false) {
            throw new RuntimeException('Не удалось открыть локальный файл бэкапа.');
        }

        try {
            $this->disk()->writeStream($remoteName, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    public function download(string $remoteName, string $localPath): void
    {
        $stream = $this->disk()->readStream($remoteName);
        if ($stream === false) {
            throw new RuntimeException('Не удалось скачать архив с удалённого сервера.');
        }

        $target = fopen($localPath, 'wb');
        if ($target === false) {
            if (is_resource($stream)) {
                fclose($stream);
            }
            throw new RuntimeException('Не удалось создать локальный файл для восстановления.');
        }

        try {
            stream_copy_to_stream($stream, $target);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
            if (is_resource($target)) {
                fclose($target);
            }
        }
    }

    /**
     * @return list<array{name: string, size: int|null, created_at: string|null}>
     */
    public function listBackupFilesWithMeta(): array
    {
        $items = [];
        $disk = $this->disk();

        foreach ($this->listBackupFiles() as $file) {
            $name = basename(str_replace('\\', '/', $file));
            $size = null;
            try {
                $size = $disk->size($file);
            } catch (\Throwable) {
                // ignore
            }

            $items[] = [
                'name' => $name,
                'size' => is_int($size) ? $size : null,
                'created_at' => self::parseBackupTimestamp($name),
            ];
        }

        return $items;
    }

    private static function parseBackupTimestamp(string $name): ?string
    {
        if (!preg_match('/^backup_(\d{4}-\d{2}-\d{2})_(\d{2}-\d{2}-\d{2})\.zip$/', $name, $matches)) {
            return null;
        }

        $date = str_replace('-', ':', $matches[2]);
        $timestamp = strtotime($matches[1] . ' ' . $date);

        return $timestamp ? date('c', $timestamp) : null;
    }

    /**
     * @return list<string>
     */
    public function listBackupFiles(): array
    {
        $files = [];
        foreach ($this->disk()->files() as $file) {
            if (str_starts_with(basename($file), 'backup_') && str_ends_with($file, '.zip')) {
                $files[] = $file;
            }
        }

        rsort($files);

        return $files;
    }

    public function prune(int $retentionCount): int
    {
        $files = $this->listBackupFiles();
        $toDelete = array_slice($files, $retentionCount);
        $deleted = 0;

        foreach ($toDelete as $file) {
            $this->disk()->delete($file);
            $deleted++;
        }

        return $deleted;
    }
}
