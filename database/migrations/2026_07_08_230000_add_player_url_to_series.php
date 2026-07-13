<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('series', function (Blueprint $table) {
            $table->text('player_url')->nullable()->after('poster_url');
        });

        if (Schema::hasTable('player_sources')) {
            $rows = DB::table('player_sources')
                ->where('is_active', true)
                ->orderByDesc('priority')
                ->orderBy('id')
                ->get(['series_id', 'iframe_url']);

            $bySeries = [];
            foreach ($rows as $row) {
                if (!isset($bySeries[$row->series_id]) && trim((string)$row->iframe_url) !== '') {
                    $bySeries[$row->series_id] = $row->iframe_url;
                }
            }

            foreach ($bySeries as $seriesId => $url) {
                DB::table('series')
                    ->where('id', $seriesId)
                    ->where(function ($q) {
                        $q->whereNull('player_url')->orWhere('player_url', '');
                    })
                    ->update(['player_url' => $url]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('series', function (Blueprint $table) {
            $table->dropColumn('player_url');
        });
    }
};
