<?php

namespace App\Console\Commands;

use App\Services\AllohaAutoSyncSettings;
use App\Services\AllohaLatestSyncService;
use App\Services\SitemapService;
use Illuminate\Console\Command;

class SyncAllohaLatest extends Command
{
    protected $signature = 'alloha:latest
        {--force : Запустить вне расписания}
        {--days= : Период в днях (переопределяет настройку)}
    ';

    protected $description = 'Check Alloha /v2/movies/latest and add or update content';

    public function handle(AllohaLatestSyncService $service): int
    {
        $settings = AllohaAutoSyncSettings::get();

        if ($this->option('days') !== null) {
            $settings['latest_days'] = max(1, min(30, (int)$this->option('days')));
        }

        if (!$this->option('force') && !AllohaAutoSyncSettings::isDue() && !$this->input->isInteractive()) {
            $this->line('Автосинхронизация ещё не due.');

            return self::SUCCESS;
        }

        $this->info('Проверка последних добавлений Alloha (за ' . $settings['latest_days'] . ' дн.)...');

        $result = $service->run($settings, $this->output);
        AllohaAutoSyncSettings::markRun();

        $this->info("Добавлено: {$result['added']}, обновлено: {$result['updated']}, пропущено: {$result['skipped']}, ошибок: {$result['failed']}");

        if (($result['added'] + $result['updated']) > 0) {
            app(SitemapService::class)->markDirty();
        }

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
