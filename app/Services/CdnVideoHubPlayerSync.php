<?php

namespace App\Services;

use App\Models\PlayerSource;
use App\Models\Series;
use App\Support\PlayerUrlHelper;
use App\Support\SiteConfig;
use App\Support\TplCache;

class CdnVideoHubPlayerSync
{
    public const SOURCE_KEY = 'cdnvideohub';

    public function syncIfEnabled(Series $series): void
    {
        if (!SiteConfig::bool('player_cdnvideohub_auto_enabled')) {
            return;
        }

        $this->syncSeries($series);
    }

    /**
     * Apply CDN VideoHub player to every series that has a numeric KP ID.
     *
     * @return array{ok: bool, synced: int, skipped: int, error?: string}
     */
    public function syncAll(): array
    {
        if (!SiteConfig::bool('player_cdnvideohub_auto_enabled')) {
            return [
                'ok' => false,
                'synced' => 0,
                'skipped' => 0,
                'error' => 'Автодобавление CDN VideoHub выключено. Включите его и сохраните настройки.',
            ];
        }

        $synced = 0;
        $skipped = 0;

        Series::query()
            ->whereNotNull('kp_id')
            ->where('kp_id', '!=', '')
            ->orderBy('id')
            ->chunkById(100, function ($chunk) use (&$synced, &$skipped): void {
                foreach ($chunk as $series) {
                    $kpId = trim((string) $series->kp_id);
                    if ($kpId === '' || !preg_match('/^\d+$/', $kpId)) {
                        $skipped++;
                        continue;
                    }

                    $this->syncSeries($series);
                    $synced++;
                }
            });

        if ($synced > 0) {
            TplCache::bumpGlobalVersion();
        }

        return [
            'ok' => true,
            'synced' => $synced,
            'skipped' => $skipped,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function runProgressiveBatch(bool $restart, int $batchSize = 100): array
    {
        if (!SiteConfig::bool('player_cdnvideohub_auto_enabled')) {
            return array_merge(CdnVideoHubBulkProgress::get(), [
                'status' => 'failed',
                'message' => 'Автодобавление CDN VideoHub выключено. Включите его и сохраните настройки.',
            ]);
        }

        @set_time_limit(120);

        $batchSize = max(1, min(200, $batchSize));
        $progress = CdnVideoHubBulkProgress::get();

        if (!$restart && in_array($progress['status'], ['paused', 'stopped'], true)) {
            return $progress;
        }

        $baseQuery = Series::query()
            ->whereNotNull('kp_id')
            ->where('kp_id', '!=', '');

        if ($restart || !in_array($progress['status'], ['running', 'paused'], true)) {
            $progress = CdnVideoHubBulkProgress::normalize([
                'status' => 'running',
                'after_id' => 0,
                'total' => (clone $baseQuery)->count(),
                'processed' => 0,
                'synced' => 0,
                'skipped' => 0,
                'failed' => 0,
                'batch_size' => $batchSize,
                'message' => 'Простановка CDN VideoHub запущена',
                'started_at' => time(),
                'finished_at' => null,
            ]);
            CdnVideoHubBulkProgress::save($progress);
        } else {
            $progress['status'] = 'running';
            $batchSize = $progress['batch_size'];
            CdnVideoHubBulkProgress::save($progress);
        }

        $out = [
            'last_id' => (int) $progress['after_id'],
            'processed' => 0,
            'synced' => 0,
            'skipped' => 0,
            'failed' => 0,
        ];

        $seriesList = (clone $baseQuery)
            ->where('id', '>', (int) $progress['after_id'])
            ->orderBy('id')
            ->limit($batchSize)
            ->get();

        foreach ($seriesList as $series) {
            $control = CdnVideoHubBulkProgress::get();
            if (in_array($control['status'], ['paused', 'stopped'], true)) {
                break;
            }

            $out['last_id'] = (int) $series->id;
            $out['processed']++;

            $kpId = trim((string) $series->kp_id);
            if ($kpId === '' || !preg_match('/^\d+$/', $kpId)) {
                $out['skipped']++;
                continue;
            }

            try {
                $this->syncSeries($series);
                $out['synced']++;
            } catch (\Throwable) {
                $out['failed']++;
            }
        }

        $latest = CdnVideoHubBulkProgress::get();
        if (in_array($latest['status'], ['paused', 'stopped'], true)) {
            $latest['after_id'] = $out['last_id'];
            $latest['processed'] = (int) $progress['processed'] + $out['processed'];
            $latest['synced'] = (int) $progress['synced'] + $out['synced'];
            $latest['skipped'] = (int) $progress['skipped'] + $out['skipped'];
            $latest['failed'] = (int) $progress['failed'] + $out['failed'];
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
            CdnVideoHubBulkProgress::save($latest);

            return $latest;
        }

        $progress['after_id'] = $out['last_id'];
        $progress['processed'] += $out['processed'];
        $progress['synced'] += $out['synced'];
        $progress['skipped'] += $out['skipped'];
        $progress['failed'] += $out['failed'];
        $progress['message'] = sprintf(
            'Обработано %d из %d',
            $progress['processed'],
            max($progress['total'], $progress['processed']),
        );

        $remaining = (clone $baseQuery)
            ->where('id', '>', $progress['after_id'])
            ->count();

        if ($remaining === 0) {
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

        CdnVideoHubBulkProgress::save($progress);

        return $progress;
    }

    public function pause(): array
    {
        $progress = CdnVideoHubBulkProgress::get();
        if ($progress['status'] !== 'running') {
            return $progress;
        }

        $progress['status'] = 'paused';
        $progress['message'] = sprintf(
            'Пауза: обработано %d из %d',
            $progress['processed'],
            max($progress['total'], $progress['processed']),
        );
        CdnVideoHubBulkProgress::save($progress);

        return $progress;
    }

    public function resume(): array
    {
        $progress = CdnVideoHubBulkProgress::get();
        if ($progress['status'] !== 'paused') {
            return $progress;
        }

        $progress['status'] = 'running';
        $progress['message'] = sprintf(
            'Продолжение: обработано %d из %d',
            $progress['processed'],
            max($progress['total'], $progress['processed']),
        );
        CdnVideoHubBulkProgress::save($progress);

        return $progress;
    }

    public function stop(): array
    {
        $progress = CdnVideoHubBulkProgress::get();
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
        CdnVideoHubBulkProgress::save($progress);

        return $progress;
    }

    public function syncSeries(Series $series): void
    {
        $kpId = trim((string) $series->kp_id);
        if ($kpId === '') {
            return;
        }

        $embedHtml = $this->buildEmbedHtml($kpId);
        if ($embedHtml === '') {
            return;
        }

        $tabName = trim(SiteConfig::str('player_cdnvideohub_tab_name')) ?: 'Смотреть онлайн';
        $priority = SiteConfig::int('player_cdnvideohub_priority');

        $payload = [
            'provider' => $tabName,
            'iframe_url' => $embedHtml,
            'is_active' => true,
            'source_key' => self::SOURCE_KEY,
        ];

        $existing = PlayerSource::query()
            ->where('series_id', $series->id)
            ->where('source_key', self::SOURCE_KEY)
            ->first();

        if ($existing) {
            // Keep manual tab order — configured priority applies only on first create.
            $existing->update($payload);
        } else {
            PlayerSource::query()->create(array_merge($payload, [
                'series_id' => $series->id,
                'priority' => $priority,
            ]));
        }

        $series->refresh();
        $firstUrl = PlayerUrlHelper::firstIframeUrlForSeries($series);
        $series->update(['player_url' => $firstUrl]);
    }

    public function buildEmbedHtml(string $kpId): string
    {
        $kpId = trim($kpId);
        if ($kpId === '' || !preg_match('/^\d+$/', $kpId)) {
            return '';
        }

        $elementId = $this->escapeAttr(trim(SiteConfig::str('player_cdnvideohub_element_id')) ?: 'cdnvideohubvideoplayer');
        $publisherId = $this->escapeAttr(trim(SiteConfig::str('player_cdnvideohub_publisher_id')) ?: '15');
        $aggregator = $this->escapeAttr(trim(SiteConfig::str('player_cdnvideohub_aggregator')) ?: 'kp');
        $scriptUrl = $this->escapeAttr(trim(SiteConfig::str('player_cdnvideohub_script_url'))
            ?: 'https://player.cdnvideohub.com/s2/stable/video-player.umd.js');

        if ($scriptUrl === '' || !preg_match('#^https?://#i', $scriptUrl)) {
            return '';
        }

        $showBanner = SiteConfig::bool('player_cdnvideohub_is_show_banner') ? 'true' : 'false';
        $showVoiceOnly = SiteConfig::bool('player_cdnvideohub_is_show_voice_only') ? 'true' : 'false';

        return sprintf(
            '<video-player id="%s" data-publisher-id="%s" is-show-banner="%s" is-show-voice-only="%s" data-title-id="%s" data-aggregator="%s"></video-player>' . "\n"
            . '<script async src="%s"></script>',
            $elementId,
            $publisherId,
            $showBanner,
            $showVoiceOnly,
            $this->escapeAttr($kpId),
            $aggregator,
            $scriptUrl,
        );
    }

    private function escapeAttr(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
