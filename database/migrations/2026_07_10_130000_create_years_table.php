<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('years', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->text('seo_html')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_hidden')->default(false);
            $table->boolean('noindex')->default(false);
            $table->timestamps();
        });

        $years = DB::table('series')
            ->selectRaw('DISTINCT COALESCE(NULLIF(year, 0), start_year) AS y')
            ->whereNotNull(DB::raw('COALESCE(NULLIF(year, 0), start_year)'))
            ->orderByDesc('y')
            ->pluck('y')
            ->filter(fn ($y) => (int)$y >= 1900 && (int)$y <= 2100)
            ->unique()
            ->values();

        $now = now();
        foreach ($years as $year) {
            $year = (string)(int)$year;
            DB::table('years')->insert([
                'slug' => $year,
                'name' => $year,
                'sort_order' => (int)$year,
                'is_active' => true,
                'is_hidden' => false,
                'noindex' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('years');
    }
};
