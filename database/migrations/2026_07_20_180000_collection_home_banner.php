<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            $table->string('home_banner_url')->nullable()->after('cover_url');
            $table->boolean('show_on_home')->default(false)->after('is_pinned');
        });
    }

    public function down(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            $table->dropColumn(['home_banner_url', 'show_on_home']);
        });
    }
};
