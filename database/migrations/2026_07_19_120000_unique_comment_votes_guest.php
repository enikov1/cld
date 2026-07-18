<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop duplicate guest votes (keep newest) before adding unique.
        $duplicates = DB::table('comment_votes')
            ->select('comment_id', 'voter_key', DB::raw('MAX(id) as keep_id'))
            ->whereNotNull('voter_key')
            ->groupBy('comment_id', 'voter_key')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $row) {
            DB::table('comment_votes')
                ->where('comment_id', $row->comment_id)
                ->where('voter_key', $row->voter_key)
                ->where('id', '!=', $row->keep_id)
                ->delete();
        }

        Schema::table('comment_votes', function (Blueprint $table) {
            $table->dropIndex(['comment_id', 'voter_key']);
            $table->unique(['comment_id', 'voter_key'], 'comment_votes_comment_id_voter_key_unique');
        });
    }

    public function down(): void
    {
        Schema::table('comment_votes', function (Blueprint $table) {
            $table->dropUnique('comment_votes_comment_id_voter_key_unique');
            $table->index(['comment_id', 'voter_key']);
        });
    }
};
