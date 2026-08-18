<?php

namespace Tests\Unit;

use App\Models\NavItem;
use App\Models\NavMegaSection;
use App\Models\Series;
use App\Models\Voice;
use App\Support\NavMenuBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavMegaVoicesTest extends TestCase
{
    use RefreshDatabase;

    public function test_mega_section_lists_voices_by_series_count(): void
    {
        $popular = Voice::query()->create([
            'slug' => 'lostfilm',
            'name' => 'LostFilm',
            'is_active' => true,
            'is_hidden' => false,
        ]);
        $rare = Voice::query()->create([
            'slug' => 'coldfilm',
            'name' => 'Coldfilm',
            'is_active' => true,
            'is_hidden' => false,
        ]);
        Voice::query()->create([
            'slug' => 'unused',
            'name' => 'Unused',
            'is_active' => true,
            'is_hidden' => false,
        ]);

        $first = Series::query()->create([
            'kp_id' => 'nav-voice-1',
            'slug' => 'nav-voice-1',
            'title' => 'One',
            'is_active' => true,
            'is_hidden' => false,
        ]);
        $second = Series::query()->create([
            'kp_id' => 'nav-voice-2',
            'slug' => 'nav-voice-2',
            'title' => 'Two',
            'is_active' => true,
            'is_hidden' => false,
        ]);
        $first->voices()->sync([$popular->id, $rare->id]);
        $second->voices()->sync([$popular->id]);

        $item = NavItem::query()->create([
            'title' => 'Каталог',
            'link_type' => NavItem::LINK_CATALOG,
            'is_active' => true,
            'show_desktop' => true,
            'show_mobile' => false,
            'has_mega' => true,
            'sort_order' => 10,
        ]);
        NavMegaSection::query()->create([
            'nav_item_id' => $item->id,
            'title' => 'Озвучки',
            'source_type' => NavMegaSection::SOURCE_VOICES,
            'item_limit' => 10,
            'item_sort' => NavMegaSection::SORT_SERIES_COUNT,
            'css_class' => 'wide',
            'sort_order' => 10,
            'is_active' => true,
        ]);

        NavMenuBuilder::flushRequestCache();
        $desktop = collect(NavMenuBuilder::forTpl()['nav_desktop_items']);
        $catalog = $desktop->firstWhere('title', 'Каталог');
        $this->assertIsArray($catalog);
        $links = $catalog['mega_sections'][0]['links'];

        $this->assertSame(['LostFilm', 'Coldfilm'], array_column($links, 'label'));
        $this->assertSame('/voice/lostfilm/', $links[0]['url']);
        $this->assertCount(2, $links);
    }
}
