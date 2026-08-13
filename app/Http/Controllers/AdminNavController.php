<?php

namespace App\Http\Controllers;

use App\Models\NavItem;
use App\Models\NavMegaButton;
use App\Models\NavMegaLink;
use App\Models\NavMegaSection;
use App\Support\NavMenuBuilder;
use App\Support\TaxonomyRegistry;
use App\Support\TplCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminNavController extends Controller
{
    public function index()
    {
        $items = NavItem::query()
            ->with([
                'megaButtons',
                'megaSections.links',
            ])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (NavItem $item) => $this->mapItem($item));

        return response()->json(['items' => $items]);
    }

    public function upsertItem(Request $request)
    {
        $data = $request->validate([
            'id' => ['nullable', 'integer', 'exists:nav_items,id'],
            'title' => ['required', 'string', 'max:200'],
            'link_type' => ['required', Rule::in($this->linkTypes())],
            'taxonomy_type' => ['nullable', 'string', Rule::in(TaxonomyRegistry::typeKeys())],
            'taxonomy_id' => ['nullable', 'integer'],
            'custom_url' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
            'show_desktop' => ['nullable', 'boolean'],
            'show_mobile' => ['nullable', 'boolean'],
            'has_mega' => ['nullable', 'boolean'],
        ]);

        $item = isset($data['id'])
            ? NavItem::query()->findOrFail($data['id'])
            : new NavItem();

        $item->fill([
            'title' => $data['title'],
            'link_type' => $data['link_type'],
            'taxonomy_type' => $data['link_type'] === NavItem::LINK_TAXONOMY ? ($data['taxonomy_type'] ?? null) : null,
            'taxonomy_id' => $data['link_type'] === NavItem::LINK_TAXONOMY ? ($data['taxonomy_id'] ?? null) : null,
            'custom_url' => $data['link_type'] === NavItem::LINK_CUSTOM ? ($data['custom_url'] ?? null) : null,
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => $data['is_active'] ?? true,
            'show_desktop' => $data['show_desktop'] ?? true,
            'show_mobile' => $data['show_mobile'] ?? true,
            'has_mega' => $data['has_mega'] ?? false,
        ]);
        $item->save();
        $item->load(['megaButtons', 'megaSections.links']);

        $this->bustCache();

        return response()->json([
            'ok' => true,
            'item' => $this->mapItem($item),
        ]);
    }

    public function destroyItem(int $id)
    {
        NavItem::query()->whereKey($id)->delete();
        $this->bustCache();

        return response()->json(['ok' => true]);
    }

    public function reorderItems(Request $request)
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:nav_items,id'],
        ]);

        DB::transaction(function () use ($data) {
            foreach ($data['ids'] as $index => $id) {
                NavItem::query()->whereKey($id)->update([
                    'sort_order' => ($index + 1) * 10,
                ]);
            }
        });

        $this->bustCache();

        return response()->json(['ok' => true]);
    }

    public function upsertMegaButton(Request $request)
    {
        $data = $request->validate([
            'id' => ['nullable', 'integer', 'exists:nav_mega_buttons,id'],
            'nav_item_id' => ['required', 'integer', 'exists:nav_items,id'],
            'title' => ['required', 'string', 'max:200'],
            'subtitle' => ['nullable', 'string', 'max:200'],
            'link_type' => ['required', Rule::in($this->linkTypes())],
            'taxonomy_type' => ['nullable', 'string', Rule::in(TaxonomyRegistry::typeKeys())],
            'taxonomy_id' => ['nullable', 'integer'],
            'custom_url' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $button = isset($data['id'])
            ? NavMegaButton::query()->findOrFail($data['id'])
            : new NavMegaButton();

        $button->fill([
            'nav_item_id' => $data['nav_item_id'],
            'title' => $data['title'],
            'subtitle' => $data['subtitle'] ?? null,
            'link_type' => $data['link_type'],
            'taxonomy_type' => $data['link_type'] === NavItem::LINK_TAXONOMY ? ($data['taxonomy_type'] ?? null) : null,
            'taxonomy_id' => $data['link_type'] === NavItem::LINK_TAXONOMY ? ($data['taxonomy_id'] ?? null) : null,
            'custom_url' => $data['link_type'] === NavItem::LINK_CUSTOM ? ($data['custom_url'] ?? null) : null,
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => $data['is_active'] ?? true,
        ]);
        $button->save();

        $this->bustCache();

        return response()->json([
            'ok' => true,
            'item' => $this->mapMegaButton($button),
        ]);
    }

    public function destroyMegaButton(int $id)
    {
        NavMegaButton::query()->whereKey($id)->delete();
        $this->bustCache();

        return response()->json(['ok' => true]);
    }

    public function reorderMegaButtons(Request $request)
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:nav_mega_buttons,id'],
        ]);

        DB::transaction(function () use ($data) {
            foreach ($data['ids'] as $index => $id) {
                NavMegaButton::query()->whereKey($id)->update([
                    'sort_order' => ($index + 1) * 10,
                ]);
            }
        });

        $this->bustCache();

        return response()->json(['ok' => true]);
    }

    public function upsertMegaSection(Request $request)
    {
        $data = $request->validate([
            'id' => ['nullable', 'integer', 'exists:nav_mega_sections,id'],
            'nav_item_id' => ['required', 'integer', 'exists:nav_items,id'],
            'title' => ['required', 'string', 'max:200'],
            'source_type' => ['required', Rule::in($this->sectionTypes())],
            'item_limit' => ['nullable', 'integer', 'min:1', 'max:60'],
            'css_class' => ['nullable', 'string', 'max:50'],
            'sort_order' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $section = isset($data['id'])
            ? NavMegaSection::query()->findOrFail($data['id'])
            : new NavMegaSection();

        $section->fill([
            'nav_item_id' => $data['nav_item_id'],
            'title' => $data['title'],
            'source_type' => $data['source_type'],
            'item_limit' => $data['item_limit'] ?? 14,
            'css_class' => $data['css_class'] ?? '',
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => $data['is_active'] ?? true,
        ]);
        $section->save();
        $section->load('links');

        $this->bustCache();

        return response()->json([
            'ok' => true,
            'item' => $this->mapMegaSection($section),
        ]);
    }

    public function destroyMegaSection(int $id)
    {
        NavMegaSection::query()->whereKey($id)->delete();
        $this->bustCache();

        return response()->json(['ok' => true]);
    }

    public function reorderMegaSections(Request $request)
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:nav_mega_sections,id'],
        ]);

        DB::transaction(function () use ($data) {
            foreach ($data['ids'] as $index => $id) {
                NavMegaSection::query()->whereKey($id)->update([
                    'sort_order' => ($index + 1) * 10,
                ]);
            }
        });

        $this->bustCache();

        return response()->json(['ok' => true]);
    }

    public function upsertMegaLink(Request $request)
    {
        $data = $request->validate([
            'id' => ['nullable', 'integer', 'exists:nav_mega_links,id'],
            'nav_mega_section_id' => ['required', 'integer', 'exists:nav_mega_sections,id'],
            'label' => ['required', 'string', 'max:200'],
            'url' => ['required', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $link = isset($data['id'])
            ? NavMegaLink::query()->findOrFail($data['id'])
            : new NavMegaLink();

        $link->fill([
            'nav_mega_section_id' => $data['nav_mega_section_id'],
            'label' => $data['label'],
            'url' => $data['url'],
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => $data['is_active'] ?? true,
        ]);
        $link->save();

        $this->bustCache();

        return response()->json([
            'ok' => true,
            'item' => $this->mapMegaLink($link),
        ]);
    }

    public function destroyMegaLink(int $id)
    {
        NavMegaLink::query()->whereKey($id)->delete();
        $this->bustCache();

        return response()->json(['ok' => true]);
    }

    public function reorderMegaLinks(Request $request)
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:nav_mega_links,id'],
        ]);

        DB::transaction(function () use ($data) {
            foreach ($data['ids'] as $index => $id) {
                NavMegaLink::query()->whereKey($id)->update([
                    'sort_order' => ($index + 1) * 10,
                ]);
            }
        });

        $this->bustCache();

        return response()->json(['ok' => true]);
    }

    /**
     * @return list<string>
     */
    private function linkTypes(): array
    {
        return [
            NavItem::LINK_HOME,
            NavItem::LINK_TAXONOMY,
            NavItem::LINK_COLLECTIONS,
            NavItem::LINK_STUDIOS,
            NavItem::LINK_CATALOG,
            NavItem::LINK_COMING_SOON,
            NavItem::LINK_CALENDAR,
            NavItem::LINK_CUSTOM,
        ];
    }

    /**
     * @return list<string>
     */
    private function sectionTypes(): array
    {
        return [
            NavMegaSection::SOURCE_GENRES,
            NavMegaSection::SOURCE_COUNTRIES,
            NavMegaSection::SOURCE_COLLECTIONS,
            NavMegaSection::SOURCE_STUDIOS,
            NavMegaSection::SOURCE_YEARS,
            NavMegaSection::SOURCE_CUSTOM,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapItem(NavItem $item): array
    {
        return [
            'id' => $item->id,
            'title' => $item->title,
            'link_type' => $item->link_type,
            'taxonomy_type' => $item->taxonomy_type,
            'taxonomy_id' => $item->taxonomy_id,
            'custom_url' => $item->custom_url,
            'url' => NavMenuBuilder::resolveLink(
                $item->link_type,
                $item->custom_url,
                $item->taxonomy_type,
                $this->taxonomySlug($item->taxonomy_type, $item->taxonomy_id),
            ),
            'sort_order' => $item->sort_order,
            'is_active' => $item->is_active,
            'show_desktop' => $item->show_desktop,
            'show_mobile' => $item->show_mobile,
            'has_mega' => $item->has_mega,
            'mega_buttons' => $item->megaButtons->map(fn (NavMegaButton $b) => $this->mapMegaButton($b))->values()->all(),
            'mega_sections' => $item->megaSections->map(fn (NavMegaSection $s) => $this->mapMegaSection($s))->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapMegaButton(NavMegaButton $button): array
    {
        return [
            'id' => $button->id,
            'nav_item_id' => $button->nav_item_id,
            'title' => $button->title,
            'subtitle' => $button->subtitle,
            'link_type' => $button->link_type,
            'taxonomy_type' => $button->taxonomy_type,
            'taxonomy_id' => $button->taxonomy_id,
            'custom_url' => $button->custom_url,
            'sort_order' => $button->sort_order,
            'is_active' => $button->is_active,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapMegaSection(NavMegaSection $section): array
    {
        return [
            'id' => $section->id,
            'nav_item_id' => $section->nav_item_id,
            'title' => $section->title,
            'source_type' => $section->source_type,
            'item_limit' => $section->item_limit,
            'css_class' => $section->css_class,
            'sort_order' => $section->sort_order,
            'is_active' => $section->is_active,
            'links' => $section->links->map(fn (NavMegaLink $l) => $this->mapMegaLink($l))->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapMegaLink(NavMegaLink $link): array
    {
        return [
            'id' => $link->id,
            'nav_mega_section_id' => $link->nav_mega_section_id,
            'label' => $link->label,
            'url' => $link->url,
            'sort_order' => $link->sort_order,
            'is_active' => $link->is_active,
        ];
    }

    private function bustCache(): void
    {
        TplCache::bumpGlobalVersion();
    }

    private function taxonomySlug(?string $type, ?int $id): ?string
    {
        if (!$type || !$id || !TaxonomyRegistry::isValidType($type)) {
            return null;
        }

        $model = TaxonomyRegistry::modelClass($type);

        return $model::query()->whereKey($id)->value('slug');
    }
}
