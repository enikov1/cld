<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('series', function (Blueprint $table) {
            $table->decimal('tmdb_popularity', 12, 4)->nullable()->after('tmdb_id');
            $table->timestamp('tmdb_popularity_refreshed_at')->nullable()->after('tmdb_popularity');
        });
    }

    public function down(): void
    {
        Schema::table('series', function (Blueprint $table) {
            $table->dropColumn(['tmdb_popularity', 'tmdb_popularity_refreshed_at']);
        });
    }
};
