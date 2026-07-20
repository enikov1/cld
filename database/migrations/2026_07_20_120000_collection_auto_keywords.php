<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            $table->boolean('auto_add_enabled')->default(false)->after('noindex');
            $table->json('auto_keywords')->nullable()->after('auto_add_enabled');
        });

        Schema::table('collection_items', function (Blueprint $table) {
            $table->boolean('is_auto')->default(false)->after('rank_order');
        });
    }

    public function down(): void
    {
        Schema::table('collection_items', function (Blueprint $table) {
            $table->dropColumn('is_auto');
        });

        Schema::table('collections', function (Blueprint $table) {
            $table->dropColumn(['auto_add_enabled', 'auto_keywords']);
        });
    }
};
