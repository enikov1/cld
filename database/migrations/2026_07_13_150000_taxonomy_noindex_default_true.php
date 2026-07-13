<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['genres', 'countries', 'people', 'years'] as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'noindex')) {
                continue;
            }

            DB::table($table)->update(['noindex' => true]);

            $driver = Schema::getConnection()->getDriverName();
            if ($driver === 'mysql') {
                DB::statement("ALTER TABLE `{$table}` MODIFY `noindex` TINYINT(1) NOT NULL DEFAULT 1");
            } elseif ($driver === 'pgsql') {
                DB::statement("ALTER TABLE {$table} ALTER COLUMN noindex SET DEFAULT true");
            } elseif ($driver === 'sqlite') {
                // SQLite cannot alter column defaults in place; model defaults cover new rows.
            }
        }
    }

    public function down(): void
    {
        foreach (['genres', 'countries', 'people', 'years'] as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'noindex')) {
                continue;
            }

            $driver = Schema::getConnection()->getDriverName();
            if ($driver === 'mysql') {
                DB::statement("ALTER TABLE `{$table}` MODIFY `noindex` TINYINT(1) NOT NULL DEFAULT 0");
            } elseif ($driver === 'pgsql') {
                DB::statement("ALTER TABLE {$table} ALTER COLUMN noindex SET DEFAULT false");
            }
        }
    }
};
