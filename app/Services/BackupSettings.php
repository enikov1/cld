<?php

namespace App\Services;

use App\Models\SiteSetting;
use App\Support\EncryptedSiteSecret;

class BackupSettings
{
    public const SETTING_KEY = 'backup_settings';
    public const PASSWORD_KEY = 'backup_remote_password';
    public const S3_SECRET_KEY = 'backup_s3_secret';
    public const LAST_RUN_KEY = 'backup_last_run';

    /**
     * @return array{
     *     enabled: bool,
     *     interval_minutes: int,
     *     include_database: bool,
     *     include_files: bool,
     *     remote_enabled: bool,
     *     protocol: string,
     *     host: string,
     *     port: int,
     *     username: string,
     *     remote_path: string,
     *     s3_key: string,
     *     s3_region: string,
     *     s3_bucket: string,
     *     s3_endpoint: string,
     *     s3_path_style: bool,
     *     retention_count: int,
     *     passive: bool
     * }
     */
    public static function get(): array
    {
        $raw = SiteSetting::get(self::SETTING_KEY, '');
        $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;

        return self::normalize(is_array($decoded) ? $decoded : []);
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public static function normalize(array $input): array
    {
        $defaults = self::defaults();
        $interval = (int)($input['interval_minutes'] ?? $defaults['interval_minutes']);
        $allowedIntervals = array_column(self::intervalOptions(), 'value');
        if (!in_array($interval, $allowedIntervals, true)) {
            $interval = $defaults['interval_minutes'];
        }

        $protocol = (string)($input['protocol'] ?? $defaults['protocol']);
        if (!in_array($protocol, ['ftp', 'sftp', 's3'], true)) {
            $protocol = $defaults['protocol'];
        }

        $port = (int)($input['port'] ?? 0);
        if ($port <= 0 || $port > 65535) {
            $port = match ($protocol) {
                'sftp' => 22,
                'ftp' => 21,
                default => 443,
            };
        }

        $remotePath = $protocol === 's3'
            ? self::normalizeS3Prefix((string)($input['remote_path'] ?? $defaults['remote_path']))
            : self::normalizeRemotePath((string)($input['remote_path'] ?? $defaults['remote_path']));

        return [
            'enabled' => (bool)($input['enabled'] ?? $defaults['enabled']),
            'interval_minutes' => $interval,
            'include_database' => (bool)($input['include_database'] ?? $defaults['include_database']),
            'include_files' => (bool)($input['include_files'] ?? $defaults['include_files']),
            'remote_enabled' => (bool)($input['remote_enabled'] ?? $defaults['remote_enabled']),
            'protocol' => $protocol,
            'host' => trim((string)($input['host'] ?? $defaults['host'])),
            'port' => $port,
            'username' => trim((string)($input['username'] ?? $defaults['username'])),
            'remote_path' => $remotePath,
            's3_key' => trim((string)($input['s3_key'] ?? $defaults['s3_key'])),
            's3_region' => trim((string)($input['s3_region'] ?? $defaults['s3_region'])),
            's3_bucket' => trim((string)($input['s3_bucket'] ?? $defaults['s3_bucket'])),
            's3_endpoint' => trim((string)($input['s3_endpoint'] ?? $defaults['s3_endpoint'])),
            's3_path_style' => (bool)($input['s3_path_style'] ?? $defaults['s3_path_style']),
            'retention_count' => max(1, min(100, (int)($input['retention_count'] ?? $defaults['retention_count']))),
            'passive' => (bool)($input['passive'] ?? $defaults['passive']),
        ];
    }

    /**
     * @param array<string,mixed> $settings
     */
    public static function save(array $settings): void
    {
        SiteSetting::set(self::SETTING_KEY, json_encode(self::normalize($settings), JSON_UNESCAPED_UNICODE));
    }

    public static function password(): string
    {
        return EncryptedSiteSecret::get(self::PASSWORD_KEY);
    }

    public static function setPassword(?string $password): void
    {
        EncryptedSiteSecret::set(self::PASSWORD_KEY, $password);
    }

    public static function hasPassword(): bool
    {
        return self::password() !== '';
    }

    public static function s3Secret(): string
    {
        return EncryptedSiteSecret::get(self::S3_SECRET_KEY);
    }

    public static function setS3Secret(?string $secret): void
    {
        EncryptedSiteSecret::set(self::S3_SECRET_KEY, $secret);
    }

    public static function hasS3Secret(): bool
    {
        return self::s3Secret() !== '';
    }

    public static function isEnabled(): bool
    {
        return self::get()['enabled'];
    }

    public static function lastRunAt(): ?int
    {
        $value = SiteSetting::get(self::LAST_RUN_KEY, '');

        return is_numeric($value) && (int)$value > 0 ? (int)$value : null;
    }

    public static function markRun(): void
    {
        SiteSetting::set(self::LAST_RUN_KEY, (string)time());
    }

    public static function isDue(): bool
    {
        if (!self::isEnabled()) {
            return false;
        }

        $last = self::lastRunAt();
        if ($last === null) {
            return true;
        }

        $interval = self::get()['interval_minutes'] * 60;

        return (time() - $last) >= $interval;
    }

    public static function isRemoteConfigured(): bool
    {
        $settings = self::get();

        if (!$settings['remote_enabled']) {
            return false;
        }

        if ($settings['protocol'] === 's3') {
            return $settings['s3_key'] !== ''
                && $settings['s3_region'] !== ''
                && $settings['s3_bucket'] !== ''
                && self::hasS3Secret();
        }

        return $settings['host'] !== ''
            && $settings['username'] !== ''
            && self::hasPassword();
    }

    /**
     * @return array<string,mixed>
     */
    public static function defaults(): array
    {
        return [
            'enabled' => false,
            'interval_minutes' => 360,
            'include_database' => true,
            'include_files' => true,
            'remote_enabled' => false,
            'protocol' => 'sftp',
            'host' => '',
            'port' => 22,
            'username' => '',
            'remote_path' => '/backups',
            's3_key' => '',
            's3_region' => '',
            's3_bucket' => '',
            's3_endpoint' => '',
            's3_path_style' => false,
            'retention_count' => 10,
            'passive' => true,
        ];
    }

    /**
     * @return list<array{value: int, label: string}>
     */
    public static function intervalOptions(): array
    {
        return [
            ['value' => 60, 'label' => 'Каждый час'],
            ['value' => 180, 'label' => 'Каждые 3 часа'],
            ['value' => 360, 'label' => 'Каждые 6 часов'],
            ['value' => 720, 'label' => 'Каждые 12 часов'],
            ['value' => 1440, 'label' => 'Раз в сутки'],
        ];
    }

    private static function normalizeRemotePath(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '/backups';
        }

        return '/' . ltrim(str_replace('\\', '/', $path), '/');
    }

    private static function normalizeS3Prefix(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path), '/');

        return $path === '' ? 'backups' : $path;
    }
}
