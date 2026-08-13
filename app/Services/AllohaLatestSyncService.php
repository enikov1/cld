<?php

namespace App\Services;

use App\Models\Series;
use Illuminate\Console\OutputStyle;

class AllohaLatestSyncService
{
    public function __construct(
        private readonly AllohaClient $client,
        private readonly AllohaImportService $importService,
    ) {
    }

    /**
     * @return array{added: int, updated: int, skipped: int, failed: int, kp_ids: list<string>, log: list<string>}
     */
    public function run(?array $settings = null, ?OutputStyle $output = null): array
    {
        $settings = AllohaAutoSyncSettings::normalize($settings ?? AllohaAutoSyncSettings::get());

        $result = [
            'added' => 0,
            'updated' => 0,
            'skipped' => 0,
            'failed' => 0,
            'kp_ids' => [],
            'log' => [],
        ];

        if (!$this->client->isConfigured()) {
            $result['log'][] = 'API-токен Alloha не настроен.';

            return $result;
        }

        $latestItems = $this->client->fetchAllLatest((int)$settings['latest_days']);
        if ($latestItems === []) {
            $result['log'][] = 'Нет новых добавлений за выбранный период.';

            return $result;
        }

        $kpIds = $this->extractUniqueKpIds($latestItems);
        $result['kp_ids'] = $kpIds;
        $result['log'][] = 'Найдено уникальных KP ID: ' . count($kpIds);

        foreach ($kpIds as $kpId) {
            $existing = Series::query()->withTrashed()->where('kp_id', $kpId)->first();
            $isNew = !$existing;

            if ($isNew && !$settings['auto_add_new']) {
                $result['skipped']++;
                $result['log'][] = "Пропуск нового KP {$kpId}: автодобавление выключено";
                continue;
            }

            if (!$isNew && !$settings['update_existing']) {
                $result['skipped']++;
                $result['log'][] = "Пропуск существующего KP {$kpId}: обновление выключено";
                continue;
            }

            $flags = AllohaAutoSyncSettings::toImportFlags($settings, $isNew);
            if (!$flags['sync_ratings'] && !$flags['sync_players'] && !$flags['sync_metadata']
                && !$flags['sync_poster'] && !$flags['sync_genres_countries'] && !$flags['bump_date']) {
                $result['skipped']++;
                $result['log'][] = "Пропуск KP {$kpId}: не выбраны поля для обновления";
                continue;
            }

            $importOptions = array_merge($flags, [
                'download_poster' => $isNew ? $settings['download_poster_new'] : $settings['update_poster'],
            ]);

            if ($isNew) {
                $importOptions['is_active'] = $settings['new_is_active'];
                $importOptions['is_hidden'] = $settings['new_is_hidden'];
            }

            $importResult = $this->importService->importByKpId($kpId, $importOptions);

            if (!$importResult['ok']) {
                $result['failed']++;
                $message = "KP {$kpId}: " . ($importResult['error'] ?? 'ошибка');
                $result['log'][] = $message;
                $output?->error($message);
                continue;
            }

            if ($isNew) {
                $result['added']++;
                $title = $importResult['series']?->title ?? $kpId;
                $message = "Добавлен: {$title} (KP {$kpId})";
            } else {
                $result['updated']++;
                $title = $importResult['series']?->title ?? $kpId;
                $message = "Обновлён: {$title} (KP {$kpId})";
            }

            $result['log'][] = $message;
            $output?->info($message);
        }

        return $result;
    }

    /**
     * @param list<array<string,mixed>> $items
     * @return list<string>
     */
    private function extractUniqueKpIds(array $items): array
    {
        $ids = [];

        foreach ($items as $item) {
            $idsBlock = $item['ids'] ?? [];
            $kpId = is_array($idsBlock) ? ($idsBlock['kp'] ?? null) : null;
            if ($kpId === null || (string)$kpId === '') {
                continue;
            }

            $ids[(string)$kpId] = true;
        }

        return array_keys($ids);
    }
}
