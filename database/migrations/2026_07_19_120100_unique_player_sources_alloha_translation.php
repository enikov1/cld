<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Keep newest row per (series_id, alloha_translation_id), drop duplicates.
        $duplicates = DB::table('player_sources')
            ->select('series_id', 'alloha_translation_id', DB::raw('MAX(id) as keep_id'))
            ->whereNotNull('alloha_translation_id')
            ->groupBy('series_id', 'alloha_translation_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $row) {
            DB::table('player_sources')
                ->where('series_id', $row->series_id)
                ->where('alloha_translation_id', $row->alloha_translation_id)
                ->where('id', '!=', $row->keep_id)
                ->delete();
        }

        Schema::table('player_sources', function (Blueprint $table) {
            $table->unique(
                ['series_id', 'alloha_translation_id'],
                'player_sources_series_alloha_translation_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('player_sources', function (Blueprint $table) {
            $table->dropUnique('player_sources_series_alloha_translation_unique');
        });
    }
};
