<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('series', function (Blueprint $table) {
            $table->date('finale_date')->nullable()->after('premiere_date');
            $table->unsignedInteger('duration_minutes')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('series', function (Blueprint $table) {
            $table->dropColumn('finale_date');
            $table->unsignedSmallInteger('duration_minutes')->nullable()->change();
        });
    }
};
