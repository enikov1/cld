<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nav_mega_sections', function (Blueprint $table) {
            $table->string('item_sort', 20)->default('name')->after('item_limit');
        });
    }

    public function down(): void
    {
        Schema::table('nav_mega_sections', function (Blueprint $table) {
            $table->dropColumn('item_sort');
        });
    }
};
