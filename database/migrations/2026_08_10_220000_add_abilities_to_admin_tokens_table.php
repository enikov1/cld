<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_tokens', function (Blueprint $table) {
            $table->json('abilities')->nullable()->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('admin_tokens', function (Blueprint $table) {
            $table->dropColumn('abilities');
        });
    }
};
