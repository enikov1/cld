<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('series_reaction_votes')) {
            return;
        }

        Schema::table('series_reaction_votes', function (Blueprint $table) {
            $table->index(['created_at', 'reaction_type_id'], 'srv_created_type_idx');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('series_reaction_votes')) {
            return;
        }

        Schema::table('series_reaction_votes', function (Blueprint $table) {
            $table->dropIndex('srv_created_type_idx');
        });
    }
};
