<?php

namespace App\Services;

use App\Models\PlayerSource;
use App\Models\Series;
use App\Support\PlayerUrlHelper;

class AllohaPlayerSync
{
    /**
     * @param list<array<string,mixed>> $translations
     */
    public function sync(Series $series, array $translations, ?string $defaultIframe = null): void
    {
        $priority = 100;
        $seenTranslationIds = [];

        foreach ($translations as $translation) {
            if (!is_array($translation)) {
                continue;
            }

            $iframe = PlayerUrlHelper::sanitize((string)($translation['iframe'] ?? ''));
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
            $defaultIframe = PlayerUrlHelper::sanitize($defaultIframe);
            if ($defaultIframe !== '' && !$series->playerSources()->exists()) {
                PlayerSource::query()->create([
                    'series_id' => $series->id,
                    'provider' => 'Смотреть онлайн',
                    'iframe_url' => $defaultIframe,
                    'is_active' => true,
                    'priority' => 100,
                ]);
            }
        }

        $series->refresh();
        $firstUrl = PlayerUrlHelper::activePlayersForSeries($series)[0]['url'] ?? null;
        if ($firstUrl) {
            $series->update(['player_url' => $firstUrl]);
        }
    }
}
