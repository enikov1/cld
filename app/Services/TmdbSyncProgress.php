<?php

namespace App\Services;

use App\Models\SiteSetting;
use App\Models\CronRun;

class TmdbSyncProgress
{
    public const SETTING_KEY = 'tmdb_sync_progress';

    /**
     * @return array{
     *     status: 'idle'|'running'|'done'|'failed',
     *     after_id: int,
     *     total: int,
     *     processed: int,
     *     updated: int,
     *     failed: int,
     *     status_changed: int,
     *     schedule_synced: int,
     *     studios_linked: int,
     *     studio_logos: int,
     *     message: string,
     *     started_at: int|null,
     *     finished_at: int|null
     * }
     */
    public static function get(): array
    {
        $raw = SiteSetting::get(self::SETTING_KEY, '');
        $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;

        return self::normalize(is_array($decoded) ? $decoded : []);
    }

    /**
     * @param array<string, mixed> $input
     * @return array{
     *     status: 'idle'|'running'|'done'|'failed',
     *     after_id: int,
     *     total: int,
     *     processed: int,
     *     updated: int,
     *     failed: int,
     *     status_changed: int,
     *     schedule_synced: int,
     *     studios_linked: int,
     *     studio_logos: int,
     *     message: string,
     *     started_at: int|null,
     *     finished_at: int|null
     * }
     */
    public static function normalize(array $input): array
    {
        $status = (string)($input['status'] ?? 'idle');
        if (!in_array($status, ['idle', 'running', 'done', 'failed'], true)) {
            $status = 'idle';
        }

        return [
            'status' => $status,
            'after_id' => max(0, (int)($input['after_id'] ?? 0)),
            'total' => max(0, (int)($input['total'] ?? 0)),
            'processed' => max(0, (int)($input['processed'] ?? 0)),
            'updated' => max(0, (int)($input['updated'] ?? 0)),
            'failed' => max(0, (int)($input['failed'] ?? 0)),
            'status_changed' => max(0, (int)($input['status_changed'] ?? 0)),
            'schedule_synced' => max(0, (int)($input['schedule_synced'] ?? 0)),
            'studios_linked' => max(0, (int)($input['studios_linked'] ?? 0)),
            'studio_logos' => max(0, (int)($input['studio_logos'] ?? 0)),
            'message' => (string)($input['message'] ?? ''),
            'started_at' => isset($input['started_at']) && is_numeric($input['started_at']) ? (int)$input['started_at'] : null,
            'finished_at' => isset($input['finished_at']) && is_numeric($input['finished_at']) ? (int)$input['finished_at'] : null,
            'cron_run_id' => isset($input['cron_run_id']) && is_numeric($input['cron_run_id']) ? (int)$input['cron_run_id'] : null,
        ];
    }

    /**
     * @param array<string, mixed> $progress
     */
    public static function save(array $progress): void
    {
        SiteSetting::set(self::SETTING_KEY, json_encode(self::normalize($progress), JSON_UNESCAPED_UNICODE));
    }

    public static function clear(): void
    {
        SiteSetting::set(self::SETTING_KEY, json_encode(self::normalize([]), JSON_UNESCAPED_UNICODE));
    }
}
