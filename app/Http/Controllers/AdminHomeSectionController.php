<?php

namespace App\Http\Controllers;

use App\Models\HomeSection;
use App\Support\TplCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminHomeSectionController extends Controller
{
    public function index()
    {
        $items = HomeSection::query()
            ->with('category')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (HomeSection $s) => $this->mapItem($s));

        return response()->json(['items' => $items]);
    }

    public function upsert(Request $request)
    {
        $data = $request->validate([
            'id' => ['nullable', 'integer', 'exists:home_sections,id'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'title' => ['required', 'string', 'max:200'],
            'sort_order' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
            'item_limit' => ['nullable', 'integer', 'min:1', 'max:60'],
            'show_tabs' => ['nullable', 'boolean'],
            'default_sort' => ['nullable', 'in:latest,popular,rating'],
        ]);

        $section = isset($data['id'])
            ? HomeSection::query()->findOrFail($data['id'])
            : new HomeSection();

        $section->fill([
            'category_id' => $data['category_id'] ?? null,
            'title' => $data['title'],
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => $data['is_active'] ?? true,
            'item_limit' => $data['item_limit'] ?? 18,
            'show_tabs' => $data['show_tabs'] ?? true,
            'default_sort' => $data['default_sort'] ?? HomeSection::SORT_LATEST,
        ]);
        $section->save();
        $section->load('category');

        TplCache::forgetHome();

        return response()->json([
            'ok' => true,
            'item' => $this->mapItem($section),
        ]);
    }

    public function destroy(int $id)
    {
        HomeSection::query()->whereKey($id)->delete();
        TplCache::forgetHome();

        return response()->json(['ok' => true]);
    }

    public function reorder(Request $request)
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:home_sections,id'],
        ]);

        DB::transaction(function () use ($data) {
            foreach ($data['ids'] as $index => $id) {
                HomeSection::query()->whereKey($id)->update([
                    'sort_order' => ($index + 1) * 10,
                ]);
            }
        });

        TplCache::forgetHome();

        return response()->json(['ok' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function mapItem(HomeSection $section): array
    {
        return [
            'id' => $section->id,
            'category_id' => $section->category_id,
            'category' => $section->category ? [
                'id' => $section->category->id,
                'slug' => $section->category->slug,
                'title' => $section->category->title,
            ] : null,
            'title' => $section->title,
            'sort_order' => $section->sort_order,
            'is_active' => $section->is_active,
            'item_limit' => $section->item_limit,
            'show_tabs' => $section->show_tabs,
            'default_sort' => $section->default_sort,
        ];
    }
}
