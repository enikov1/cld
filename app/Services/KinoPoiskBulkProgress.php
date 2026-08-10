<?php

namespace App\Services;

use App\Models\SiteSetting;

class KinoPoiskBulkProgress
{
    public const SETTING_KEY = 'kp_bulk_sync_progress';

    /**
     * @return array{
     *     status: 'idle'|'running'|'paused'|'stopped'|'done'|'failed',
     *     after_index: int,
     *     total: int,
     *     processed: int,
     *     synced: int,
     *     skipped: int,
     *     failed: int,
     *     keyword: string,
     *     limit: int,
     *     sleep: float,
     *     download_poster: bool,
     *     batch_size: int,
     *     film_ids: list<string|int>,
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
     *     after_index: int,
     *     total: int,
     *     processed: int,
     *     synced: int,
     *     skipped: int,
     *     failed: int,
     *     keyword: string,
     *     limit: int,
     *     sleep: float,
     *     download_poster: bool,
     *     batch_size: int,
     *     film_ids: list<string|int>,
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

        $filmIds = [];
        if (isset($input['film_ids']) && is_array($input['film_ids'])) {
            foreach ($input['film_ids'] as $id) {
                $s = trim((string) $id);
                if ($s !== '') {
                    $filmIds[] = $s;
                }
            }
        }

        return [
            'status' => $status,
            'after_index' => max(0, (int) ($input['after_index'] ?? 0)),
            'total' => max(0, (int) ($input['total'] ?? 0)),
            'processed' => max(0, (int) ($input['processed'] ?? 0)),
            'synced' => max(0, (int) ($input['synced'] ?? 0)),
            'skipped' => max(0, (int) ($input['skipped'] ?? 0)),
            'failed' => max(0, (int) ($input['failed'] ?? 0)),
            'keyword' => trim((string) ($input['keyword'] ?? '')),
            'limit' => max(1, min(250, (int) ($input['limit'] ?? 20))),
            'sleep' => max(0, min(30, (float) ($input['sleep'] ?? 0))),
            'download_poster' => (bool) ($input['download_poster'] ?? false),
            'batch_size' => max(1, min(50, (int) ($input['batch_size'] ?? 5))),
            'film_ids' => $filmIds,
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
