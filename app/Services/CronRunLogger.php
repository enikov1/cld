<?php

namespace App\Services;

use App\Models\CronRun;
use App\Support\Utf8;
use Illuminate\Support\Str;
use Throwable;

class CronRunLogger
{
    public const JOB_ALLOHA_LATEST = 'alloha:latest';
    public const JOB_TMDB_POPULARITY = 'tmdb:sync-popularity';
    public const JOB_POPULAR_BADGES = 'series:refresh-popularity-badges';
    public const JOB_SITEMAP = 'sitemap:generate';
    public const JOB_KP_SYNC = 'kp:sync';
    public const JOB_ALLOHA_SYNC = 'alloha:sync';
    public const JOB_ALLOHA_IMPORT = 'alloha:import';
    public const JOB_TMDB_STUDIO_LOGOS = 'tmdb:fill-studio-logos';
    public const JOB_BACKUP = 'backup:run';
    public const JOB_BACKUP_RESTORE = 'backup:restore';

    /**
     * @param array<string, mixed> $meta
     */
    public static function start(
        string $jobKey,
        string $command,
        string $trigger = CronRun::TRIGGER_SCHEDULE,
        array $meta = [],
        ?string $message = null,
    ): CronRun {
        return CronRun::query()->create([
            'job_key' => $jobKey,
            'command' => $command,
            'trigger' => $trigger,
            'status' => CronRun::STATUS_RUNNING,
            'started_at' => now(),
            'message' => $message,
            'meta' => $meta ?: null,
        ]);
    }

    /**
     * @param array<string, mixed>|null $counts
     * @param list<string>|string|null $log
     */
    /**
     * Mid-run progress for long jobs (backup/restore). Persists message + meta.progress for UI polling.
     *
     * @param array{percent?: int, stage?: string|null, step?: int|null, steps?: int|null}|null $progress
     */
    public static function progress(CronRun $run, string $message, ?array $progress = null): void
    {
        if ($run->status !== CronRun::STATUS_RUNNING) {
            return;
        }

        $meta = is_array($run->meta) ? $run->meta : [];
        if ($progress !== null) {
            $percent = isset($progress['percent']) ? (int)$progress['percent'] : 0;
            $meta['progress'] = [
                'percent' => max(0, min(100, $percent)),
                'stage' => isset($progress['stage']) ? (string)$progress['stage'] : null,
                'step' => isset($progress['step']) ? (int)$progress['step'] : null,
                'steps' => isset($progress['steps']) ? (int)$progress['steps'] : null,
                'updated_at' => now()->toIso8601String(),
            ];
        }

        $run->fill([
            'message' => Str::limit((string)Utf8::sanitize($message), 500, ''),
            'meta' => $meta,
        ]);
        $run->save();
    }

    public static function finish(
        CronRun $run,
        string $status,
        ?array $counts = null,
        ?string $message = null,
        ?string $error = null,
        array|string|null $log = null,
    ): CronRun {
        $finishedAt = now();
        $startedAt = $run->started_at ?? $finishedAt;
        $durationMs = max(0, (int)$startedAt->diffInMilliseconds($finishedAt));

        $logText = null;
        if (is_array($log)) {
            $logText = implode("\n", array_map(static fn ($line) => (string)Utf8::sanitize((string)$line), $log));
        } elseif (is_string($log) && $log !== '') {
            $logText = (string)Utf8::sanitize($log);
        }

        if ($logText !== null) {
            $logText = Str::limit($logText, 20000, "\n…");
        }

        $run->fill([
            'status' => $status,
            'finished_at' => $finishedAt,
            'duration_ms' => $durationMs,
            'counts' => $counts,
            'message' => $message !== null ? Str::limit((string)Utf8::sanitize($message), 500, '') : $run->message,
            'error' => $error !== null ? Str::limit((string)Utf8::sanitize($error), 5000, '') : null,
            'log' => $logText,
        ]);
        $run->save();

        self::prune();

        return $run;
    }

    /**
     * @param callable(): array{
     *     status?: string,
     *     counts?: array<string, mixed>|null,
     *     message?: string|null,
     *     error?: string|null,
     *     log?: list<string>|string|null
     * }|void $callback
     * @param array<string, mixed> $meta
     */
    public static function run(
        string $jobKey,
        string $command,
        string $trigger,
        callable $callback,
        array $meta = [],
        ?string $startMessage = null,
    ): CronRun {
        $run = self::start($jobKey, $command, $trigger, $meta, $startMessage);

        try {
            $result = $callback($run);
            if (!is_array($result)) {
                $result = [];
            }

            $status = (string)($result['status'] ?? CronRun::STATUS_SUCCESS);
            if (!in_array($status, [
                CronRun::STATUS_SUCCESS,
                CronRun::STATUS_FAILED,
                CronRun::STATUS_SKIPPED,
            ], true)) {
                $status = CronRun::STATUS_SUCCESS;
            }

            return self::finish(
                $run,
                $status,
                $result['counts'] ?? null,
                $result['message'] ?? null,
                $result['error'] ?? null,
                $result['log'] ?? null,
            );
        } catch (Throwable $e) {
            return self::finish(
                $run,
                CronRun::STATUS_FAILED,
                null,
                'Ошибка выполнения',
                (string)Utf8::sanitize($e->getMessage()),
            );
        }
    }

    public static function detectTrigger(?string $explicit = null): string
    {
        if ($explicit !== null && $explicit !== '') {
            return match ($explicit) {
                CronRun::TRIGGER_ADMIN, 'admin' => CronRun::TRIGGER_ADMIN,
                CronRun::TRIGGER_SCHEDULE, 'schedule', 'cron' => CronRun::TRIGGER_SCHEDULE,
                default => CronRun::TRIGGER_CLI,
            };
        }

        if (app()->runningInConsole()) {
            return CronRun::TRIGGER_CLI;
        }

        return CronRun::TRIGGER_ADMIN;
    }

    public static function jobLabel(string $jobKey): string
    {
        return match ($jobKey) {
            self::JOB_ALLOHA_LATEST => 'Alloha: последние',
            self::JOB_TMDB_POPULARITY => 'TMDB: популярность и статусы',
            self::JOB_POPULAR_BADGES => 'Бейджи «Популярно»',
            self::JOB_SITEMAP => 'Sitemap',
            self::JOB_KP_SYNC => 'KinoPoisk sync',
            self::JOB_ALLOHA_SYNC => 'Alloha sync',
            self::JOB_ALLOHA_IMPORT => 'Alloha import',
            self::JOB_TMDB_STUDIO_LOGOS => 'TMDB: логотипы студий',
            self::JOB_BACKUP => 'Резервное копирование',
            self::JOB_BACKUP_RESTORE => 'Восстановление из бэкапа',
            default => $jobKey,
        };
    }

    /**
     * Latest run for a job, marking stale "running" rows as failed.
     */
    public static function latestJob(string $jobKey, int $staleAfterSeconds = 10800): ?CronRun
    {
        $run = CronRun::query()
            ->where('job_key', $jobKey)
            ->orderByDesc('id')
            ->first();

        if ($run === null) {
            return null;
        }

        if (
            $run->status === CronRun::STATUS_RUNNING
            && $run->started_at
            && $run->started_at->lt(now()->subSeconds($staleAfterSeconds))
        ) {
            return self::finish(
                $run,
                CronRun::STATUS_FAILED,
                null,
                'Превышено время ожидания',
                'Задача зависла или процесс был прерван.',
            );
        }

        return $run;
    }

    public static function isJobRunning(string $jobKey, int $staleAfterSeconds = 10800): bool
    {
        $run = self::latestJob($jobKey, $staleAfterSeconds);

        return $run !== null && $run->status === CronRun::STATUS_RUNNING;
    }

    private static function prune(): void
    {
        // Keep the history readable: last 500 runs.
        $keepIds = CronRun::query()
            ->orderByDesc('id')
            ->limit(500)
            ->pluck('id');

        if ($keepIds->isEmpty()) {
            return;
        }

        CronRun::query()
            ->whereNotIn('id', $keepIds->all())
            ->delete();
    }
}
