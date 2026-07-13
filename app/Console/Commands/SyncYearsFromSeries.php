<?php

namespace App\Console\Commands;

use App\Services\TaxonomyService;
use Illuminate\Console\Command;

class SyncYearsFromSeries extends Command
{
    protected $signature = 'years:sync-from-series';

    protected $description = 'Create missing year taxonomy entries from imported series';

    public function handle(TaxonomyService $taxonomyService): int
    {
        $created = $taxonomyService->syncMissingYearsFromSeries();
        $this->info("Created year entries: {$created}");

        return self::SUCCESS;
    }
}
