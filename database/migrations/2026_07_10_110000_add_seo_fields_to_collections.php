<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            $table->string('meta_title')->nullable()->after('title');
            $table->text('seo_html')->nullable()->after('meta_description');
            $table->boolean('is_hidden')->default(false)->after('is_active');
            $table->boolean('noindex')->default(false)->after('is_hidden');
        });
    }

    public function down(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            $table->dropColumn(['meta_title', 'seo_html', 'is_hidden', 'noindex']);
        });
    }
};
