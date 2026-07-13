<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('studios', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique()->index();
            $table->string('title');
            $table->string('meta_title')->nullable();
            $table->text('description')->nullable();
            $table->text('meta_description')->nullable();
            $table->text('seo_html')->nullable();
            $table->string('logo_url')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_hidden')->default(false);
            $table->boolean('noindex')->default(false);
            $table->timestamps();
        });

        Schema::create('studio_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('studio_id')->constrained('studios')->cascadeOnDelete();
            $table->foreignId('series_id')->constrained('series')->cascadeOnDelete();
            $table->unsignedInteger('rank_order')->default(0);
            $table->unique(['studio_id', 'series_id']);
            $table->timestamps();
        });

        Schema::table('series', function (Blueprint $table) {
            $table->foreignId('studio_id')->nullable()->after('category_id')->constrained('studios')->nullOnDelete();
        });

        Schema::table('collections', function (Blueprint $table) {
            $table->foreignId('studio_id')->nullable()->after('title')->constrained('studios')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            $table->dropConstrainedForeignId('studio_id');
        });

        Schema::table('series', function (Blueprint $table) {
            $table->dropConstrainedForeignId('studio_id');
        });

        Schema::dropIfExists('studio_items');
        Schema::dropIfExists('studios');
    }
};
