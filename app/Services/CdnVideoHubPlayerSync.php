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
            'priority' => $priority,
            'source_key' => self::SOURCE_KEY,
        ];

        PlayerSource::query()->updateOrCreate(
            [
                'series_id' => $series->id,
                'source_key' => self::SOURCE_KEY,
            ],
            $payload
        );

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
