<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['genres', 'countries', 'people'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->string('meta_title')->nullable()->after('name');
                $table->text('meta_description')->nullable()->after('meta_title');
                $table->text('seo_html')->nullable()->after('meta_description');
                $table->boolean('is_hidden')->default(false)->after('is_active');
                $table->boolean('noindex')->default(false)->after('is_hidden');
            });
        }

        Schema::table('categories', function (Blueprint $table) {
            $table->string('meta_title')->nullable()->after('title');
            $table->boolean('is_hidden')->default(false)->after('is_active');
            $table->boolean('noindex')->default(false)->after('is_hidden');
        });

        Schema::table('series', function (Blueprint $table) {
            $table->string('meta_title')->nullable()->after('title');
            $table->text('meta_description')->nullable()->after('meta_title');
            $table->boolean('is_hidden')->default(false)->after('is_active');
            $table->boolean('noindex')->default(false)->after('is_hidden');
        });
    }

    public function down(): void
    {
        foreach (['genres', 'countries', 'people'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn(['meta_title', 'meta_description', 'seo_html', 'is_hidden', 'noindex']);
            });
        }

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['meta_title', 'is_hidden', 'noindex']);
        });

        Schema::table('series', function (Blueprint $table) {
            $table->dropColumn(['meta_title', 'meta_description', 'is_hidden', 'noindex']);
        });
    }
};
