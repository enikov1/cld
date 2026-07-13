<?php

use App\Models\Category;
use App\Models\HomeSection;
use App\Models\Series;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('home_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('title');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('item_limit')->default(18);
            $table->boolean('show_tabs')->default(true);
            $table->string('default_sort', 20)->default('latest');
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        $wrong = Category::query()->where('slug', 'zarubeznye-serialy')->first();
        $correct = Category::query()->where('slug', 'zarubezhnye-serialy')->first();
        if ($wrong && $correct && $wrong->id !== $correct->id) {
            Series::query()->where('category_id', $wrong->id)->update(['category_id' => $correct->id]);
            $wrong->delete();
        }

        $sort = 0;
        foreach (
            Category::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('title')
                ->get() as $cat
        ) {
            $sort += 10;
            HomeSection::query()->create([
                'category_id' => $cat->id,
                'title' => $cat->title,
                'sort_order' => $sort,
                'is_active' => true,
                'item_limit' => 18,
                'show_tabs' => true,
                'default_sort' => 'latest',
            ]);
        }

        \App\Support\TplCache::forgetHome();
    }

    public function down(): void
    {
        Schema::dropIfExists('home_sections');
    }
};
