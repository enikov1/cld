<?php

namespace App\Console\Commands;

use App\Models\Series;
use App\Services\AllohaClient;
use App\Services\AllohaConfig;
use App\Services\AllohaImportService;
use Illuminate\Console\Command;

class SyncAlloha extends Command
{
    protected $signature = 'alloha:sync
        {--kp-id= : Синхронизировать один сериал по KP ID}
        {--ratings-only : Обновить только рейтинги}
        {--players-only : Обновить только плееры}
        {--sleep=0 : Пауза (сек) между запросами}
    ';

    protected $description = 'Sync series metadata and players from Alloha API';

    public function handle(AllohaImportService $importService, AllohaClient $client): int
    {
        if (!AllohaConfig::isConfigured()) {
            $this->error('API-токен Alloha не настроен. Укажите его в админке: Настройки → Интеграции.');

            return self::FAILURE;
        }

        $kpId = $this->option('kp-id');
        $ratingsOnly = (bool)$this->option('ratings-only');
        $playersOnly = (bool)$this->option('players-only');
        $sleep = (float)$this->option('sleep');

        $syncMetadata = !$playersOnly;
        $syncPlayers = !$ratingsOnly;

        if ($ratingsOnly && $playersOnly) {
            $this->error('Нельзя одновременно использовать --ratings-only и --players-only.');

            return self::FAILURE;
        }

        $query = Series::query()->whereNotNull('kp_id')->where('kp_id', '!=', '');
        if ($kpId) {
            $query->where('kp_id', (string)$kpId);
        }

        $seriesList = $query->orderBy('id')->get();
        if ($seriesList->isEmpty()) {
            $this->warn('Нет сериалов с KP ID для синхронизации.');

            return self::SUCCESS;
        }

        $updated = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($seriesList as $series) {
            $exists = $client->movieExists($series->kp_id);
            if (!$exists['exists']) {
                $this->line("Пропуск KP {$series->kp_id}: не найден в Alloha");
                $skipped++;
                continue;
            }

            $result = $importService->importByKpId((string)$series->kp_id, [
                'download_poster' => false,
                'sync_players' => $syncPlayers,
                'sync_metadata' => $syncMetadata,
                'fill_empty_only' => true,
                'ratings_only' => $ratingsOnly,
            ]);

            if (!$result['ok']) {
                $this->error("KP {$series->kp_id}: {$result['error']}");
                $failed++;
                continue;
            }

            $this->info("Обновлён: {$series->title} (KP {$series->kp_id})");
            $updated++;

            if ($sleep > 0) {
                usleep((int)($sleep * 1_000_000));
            }
        }

        $this->info("Готово. Обновлено: {$updated}, пропущено: {$skipped}, ошибок: {$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
