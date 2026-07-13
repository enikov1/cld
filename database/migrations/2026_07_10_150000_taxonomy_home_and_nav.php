<?php

use App\Models\NavItem;
use App\Models\NavMegaButton;
use App\Support\NavMenuBuilder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['genres', 'countries', 'people', 'years'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->boolean('show_on_home')->default(false)->after('noindex');
                $table->string('home_title')->nullable()->after('show_on_home');
                $table->unsignedSmallInteger('home_item_limit')->default(18)->after('home_title');
                $table->boolean('home_show_tabs')->default(true)->after('home_item_limit');
                $table->string('home_default_sort', 20)->default('latest')->after('home_show_tabs');
            });
        }

        Schema::table('nav_items', function (Blueprint $table) {
            $table->string('taxonomy_type', 32)->nullable()->after('category_id');
            $table->unsignedBigInteger('taxonomy_id')->nullable()->after('taxonomy_type');
        });

        Schema::table('nav_mega_buttons', function (Blueprint $table) {
            $table->string('taxonomy_type', 32)->nullable()->after('category_id');
            $table->unsignedBigInteger('taxonomy_id')->nullable()->after('taxonomy_type');
        });

        $this->migrateCategoryNavLinks();

        \App\Support\TplCache::forgetHome();
        \App\Support\TplCache::bumpGlobalVersion();
    }

    public function down(): void
    {
        Schema::table('nav_mega_buttons', function (Blueprint $table) {
            $table->dropColumn(['taxonomy_type', 'taxonomy_id']);
        });

        Schema::table('nav_items', function (Blueprint $table) {
            $table->dropColumn(['taxonomy_type', 'taxonomy_id']);
        });

        foreach (['genres', 'countries', 'people', 'years'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn([
                    'show_on_home',
                    'home_title',
                    'home_item_limit',
                    'home_show_tabs',
                    'home_default_sort',
                ]);
            });
        }
    }

    private function migrateCategoryNavLinks(): void
    {
        NavItem::query()
            ->where('link_type', NavItem::LINK_CATEGORY)
            ->whereNotNull('category_id')
            ->with('category')
            ->get()
            ->each(function (NavItem $item) {
                $slug = $item->category?->slug;
                if ($slug === null || $slug === '') {
                    return;
                }

                $item->update([
                    'link_type' => NavItem::LINK_CUSTOM,
                    'custom_url' => NavMenuBuilder::resolveLink(NavItem::LINK_CATEGORY, $slug, null),
                    'category_id' => null,
                ]);
            });

        NavMegaButton::query()
            ->where('link_type', NavItem::LINK_CATEGORY)
            ->whereNotNull('category_id')
            ->with('category')
            ->get()
            ->each(function (NavMegaButton $button) {
                $slug = $button->category?->slug;
                if ($slug === null || $slug === '') {
                    return;
                }

                $button->update([
                    'link_type' => NavItem::LINK_CUSTOM,
                    'custom_url' => NavMenuBuilder::resolveLink(NavItem::LINK_CATEGORY, $slug, null),
                    'category_id' => null,
                ]);
            });
    }
};
