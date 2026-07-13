<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('search_queries')) {
            return;
        }

        Schema::table('search_queries', function (Blueprint $table) {
            if (!Schema::hasColumn('search_queries', 'suggest_hits')) {
                $table->unsignedInteger('suggest_hits')->default(0)->after('hits');
            }
            if (!Schema::hasColumn('search_queries', 'full_hits')) {
                $table->unsignedInteger('full_hits')->default(0)->after('suggest_hits');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('search_queries')) {
            return;
        }

        Schema::table('search_queries', function (Blueprint $table) {
            if (Schema::hasColumn('search_queries', 'full_hits')) {
                $table->dropColumn('full_hits');
            }
            if (Schema::hasColumn('search_queries', 'suggest_hits')) {
                $table->dropColumn('suggest_hits');
            }
        });
    }
};
