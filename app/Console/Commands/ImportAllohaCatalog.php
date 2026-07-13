<?php

namespace App\Console\Commands;

use App\Models\Series;
use App\Services\AllohaClient;
use App\Services\AllohaConfig;
use App\Services\AllohaImportService;
use Illuminate\Console\Command;

class ImportAllohaCatalog extends Command
{
    protected $signature = 'alloha:import
        {--limit=50 : Сколько новых записей импортировать}
        {--download-poster : Скачать постер на сервер}
        {--sleep=0.5 : Пауза (сек) между запросами}
    ';

    protected $description = 'Import missing series from Alloha catalog by KP ID';

    public function handle(AllohaClient $client, AllohaImportService $importService): int
    {
        if (!AllohaConfig::isConfigured()) {
            $this->error('API-токен Alloha не настроен.');

            return self::FAILURE;
        }

        $limit = max(1, (int)$this->option('limit'));
        $sleep = (float)$this->option('sleep');
        $downloadPoster = (bool)$this->option('download-poster');

        $this->info('Загрузка каталога Alloha...');
        $catalogIds = $client->catalogKpIds();
        if ($catalogIds === []) {
            $this->warn('Каталог Alloha пуст или недоступен.');

            return self::SUCCESS;
        }

        $existingIds = Series::query()
            ->whereNotNull('kp_id')
            ->pluck('kp_id')
            ->map(fn ($id) => (string)$id)
            ->flip()
            ->all();

        $imported = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($catalogIds as $rawId) {
            if ($imported >= $limit) {
                break;
            }

            $kpId = (string)$rawId;
            if ($kpId === '' || isset($existingIds[$kpId])) {
                $skipped++;
                continue;
            }

            $result = $importService->importByKpId($kpId, [
                'download_poster' => $downloadPoster,
                'sync_players' => true,
                'sync_metadata' => true,
                'fill_empty_only' => false,
            ]);

            if (!$result['ok']) {
                $this->error("KP {$kpId}: {$result['error']}");
                $failed++;
                continue;
            }

            $title = $result['series']?->title ?? $kpId;
            $this->info("Импортирован: {$title} (KP {$kpId})");
            $existingIds[$kpId] = true;
            $imported++;

            if ($sleep > 0) {
                usleep((int)($sleep * 1_000_000));
            }
        }

        $this->info("Готово. Импортировано: {$imported}, пропущено: {$skipped}, ошибок: {$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
