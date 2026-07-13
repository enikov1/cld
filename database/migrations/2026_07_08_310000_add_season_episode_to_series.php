<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('series', function (Blueprint $table) {
            $table->unsignedSmallInteger('season_number')->nullable()->after('broadcast_status');
            $table->unsignedSmallInteger('last_episode_number')->nullable()->after('season_number');
        });
    }

    public function down(): void
    {
        Schema::table('series', function (Blueprint $table) {
            $table->dropColumn(['season_number', 'last_episode_number']);
        });
    }
};
