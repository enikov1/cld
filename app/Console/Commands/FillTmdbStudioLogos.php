<?php

namespace App\Console\Commands;

use App\Services\TmdbConfig;
use App\Services\TmdbStudioSyncService;
use Illuminate\Console\Command;

class FillTmdbStudioLogos extends Command
{
    protected $signature = 'tmdb:fill-studio-logos {--limit=200 : Max studios without logo to process}';

    protected $description = 'Download missing studio logos from TMDB network/company details';

    public function handle(TmdbStudioSyncService $studioSync): int
    {
        if (!TmdbConfig::isConfigured()) {
            $this->error('API-ключ TMDB не настроен');

            return self::FAILURE;
        }

        $limit = max(1, (int)$this->option('limit'));
        $result = $studioSync->fillMissingLogos($limit);

        $this->info(sprintf(
            'Проверено: %d, скачано: %d, без лого: %d',
            $result['checked'],
            $result['downloaded'],
            $result['failed'],
        ));

        return self::SUCCESS;
    }
}
