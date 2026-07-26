<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PruneExpiredCache extends Command
{
    protected $signature = 'cache:prune-expired
                            {--chunk=1000 : Rows to delete per batch}
                            {--max=50000 : Max rows to delete in one run (0 = unlimited)}';

    protected $description = 'Delete expired rows from the database cache table';

    public function handle(): int
    {
        if ((string)config('cache.default') !== 'database') {
            $this->info('Default cache store is not database — nothing to prune.');

            return self::SUCCESS;
        }

        $table = (string)config('cache.stores.database.table', 'cache');
        if (!Schema::hasTable($table)) {
            $this->warn("Cache table [{$table}] does not exist.");

            return self::SUCCESS;
        }

        $chunk = max(1, (int)$this->option('chunk'));
        $max = max(0, (int)$this->option('max'));
        $now = time();
        $deleted = 0;

        do {
            $limit = $chunk;
            if ($max > 0) {
                $remaining = $max - $deleted;
                if ($remaining <= 0) {
                    break;
                }
                $limit = min($chunk, $remaining);
            }

            // Batch deletes avoid long locks on a bloated cache table.
            $batch = DB::table($table)
                ->where('expiration', '>', 0)
                ->where('expiration', '<=', $now)
                ->limit($limit)
                ->delete();

            $deleted += $batch;
        } while ($batch > 0);

        $locksTable = (string)config('cache.stores.database.lock_table', 'cache_locks');
        $locksDeleted = 0;
        if (Schema::hasTable($locksTable)) {
            $locksDeleted = (int)DB::table($locksTable)
                ->where('expiration', '>', 0)
                ->where('expiration', '<=', $now)
                ->delete();
        }

        $this->info("Pruned {$deleted} expired cache row(s)" . ($locksDeleted ? ", {$locksDeleted} lock(s)" : '') . '.');

        return self::SUCCESS;
    }
}
