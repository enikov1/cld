<?php

namespace App\Services;

use App\Models\SiteSetting;

class RutubeBulkTrailerProgress
{
    public const SETTING_KEY = 'rutube_bulk_trailer_progress';

    /**
     * @return array{
     *     status: 'idle'|'running'|'paused'|'stopped'|'done'|'failed',
     *     after_id: int,
     *     total: int,
     *     processed: int,
     *     synced: int,
     *     skipped: int,
     *     failed: int,
     *     tab_name: string,
     *     existing_mode: 'skip'|'update',
     *     kp_id: string|null,
     *     sleep: float,
     *     batch_size: int,
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
     *     status: 'idle'|'running'|'paused'|'stopped'|'done'|'failed',
     *     after_id: int,
     *     total: int,
     *     processed: int,
     *     synced: int,
     *     skipped: int,
     *     failed: int,
     *     tab_name: string,
     *     existing_mode: 'skip'|'update',
     *     kp_id: string|null,
     *     sleep: float,
     *     batch_size: int,
     *     message: string,
     *     started_at: int|null,
     *     finished_at: int|null
     * }
     */
    public static function normalize(array $input): array
    {
        $status = (string) ($input['status'] ?? 'idle');
        if (!in_array($status, ['idle', 'running', 'paused', 'stopped', 'done', 'failed'], true)) {
            $status = 'idle';
        }

        $existingMode = (string) ($input['existing_mode'] ?? 'skip');
        if (!in_array($existingMode, ['skip', 'update'], true)) {
            $existingMode = 'skip';
        }

        return [
            'status' => $status,
            'after_id' => max(0, (int) ($input['after_id'] ?? 0)),
            'total' => max(0, (int) ($input['total'] ?? 0)),
            'processed' => max(0, (int) ($input['processed'] ?? 0)),
            'synced' => max(0, (int) ($input['synced'] ?? 0)),
            'skipped' => max(0, (int) ($input['skipped'] ?? 0)),
            'failed' => max(0, (int) ($input['failed'] ?? 0)),
            'tab_name' => trim((string) ($input['tab_name'] ?? 'Трейлер')) ?: 'Трейлер',
            'existing_mode' => $existingMode,
            'kp_id' => isset($input['kp_id']) && trim((string) $input['kp_id']) !== ''
                ? trim((string) $input['kp_id'])
                : null,
            'sleep' => max(0, min(30, (float) ($input['sleep'] ?? 0.5))),
            'batch_size' => max(1, min(50, (int) ($input['batch_size'] ?? 10))),
            'message' => (string) ($input['message'] ?? ''),
            'started_at' => isset($input['started_at']) && is_numeric($input['started_at']) ? (int) $input['started_at'] : null,
            'finished_at' => isset($input['finished_at']) && is_numeric($input['finished_at']) ? (int) $input['finished_at'] : null,
        ];
    }

    /**
     * @param array<string, mixed> $progress
     */
    public static function save(array $progress): void
    {
        SiteSetting::set(self::SETTING_KEY, json_encode(self::normalize($progress), JSON_UNESCAPED_UNICODE));
    }

    public static function percent(array $progress): int
    {
        $total = max(0, (int) ($progress['total'] ?? 0));
        $processed = max(0, (int) ($progress['processed'] ?? 0));

        if ($total <= 0) {
            return in_array(($progress['status'] ?? ''), ['done', 'stopped'], true) ? 100 : 0;
        }

        return min(100, (int) round(($processed / $total) * 100));
    }
}
