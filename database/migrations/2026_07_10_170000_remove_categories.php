<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('home_sections') && Schema::hasColumn('home_sections', 'category_id')) {
            Schema::table('home_sections', function (Blueprint $table) {
                $table->dropForeign(['category_id']);
                $table->dropColumn('category_id');
            });
        }

        if (Schema::hasTable('nav_mega_buttons') && Schema::hasColumn('nav_mega_buttons', 'category_id')) {
            Schema::table('nav_mega_buttons', function (Blueprint $table) {
                $table->dropForeign(['category_id']);
                $table->dropColumn('category_id');
            });
        }

        if (Schema::hasTable('nav_items')) {
            Schema::table('nav_items', function (Blueprint $table) {
                if (Schema::hasColumn('nav_items', 'category_id')) {
                    $table->dropForeign(['category_id']);
                    $table->dropColumn('category_id');
                }
                if (Schema::hasColumn('nav_items', 'mega_filter_category_id')) {
                    $table->dropForeign(['mega_filter_category_id']);
                    $table->dropColumn('mega_filter_category_id');
                }
            });
        }

        if (Schema::hasColumn('series', 'category_id')) {
            Schema::table('series', function (Blueprint $table) {
                $table->dropForeign(['category_id']);
                $table->dropColumn('category_id');
            });
        }

        Schema::dropIfExists('categories');
    }

    public function down(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('meta_title')->nullable();
            $table->text('seo_html')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_hidden')->default(false);
            $table->boolean('noindex')->default(false);
            $table->timestamps();
        });

        Schema::table('series', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('id')->constrained('categories')->nullOnDelete();
        });
    }
};
