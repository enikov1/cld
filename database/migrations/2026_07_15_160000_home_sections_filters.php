<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('home_sections')) {
            return;
        }

        Schema::table('home_sections', function (Blueprint $table) {
            if (! Schema::hasColumn('home_sections', 'filters')) {
                $table->json('filters')->nullable()->after('title');
            }
            if (! Schema::hasColumn('home_sections', 'link_url')) {
                $table->string('link_url', 500)->nullable()->after('filters');
            }
        });

        \App\Support\TplCache::forgetHome();
    }

    public function down(): void
    {
        if (! Schema::hasTable('home_sections')) {
            return;
        }

        Schema::table('home_sections', function (Blueprint $table) {
            if (Schema::hasColumn('home_sections', 'link_url')) {
                $table->dropColumn('link_url');
            }
            if (Schema::hasColumn('home_sections', 'filters')) {
                $table->dropColumn('filters');
            }
        });
    }
};
