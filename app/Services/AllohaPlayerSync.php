<?php

namespace App\Services;

use App\Models\PlayerSource;
use App\Models\Series;
use App\Support\PlayerUrlHelper;
use App\Support\SiteConfig;

class AllohaPlayerSync
{
    public const SOURCE_KEY = 'alloha';

    public static function isEnabled(): bool
    {
        return SiteConfig::bool('player_alloha_sync_enabled');
    }

    /**
     * @param list<array<string,mixed>> $translations
     */
    public function sync(Series $series, array $translations, ?string $defaultIframe = null): void
    {
        if (!self::isEnabled()) {
            $this->removeForSeries($series);

            return;
        }

        $priority = 100;
        $seenTranslationIds = [];

        foreach ($translations as $translation) {
            if (!is_array($translation)) {
                continue;
            }

            $iframe = PlayerUrlHelper::normalizePlayerContent((string)($translation['iframe'] ?? ''));
            if ($iframe === '') {
                continue;
            }

            $translationId = isset($translation['id']) ? (int)$translation['id'] : null;
            $provider = trim((string)($translation['name'] ?? '')) ?: 'Озвучка';

            $payload = [
                'provider' => $provider,
                'iframe_url' => $iframe,
                'is_active' => true,
                'priority' => $priority,
                'source_key' => self::SOURCE_KEY,
            ];
            $priority -= 10;

            if ($translationId) {
                $seenTranslationIds[] = $translationId;
                $source = PlayerSource::query()
                    ->where('series_id', $series->id)
                    ->where('alloha_translation_id', $translationId)
                    ->first();

                if ($source) {
                    $source->update($payload);
                    continue;
                }

                PlayerSource::query()->create(array_merge($payload, [
                    'series_id' => $series->id,
                    'alloha_translation_id' => $translationId,
                ]));
                continue;
            }

            PlayerSource::query()->create(array_merge($payload, [
                'series_id' => $series->id,
            ]));
        }

        if ($defaultIframe) {
            $defaultIframe = PlayerUrlHelper::normalizePlayerContent($defaultIframe);
            if ($defaultIframe !== '' && !$series->playerSources()->exists()) {
                PlayerSource::query()->create([
                    'series_id' => $series->id,
                    'source_key' => self::SOURCE_KEY,
                    'provider' => 'Смотреть онлайн',
                    'iframe_url' => $defaultIframe,
                    'is_active' => true,
                    'priority' => 100,
                ]);
            }
        }

        $series->refresh();
        $this->syncLegacyPlayerUrl($series);
    }

    public function removeForSeries(Series $series): void
    {
        $deleted = PlayerSource::query()
            ->where('series_id', $series->id)
            ->where(function ($query) {
                $query->where('source_key', self::SOURCE_KEY)
                    ->orWhereNotNull('alloha_translation_id');
            })
            ->delete();

        if ($deleted === 0) {
            return;
        }

        $series->refresh();
        $this->syncLegacyPlayerUrl($series);
    }

    private function syncLegacyPlayerUrl(Series $series): void
    {
        $activePlayers = PlayerUrlHelper::activePlayersForSeries($series);
        if ($activePlayers === []) {
            $series->update(['player_url' => null]);

            return;
        }

        $series->update(['player_url' => PlayerUrlHelper::firstIframeUrlForSeries($series)]);
    }
}
