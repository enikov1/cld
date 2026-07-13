<?php

namespace App\Console\Commands;

use App\Services\SeriesPopularityService;
use Illuminate\Console\Command;

class RefreshSeriesPopularityBadges extends Command
{
    protected $signature = 'series:refresh-popularity-badges';

    protected $description = 'Recalculate popular badge flags on series cards based on recent views';

    public function handle(): int
    {
        $updated = SeriesPopularityService::refreshPopularBadges();
        $this->info('Popular badges refreshed. Changed rows: ' . $updated);

        return self::SUCCESS;
    }
}
