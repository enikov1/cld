<?php

namespace App\Console\Commands;

use App\Models\CronRun;
use App\Services\CronRunLogger;
use App\Services\SeriesPopularityService;
use Illuminate\Console\Command;

class RefreshSeriesPopularityBadges extends Command
{
    protected $signature = 'series:refresh-popularity-badges {--trigger= : schedule|admin|cli}';

    protected $description = 'Recalculate popular badge flags on series cards based on recent views';

    public function handle(): int
    {
        $trigger = CronRunLogger::detectTrigger($this->option('trigger') ?: CronRun::TRIGGER_SCHEDULE);

        $run = CronRunLogger::run(
            CronRunLogger::JOB_POPULAR_BADGES,
            'series:refresh-popularity-badges',
            $trigger,
            function () {
                $updated = SeriesPopularityService::refreshPopularBadges();

                return [
                    'status' => CronRun::STATUS_SUCCESS,
                    'counts' => ['changed' => $updated],
                    'message' => 'Изменено записей: ' . $updated,
                ];
            },
            [],
            'Пересчёт бейджей «Популярно»',
        );

        $this->info((string)$run->message);

        return self::SUCCESS;
    }
}
