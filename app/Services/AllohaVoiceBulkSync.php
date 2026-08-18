<?php

namespace App\Services;

use App\Models\PlayerSource;
use App\Models\Series;
use App\Models\Voice;
use App\Support\TplCache;
use Illuminate\Database\Eloquent\Builder;

class AllohaVoiceBulkSync
{
    public function __construct(
        private readonly AllohaClient $client,
        private readonly TaxonomyService $taxonomy,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function runProgressiveBatch(bool $restart): array
    {
        if (!$this->client->isConfigured()) {
            return array_merge(AllohaVoiceSyncProgress::get(), [
                'status' => 'failed',
                'message' => 'API-токен Alloha не настроен. Укажите его в Настройках.',
            ]);
        }

        @set_time_limit(40);
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        ignore_user_abort(true);

        $progress = AllohaVoiceSyncProgress::get();

        if ($restart || !in_array($progress['status'], ['running'], true)) {
            $this->taxonomy->purgeDummyVoices();
            $this->taxonomy->purgeUnusedVoices();
            $this->attachFromPlayerSources();

            $progress = AllohaVoiceSyncProgress::normalize([
                'status' => 'running',
                'after_id' => 0,
                'total' => $this->seriesQuery()->count(),
                'processed' => 0,
                'synced' => 0,
                'skipped' => 0,
                'failed' => 0,
                'catalog' => 0,
                'current' => '',
                'message' => 'Синхронизация озвучек по сериалам…',
                'started_at' => time(),
                'finished_at' => null,
            ]);
            AllohaVoiceSyncProgress::save($progress);
        } else {
            $progress['status'] = 'running';
            AllohaVoiceSyncProgress::save($progress);
        }

        $batchSize = max(1, min(20, (int) config('alloha.voice_bulk_batch_size', 5)));
        $this->syncBatch($progress, $batchSize);

        $latest = AllohaVoiceSyncProgress::get();
        if (($latest['status'] ?? '') === 'stopped') {
            $latest['current'] = '';
            $latest['finished_at'] = time();
            $latest['message'] = sprintf(
                'Остановлено: обработано %d из %d, привязано %d, пропущено %d, ошибок %d',
                $latest['processed'],
                max($latest['total'], $latest['processed']),
                $latest['synced'],
                $latest['skipped'],
                $latest['failed'],
            );
            AllohaVoiceSyncProgress::save($latest);

            return $latest;
        }

        $done = $this->seriesQuery()->where('id', '>', (int) $progress['after_id'])->doesntExist();
        if ($done) {
            $this->taxonomy->purgeUnusedVoices();
            TplCache::bumpGlobalVersion();
            $progress['catalog'] = Voice::query()->has('series')->count();
            $progress['status'] = 'done';
            $progress['current'] = '';
            $progress['finished_at'] = time();
            $progress['message'] = sprintf(
                'Готово: озвучек у сериалов %d, сериалов с озвучками %d, пропущено %d, ошибок %d',
                $progress['catalog'],
                $progress['synced'],
                $progress['skipped'],
                $progress['failed'],
            );
        } else {
            $progress['status'] = 'running';
            $progress['message'] = sprintf(
                'Обработано %d из %d, привязано %d',
                $progress['processed'],
                max($progress['total'], $progress['processed']),
                $progress['synced'],
            );
        }

        AllohaVoiceSyncProgress::save($progress);

        return $progress;
    }

    public function stop(): array
    {
        $progress = AllohaVoiceSyncProgress::get();
        if ($progress['status'] !== 'running') {
            return $progress;
        }

        $progress['status'] = 'stopped';
        $progress['message'] = sprintf(
            'Остановка… обработано %d из %d',
            $progress['processed'],
            max($progress['total'], $progress['processed']),
        );
        AllohaVoiceSyncProgress::save($progress);

        return $progress;
    }

    /**
     * @param array<string, mixed> $progress
     */
    private function syncBatch(array &$progress, int $limit): void
    {
        $seriesList = $this->seriesQuery()
            ->where('id', '>', (int) $progress['after_id'])
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $startedAt = microtime(true);
        $timeBudget = max(8, min(25, (int) config('alloha.voice_bulk_time_budget', 18)));

        foreach ($seriesList as $series) {
            if ((microtime(true) - $startedAt) >= $timeBudget) {
                break;
            }

            $control = AllohaVoiceSyncProgress::get();
            if (($control['status'] ?? '') === 'stopped') {
                $progress['status'] = 'stopped';
                break;
            }

            $title = trim((string) $series->title);
            $progress['after_id'] = (int) $series->id;
            $progress['current'] = $title;
            $progress['processed'] = (int) $progress['processed'] + 1;

            try {
                if ($this->syncSeries($series)) {
                    $progress['synced'] = (int) $progress['synced'] + 1;
                    TplCache::forgetSeries((int) $series->id);
                } else {
                    $progress['skipped'] = (int) $progress['skipped'] + 1;
                }
            } catch (\Throwable) {
                $progress['failed'] = (int) $progress['failed'] + 1;
            }

            $progress['message'] = sprintf(
                'Обработано %d из %d — %s',
                $progress['processed'],
                max((int) $progress['total'], (int) $progress['processed']),
                $title !== '' ? $title : ('ID ' . $series->id),
            );

            $control = AllohaVoiceSyncProgress::get();
            if (($control['status'] ?? '') === 'stopped') {
                $progress['status'] = 'stopped';
            }
            AllohaVoiceSyncProgress::save($progress);

            if (($progress['status'] ?? '') === 'stopped') {
                break;
            }
        }
    }

    private function syncSeries(Series $series): bool
    {
        $kpId = trim((string) ($series->kp_id ?? ''));
        $lookupKp = ($kpId !== '' && preg_match('/^\d+$/', $kpId)) ? $kpId : null;
        $response = $this->client->getMovieWithFallback(
            $lookupKp,
            AllohaClient::normalizeImdbId($series->imdb_id),
            trim((string) ($series->tmdb_id ?? '')),
            max(3, (int) config('alloha.voice_request_timeout', 8)),
            1,
        );
        if ($response === []) {
            return false;
        }

        $data = $response['data'] ?? $response;
        $translations = is_array($data['translations'] ?? null) ? $data['translations'] : [];
        if ($translations === []) {
            $mapped = AllohaMapper::toSeriesAttributes($response);
            $translations = is_array($mapped['_translations'] ?? null) ? $mapped['_translations'] : [];
        }
        if ($translations === []) {
            return false;
        }

        $before = $series->voices()->count();
        $this->taxonomy->syncSeriesVoicesFromTranslations($series, $translations);

        return $series->voices()->count() > 0 || $before > 0;
    }

    private function attachFromPlayerSources(): void
    {
        $rows = PlayerSource::query()
            ->whereNotNull('alloha_translation_id')
            ->get(['series_id', 'alloha_translation_id', 'provider']);

        $bySeries = [];
        foreach ($rows as $row) {
            $voice = $this->taxonomy->upsertVoice(
                (string) $row->provider,
                (int) $row->alloha_translation_id,
            );
            if (!$voice) {
                continue;
            }
            $bySeries[(int) $row->series_id][] = (int) $voice->id;
        }

        foreach ($bySeries as $seriesId => $voiceIds) {
            $series = Series::query()->find($seriesId);
            if (!$series) {
                continue;
            }
            $series->voices()->syncWithoutDetaching(array_values(array_unique($voiceIds)));
        }
    }

    private function seriesQuery(): Builder
    {
        return Series::query()
            ->where(function (Builder $query) {
                $query->where(function (Builder $kp) {
                    $kp->whereNotNull('kp_id')->where('kp_id', '!=', '');
                })->orWhere(function (Builder $imdb) {
                    $imdb->whereNotNull('imdb_id')->where('imdb_id', '!=', '');
                })->orWhere(function (Builder $tmdb) {
                    $tmdb->whereNotNull('tmdb_id')->where('tmdb_id', '!=', '');
                });
            });
    }
}
