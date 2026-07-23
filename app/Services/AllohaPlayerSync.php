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

        $seenTranslationIds = [];
        $nextNewPriority = null;

        foreach ($translations as $translation) {
            if (!is_array($translation)) {
                continue;
            }

            $iframe = PlayerUrlHelper::normalizePlayerContent((string) ($translation['iframe'] ?? ''));
            if ($iframe === '') {
                continue;
            }

            $translationId = isset($translation['id']) ? (int) $translation['id'] : null;
            if (!$translationId) {
                // Skip nameless translations — creating without id duplicates on every sync.
                continue;
            }

            $provider = trim((string) ($translation['name'] ?? '')) ?: 'Озвучка';

            $payload = [
                'provider' => $provider,
                'iframe_url' => $iframe,
                'is_active' => true,
                'source_key' => self::SOURCE_KEY,
            ];

            $seenTranslationIds[] = $translationId;

            $existing = PlayerSource::query()
                ->where('series_id', $series->id)
                ->where('alloha_translation_id', $translationId)
                ->first();

            if ($existing) {
                // Keep manual tab order — never rewrite priority on update.
                $existing->update($payload);
                continue;
            }

            if ($nextNewPriority === null) {
                $minPriority = PlayerSource::query()
                    ->where('series_id', $series->id)
                    ->min('priority');
                $nextNewPriority = $minPriority !== null ? ((int) $minPriority - 10) : 100;
            }

            PlayerSource::query()->create(array_merge($payload, [
                'series_id' => $series->id,
                'alloha_translation_id' => $translationId,
                'priority' => $nextNewPriority,
            ]));
            $nextNewPriority -= 10;
        }

        // Remove Alloha voices that disappeared from the API payload.
        if ($seenTranslationIds !== []) {
            PlayerSource::query()
                ->where('series_id', $series->id)
                ->whereNotNull('alloha_translation_id')
                ->whereNotIn('alloha_translation_id', $seenTranslationIds)
                ->delete();
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
