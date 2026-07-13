<?php

use App\Models\NavItem;
use App\Models\NavMegaButton;
use App\Models\NavMegaSection;
use App\Support\SiteConfig;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nav_items', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('link_type', 20)->default('category');
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('custom_url', 500)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('show_desktop')->default(true);
            $table->boolean('show_mobile')->default(true);
            $table->boolean('has_mega')->default(false);
            $table->foreignId('mega_filter_category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('nav_mega_buttons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nav_item_id')->constrained('nav_items')->cascadeOnDelete();
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->string('link_type', 20)->default('category');
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('custom_url', 500)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['nav_item_id', 'sort_order']);
        });

        Schema::create('nav_mega_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nav_item_id')->constrained('nav_items')->cascadeOnDelete();
            $table->string('title');
            $table->string('source_type', 20)->default('custom');
            $table->unsignedSmallInteger('item_limit')->default(14);
            $table->string('css_class', 50)->default('');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['nav_item_id', 'sort_order']);
        });

        Schema::create('nav_mega_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nav_mega_section_id')->constrained('nav_mega_sections')->cascadeOnDelete();
            $table->string('label');
            $table->string('url', 500);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['nav_mega_section_id', 'sort_order']);
        });

        $this->seedFromLegacyMenu();
        \App\Support\TplCache::bumpGlobalVersion();
    }

    public function down(): void
    {
        Schema::dropIfExists('nav_mega_links');
        Schema::dropIfExists('nav_mega_sections');
        Schema::dropIfExists('nav_mega_buttons');
        Schema::dropIfExists('nav_items');
    }

    private function seedFromLegacyMenu(): void
    {
        if (NavItem::query()->exists()) {
            return;
        }

        NavItem::query()->create([
            'title' => 'Главная',
            'link_type' => NavItem::LINK_HOME,
            'sort_order' => 5,
            'is_active' => true,
            'show_desktop' => false,
            'show_mobile' => true,
            'has_mega' => false,
        ]);

        $categories = DB::table('categories')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();

        $firstCategory = $categories->first();
        $sort = 0;

        foreach ($categories as $index => $category) {
            $sort += 10;
            $isMega = $index === 0;

            $navItem = NavItem::query()->create([
                'title' => $category->title,
                'link_type' => NavItem::LINK_CATEGORY,
                'category_id' => $category->id,
                'sort_order' => $sort,
                'is_active' => true,
                'show_desktop' => true,
                'show_mobile' => true,
                'has_mega' => $isMega,
                'mega_filter_category_id' => $isMega ? $firstCategory?->id : null,
            ]);

            if ($isMega && $firstCategory) {
                NavMegaButton::query()->create([
                    'nav_item_id' => $navItem->id,
                    'title' => 'Все ' . $category->title,
                    'subtitle' => 'Перейти в каталог',
                    'link_type' => NavItem::LINK_CATEGORY,
                    'category_id' => $category->id,
                    'sort_order' => 10,
                    'is_active' => true,
                ]);

                NavMegaButton::query()->create([
                    'nav_item_id' => $navItem->id,
                    'title' => 'Подборки',
                    'subtitle' => 'Тематические коллекции',
                    'link_type' => NavItem::LINK_COLLECTIONS,
                    'sort_order' => 20,
                    'is_active' => true,
                ]);

                NavMegaSection::query()->create([
                    'nav_item_id' => $navItem->id,
                    'title' => 'Жанры',
                    'source_type' => NavMegaSection::SOURCE_GENRES,
                    'item_limit' => SiteConfig::int('nav_mega_genres_limit'),
                    'css_class' => 'wide',
                    'sort_order' => 10,
                    'is_active' => true,
                ]);

                NavMegaSection::query()->create([
                    'nav_item_id' => $navItem->id,
                    'title' => 'Страны',
                    'source_type' => NavMegaSection::SOURCE_COUNTRIES,
                    'item_limit' => SiteConfig::int('nav_mega_countries_limit'),
                    'css_class' => 'wide',
                    'sort_order' => 20,
                    'is_active' => true,
                ]);
            }
        }

        NavItem::query()->create([
            'title' => 'Подборки',
            'link_type' => NavItem::LINK_COLLECTIONS,
            'sort_order' => $sort + 10,
            'is_active' => true,
            'show_desktop' => true,
            'show_mobile' => true,
            'has_mega' => false,
        ]);
    }
};
