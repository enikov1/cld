<?php

namespace App\Http\Controllers;

use App\Models\HomeSection;
use App\Services\HomeBlockService;
use App\Services\HomeSectionService;
use App\Support\TplCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminHomeSectionController extends Controller
{
    public function index()
    {
        $items = HomeSection::query()
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
            'title' => ['required', 'string', 'max:200'],
            'link_url' => ['nullable', 'string', 'max:500'],
            'filters' => ['nullable', 'array'],
            'filters.content_type' => ['nullable', 'string', Rule::in(['film', 'series'])],
            'filters.broadcast_status' => ['nullable', 'string', Rule::in(['ongoing', 'paused', 'completed'])],
            'filters.year_mode' => ['nullable', 'string', Rule::in(['', 'current_year'])],
            'filters.studio_id' => ['nullable', 'integer', 'min:1'],
            'filters.genre_id' => ['nullable', 'integer', 'min:1'],
            'filters.country_id' => ['nullable', 'integer', 'min:1'],
            'filters.actor_id' => ['nullable', 'integer', 'min:1'],
            'filters.director_id' => ['nullable', 'integer', 'min:1'],
            'filters.year_from' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'filters.year_to' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'filters.kp_rating_min' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'filters.imdb_rating_min' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'filters.tmdb_popularity_min' => ['nullable', 'numeric', 'min:0'],
            'filters.views_min' => ['nullable', 'integer', 'min:0'],
            'filters.is_coming_soon' => ['nullable', 'boolean'],
            'filters.popular_badge_active' => ['nullable', 'boolean'],
            'filters.has_poster' => ['nullable', 'boolean'],
            'filters.has_tmdb_id' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
            'item_limit' => ['nullable', 'integer', 'min:1', 'max:60'],
            'show_tabs' => ['nullable', 'boolean'],
            'default_sort' => ['nullable', 'in:latest,popular,rating'],
        ]);

        $section = isset($data['id'])
            ? HomeSection::query()->findOrFail($data['id'])
            : new HomeSection();

        $filters = $this->cleanFilters($data['filters'] ?? []);

        $section->fill([
            'title' => $data['title'],
            'link_url' => trim((string) ($data['link_url'] ?? '')) ?: null,
            'filters' => $filters !== [] ? $filters : null,
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => $data['is_active'] ?? true,
            'item_limit' => $data['item_limit'] ?? 18,
            'show_tabs' => $data['show_tabs'] ?? true,
            'default_sort' => $data['default_sort'] ?? HomeSection::SORT_LATEST,
        ]);
        $section->save();

        TplCache::forgetHome();

        return response()->json([
            'ok' => true,
            'item' => $this->mapItem($section),
        ]);
    }

    public function preview(Request $request)
    {
        $data = $request->validate([
            'filters' => ['nullable', 'array'],
            'item_limit' => ['nullable', 'integer', 'min:1', 'max:60'],
        ]);

        $filters = $this->cleanFilters($data['filters'] ?? []);
        $count = HomeBlockService::seriesCount($filters);
        $limit = max(1, min(60, (int) ($data['item_limit'] ?? 18)));

        return response()->json([
            'count' => $count,
            'shown' => min($count, $limit),
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
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function cleanFilters(array $filters): array
    {
        $clean = [];

        foreach ($filters as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $clean[$key] = $value;
        }

        if (($clean['year_mode'] ?? '') === '') {
            unset($clean['year_mode']);
        }

        return $clean;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapItem(HomeSection $section): array
    {
        $filters = is_array($section->filters) ? $section->filters : [];

        return [
            'id' => $section->id,
            'title' => $section->title,
            'link_url' => $section->link_url,
            'filters' => $filters,
            'sort_order' => $section->sort_order,
            'is_active' => $section->is_active,
            'item_limit' => $section->item_limit,
            'show_tabs' => $section->show_tabs,
            'default_sort' => $section->default_sort,
            'series_count' => HomeBlockService::seriesCount($filters),
        ];
    }
}
