<?php

namespace App\Console\Commands;

use App\Models\CronRun;
use App\Services\AllohaAutoSyncSettings;
use App\Services\AllohaLatestSyncService;
use App\Services\CronRunLogger;
use App\Services\SitemapService;
use Illuminate\Console\Command;

class SyncAllohaLatest extends Command
{
    protected $signature = 'alloha:latest
        {--force : Запустить вне расписания}
        {--days= : Период в днях (переопределяет настройку)}
        {--trigger= : schedule|admin|cli}
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

        $trigger = CronRunLogger::detectTrigger($this->option('trigger'));
        if ($trigger === CronRun::TRIGGER_CLI && $this->option('force') && !$this->input->isInteractive()) {
            $trigger = CronRun::TRIGGER_SCHEDULE;
        }

        $this->info('Проверка последних добавлений Alloha (за ' . $settings['latest_days'] . ' дн.)...');

        $run = CronRunLogger::run(
            CronRunLogger::JOB_ALLOHA_LATEST,
            'alloha:latest',
            $trigger,
            function () use ($service, $settings) {
                $result = $service->run($settings, $this->output);
                AllohaAutoSyncSettings::markRun();

                if (($result['added'] + $result['updated']) > 0) {
                    app(SitemapService::class)->markDirty();
                }

                $message = sprintf(
                    'Добавлено: %d, обновлено: %d, пропущено: %d, ошибок: %d',
                    $result['added'],
                    $result['updated'],
                    $result['skipped'],
                    $result['failed'],
                );

                return [
                    'status' => $result['failed'] > 0 ? CronRun::STATUS_FAILED : CronRun::STATUS_SUCCESS,
                    'counts' => [
                        'added' => $result['added'],
                        'updated' => $result['updated'],
                        'skipped' => $result['skipped'],
                        'failed' => $result['failed'],
                        'kp_ids' => count($result['kp_ids']),
                    ],
                    'message' => $message,
                    'log' => $result['log'],
                ];
            },
            [
                'latest_days' => $settings['latest_days'],
                'force' => (bool)$this->option('force'),
            ],
            'Проверка последних добавлений Alloha',
        );

        $this->info((string)$run->message);

        return $run->status === CronRun::STATUS_FAILED ? self::FAILURE : self::SUCCESS;
    }
}
