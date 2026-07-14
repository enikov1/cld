<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('studios', function (Blueprint $table) {
            $table->unsignedInteger('tmdb_id')->nullable()->after('id');
            $table->string('tmdb_type', 16)->nullable()->after('tmdb_id');
            $table->unique(['tmdb_type', 'tmdb_id']);
        });
    }

    public function down(): void
    {
        Schema::table('studios', function (Blueprint $table) {
            $table->dropUnique(['tmdb_type', 'tmdb_id']);
            $table->dropColumn(['tmdb_id', 'tmdb_type']);
        });
    }
};
