<?php

namespace App\Services;

use App\Models\Series;
use App\Support\TplCache;
use Illuminate\Database\Eloquent\Builder;

class RutubeBulkTrailerSync
{
    public function __construct(
        private readonly RutubeTrailerService $trailers,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function runProgressiveBatch(
        bool $restart,
        string $tabName = 'Трейлер',
        string $existingMode = 'skip',
        ?string $kpId = null,
        float $sleep = 0.5,
        int $batchSize = 10,
    ): array {
        @set_time_limit(120);

        $tabName = trim($tabName) ?: 'Трейлер';
        $existingMode = $existingMode === 'update' ? 'update' : 'skip';
        $kpId = $kpId !== null && trim($kpId) !== '' ? trim($kpId) : null;
        $sleep = max(0, min(30, $sleep));
        $batchSize = max(1, min(50, $batchSize));

        $progress = RutubeBulkTrailerProgress::get();

        if (!$restart && in_array($progress['status'], ['paused', 'stopped'], true)) {
            return $progress;
        }

        if ($restart || !in_array($progress['status'], ['running', 'paused'], true)) {
            $progress = RutubeBulkTrailerProgress::normalize([
                'status' => 'running',
                'after_id' => 0,
                'total' => $this->baseQuery($kpId)->count(),
                'processed' => 0,
                'synced' => 0,
                'skipped' => 0,
                'failed' => 0,
                'tab_name' => $tabName,
                'existing_mode' => $existingMode,
                'kp_id' => $kpId,
                'sleep' => $sleep,
                'batch_size' => $batchSize,
                'message' => 'Простановка трейлеров Rutube запущена',
                'started_at' => time(),
                'finished_at' => null,
            ]);
            RutubeBulkTrailerProgress::save($progress);
        } else {
            $progress['status'] = 'running';
            $tabName = $progress['tab_name'];
            $existingMode = $progress['existing_mode'];
            $kpId = $progress['kp_id'];
            $sleep = $progress['sleep'];
            $batchSize = $progress['batch_size'];
            RutubeBulkTrailerProgress::save($progress);
        }

        $batch = $this->syncBatch(
            afterId: (int) $progress['after_id'],
            limit: $batchSize,
            tabName: $tabName,
            existingMode: $existingMode,
            kpId: $kpId,
            sleep: $sleep,
        );

        // Re-read in case pause/stop was requested mid-batch.
        $latest = RutubeBulkTrailerProgress::get();
        if (in_array($latest['status'], ['paused', 'stopped'], true)) {
            $latest['after_id'] = $batch['last_id'];
            $latest['processed'] = (int) $progress['processed'] + $batch['processed'];
            $latest['synced'] = (int) $progress['synced'] + $batch['synced'];
            $latest['skipped'] = (int) $progress['skipped'] + $batch['skipped'];
            $latest['failed'] = (int) $progress['failed'] + $batch['failed'];
            if ($latest['status'] === 'paused') {
                $latest['message'] = sprintf(
                    'Пауза: обработано %d из %d',
                    $latest['processed'],
                    max($latest['total'], $latest['processed']),
                );
            } else {
                $latest['finished_at'] = time();
                $latest['message'] = sprintf(
                    'Остановлено: проставлено %d, пропущено %d, ошибок %d',
                    $latest['synced'],
                    $latest['skipped'],
                    $latest['failed'],
                );
            }
            RutubeBulkTrailerProgress::save($latest);

            return $latest;
        }

        $progress['after_id'] = $batch['last_id'];
        $progress['processed'] += $batch['processed'];
        $progress['synced'] += $batch['synced'];
        $progress['skipped'] += $batch['skipped'];
        $progress['failed'] += $batch['failed'];
        $progress['message'] = sprintf(
            'Обработано %d из %d',
            $progress['processed'],
            max($progress['total'], $progress['processed']),
        );

        if ($batch['done']) {
            if ($progress['synced'] > 0) {
                TplCache::bumpGlobalVersion();
            }

            $progress['status'] = 'done';
            $progress['finished_at'] = time();
            $progress['message'] = sprintf(
                'Готово: проставлено %d, пропущено %d, ошибок %d',
                $progress['synced'],
                $progress['skipped'],
                $progress['failed'],
            );
        } else {
            $progress['status'] = 'running';
        }

        RutubeBulkTrailerProgress::save($progress);

        return $progress;
    }

    /**
     * @return array{
     *     last_id: int,
     *     processed: int,
     *     synced: int,
     *     skipped: int,
     *     failed: int,
     *     done: bool,
     *     remaining: int
     * }
     */
    public function syncBatch(
        int $afterId,
        int $limit,
        string $tabName,
        string $existingMode,
        ?string $kpId,
        float $sleep,
    ): array {
        $out = [
            'last_id' => $afterId,
            'processed' => 0,
            'synced' => 0,
            'skipped' => 0,
            'failed' => 0,
            'done' => false,
            'remaining' => 0,
        ];

        $seriesList = $this->baseQuery($kpId)
            ->where('id', '>', $afterId)
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($seriesList as $series) {
            $control = RutubeBulkTrailerProgress::get();
            if (in_array($control['status'], ['paused', 'stopped'], true)) {
                break;
            }

            $out['last_id'] = (int) $series->id;
            $out['processed']++;

            $title = trim((string) $series->title);
            if ($title === '') {
                $out['skipped']++;
                continue;
            }

            try {
                $result = $this->trailers->addToSeries($series, $tabName, $existingMode);
                if (!($result['ok'] ?? false)) {
                    $out['failed']++;
                    if ($sleep > 0) {
                        usleep((int) ($sleep * 1_000_000));
                    }
                    continue;
                }

                if (!empty($result['skipped'])) {
                    $out['skipped']++;
                } else {
                    $out['synced']++;
                    if ($sleep > 0) {
                        usleep((int) ($sleep * 1_000_000));
                    }
                }
            } catch (\Throwable) {
                $out['failed']++;
            }
        }

        $out['remaining'] = $this->baseQuery($kpId)
            ->where('id', '>', $out['last_id'])
            ->count();
        $out['done'] = $out['remaining'] === 0
            && !in_array(RutubeBulkTrailerProgress::get()['status'], ['paused', 'stopped'], true);

        return $out;
    }

    public function pause(): array
    {
        $progress = RutubeBulkTrailerProgress::get();
        if ($progress['status'] !== 'running') {
            return $progress;
        }

        $progress['status'] = 'paused';
        $progress['message'] = sprintf(
            'Пауза: обработано %d из %d',
            $progress['processed'],
            max($progress['total'], $progress['processed']),
        );
        RutubeBulkTrailerProgress::save($progress);

        return $progress;
    }

    public function resume(): array
    {
        $progress = RutubeBulkTrailerProgress::get();
        if ($progress['status'] !== 'paused') {
            return $progress;
        }

        $progress['status'] = 'running';
        $progress['message'] = sprintf(
            'Продолжение: обработано %d из %d',
            $progress['processed'],
            max($progress['total'], $progress['processed']),
        );
        RutubeBulkTrailerProgress::save($progress);

        return $progress;
    }

    public function stop(): array
    {
        $progress = RutubeBulkTrailerProgress::get();
        if (!in_array($progress['status'], ['running', 'paused'], true)) {
            return $progress;
        }

        $progress['status'] = 'stopped';
        $progress['finished_at'] = time();
        $progress['message'] = sprintf(
            'Остановлено: проставлено %d, пропущено %d, ошибок %d',
            $progress['synced'],
            $progress['skipped'],
            $progress['failed'],
        );
        RutubeBulkTrailerProgress::save($progress);

        return $progress;
    }

    private function baseQuery(?string $kpId): Builder
    {
        $query = Series::query()->whereNotNull('title')->where('title', '!=', '');

        if ($kpId !== null && $kpId !== '') {
            $query->where('kp_id', $kpId);
        }

        return $query;
    }
}
