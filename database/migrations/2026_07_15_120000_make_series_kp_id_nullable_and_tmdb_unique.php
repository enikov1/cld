<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('series', function (Blueprint $table) {
            $table->string('kp_id')->nullable()->change();
            $table->unique('tmdb_id');
        });
    }

    public function down(): void
    {
        Schema::table('series', function (Blueprint $table) {
            $table->dropUnique(['tmdb_id']);
            $table->string('kp_id')->nullable(false)->change();
        });
    }
};
