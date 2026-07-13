<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('series', function (Blueprint $table) {
            $table->string('alloha_token', 64)->nullable()->after('kp_web_url');
        });

        Schema::table('player_sources', function (Blueprint $table) {
            $table->unsignedInteger('alloha_translation_id')->nullable()->index()->after('provider');
        });
    }

    public function down(): void
    {
        Schema::table('series', function (Blueprint $table) {
            $table->dropColumn('alloha_token');
        });

        Schema::table('player_sources', function (Blueprint $table) {
            $table->dropColumn('alloha_translation_id');
        });
    }
};
