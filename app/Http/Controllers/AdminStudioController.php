<?php

namespace App\Http\Controllers;

use App\Models\Studio;
use App\Models\StudioItem;
use App\Services\ImageOptimizer;
use App\Services\PosterContext;
use App\Services\PosterStorage;
use App\Support\AdminSeriesResolver;
use App\Support\SeriesItemResolver;
use App\Support\SlugHelper;
use App\Support\TplCache;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminStudioController extends Controller
{
    public function index()
    {
        return response()->json([
            'items' => Studio::query()->withCount('items')->catalogOrder()->limit(2000)->get(),
        ]);
    }

    public function upsert(Request $request)
    {
        $data = $request->validate([
            'id' => ['nullable', 'integer', 'exists:studios,id'],
            'slug' => ['nullable', 'string', 'regex:/^[a-z0-9\-]*$/'],
            'title' => ['required', 'string', 'max:255'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:65535'],
            'meta_description' => ['nullable', 'string', 'max:65535'],
            'seo_html' => ['nullable', 'string', 'max:65535'],
            'logo_url' => ['nullable', 'string', 'max:2048'],
            'tmdb_id' => ['nullable', 'integer', 'min:1'],
            'tmdb_type' => ['nullable', 'string', Rule::in(['movie', 'tv', 'company'])],
            'sort_order' => ['nullable', 'integer'],
            'is_pinned' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'is_hidden' => ['nullable', 'boolean'],
            'noindex' => ['nullable', 'boolean'],
        ]);

        $manual = trim((string) ($data['slug'] ?? ''));
        $studio = !empty($data['id'])
            ? Studio::query()->findOrFail($data['id'])
            : null;

        if ($studio) {
            $slug = $studio->slug;
        } elseif ($manual !== '') {
            $slug = \Illuminate\Support\Str::slug($manual);
        } else {
            $slug = SlugHelper::makeUnique(
                null,
                $data['title'],
                fn (string $candidate) => Studio::query()->where('slug', $candidate)->exists()
            );
        }

        if (!$studio && Studio::query()->where('slug', $slug)->exists()) {
            return response()->json(['ok' => false, 'error' => 'Slug already taken'], 422);
        }

        $attrs = [
            'title' => $data['title'],
            'meta_title' => $data['meta_title'] ?? null,
            'description' => $data['description'] ?? null,
            'meta_description' => $data['meta_description'] ?? null,
            'seo_html' => isset($data['seo_html']) ? str_replace("\r\n", "\n", (string) $data['seo_html']) : null,
            'logo_url' => $data['logo_url'] ?? null,
            'tmdb_id' => $data['tmdb_id'] ?? null,
            'tmdb_type' => $data['tmdb_type'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
            'is_pinned' => $data['is_pinned'] ?? false,
            'is_active' => $data['is_active'] ?? true,
            'is_hidden' => $data['is_hidden'] ?? false,
            'noindex' => $data['noindex'] ?? false,
        ];

        if ($studio) {
            $studio->update($attrs);
        } else {
            $studio = Studio::query()->create(array_merge($attrs, [
                'slug' => $slug,
            ]));
        }

        TplCache::bumpGlobalVersion();

        return response()->json(['ok' => true, 'item' => $studio->fresh()]);
    }

    public function uploadLogo(Request $request, string $slug)
    {
        $maxKb = (int) ceil(app(ImageOptimizer::class)->maxUploadBytes() / 1024);

        $request->validate([
            'logo' => ['required', 'file', 'image', 'max:' . $maxKb],
        ]);

        $studio = Studio::query()->where('slug', $slug)->firstOrFail();
        $url = app(PosterStorage::class)->storeFromUpload(
            $request->file('logo'),
            PosterContext::forStudio($studio),
        );
        $studio->logo_url = $url;
        $studio->save();

        return response()->json(['ok' => true, 'logo_url' => $url, 'item' => $studio]);
    }

    public function items(string $studio_slug)
    {
        $studio = Studio::query()->where('slug', $studio_slug)->firstOrFail();

        return response()->json([
            'items' => StudioItem::query()
                ->where('studio_id', $studio->id)
                ->with('series:id,kp_id,tmdb_id,title,slug,year')
                ->orderBy('rank_order')
                ->get(),
        ]);
    }

    public function saveItems(Request $request, string $studio_slug)
    {
        $data = $request->validate([
            'items' => ['required', 'array'],
            'items.*.series_id' => ['nullable', 'integer', 'exists:series,id'],
            'items.*.kp_id' => ['nullable', 'string'],
            'items.*.tmdb_id' => ['nullable', 'string'],
            'items.*.rank_order' => ['nullable', 'integer'],
        ]);

        $studio = Studio::query()->where('slug', $studio_slug)->firstOrFail();
        $added = 0;
        $skipped = 0;

        foreach ($data['items'] as $idx => $item) {
            $series = SeriesItemResolver::fromItem($item);
            if (!$series) {
                $skipped++;
                continue;
            }

            StudioItem::query()->updateOrCreate(
                ['studio_id' => $studio->id, 'series_id' => $series->id],
                ['rank_order' => $item['rank_order'] ?? (int) $idx]
            );

            if (!$series->studio_id) {
                $series->studio_id = $studio->id;
                $series->save();
            }
            $added++;
        }

        TplCache::bumpGlobalVersion();

        return response()->json(['ok' => true, 'added' => $added, 'skipped' => $skipped]);
    }

    public function destroyItem(string $studio_slug, string $seriesKey)
    {
        $studio = Studio::query()->where('slug', $studio_slug)->firstOrFail();
        $series = AdminSeriesResolver::byKey($seriesKey);

        StudioItem::query()
            ->where('studio_id', $studio->id)
            ->where('series_id', $series->id)
            ->delete();

        TplCache::bumpGlobalVersion();

        return response()->json(['ok' => true]);
    }

    public function destroy(string $studio_slug)
    {
        $studio = Studio::query()->where('slug', $studio_slug)->firstOrFail();
        $studio->delete();

        TplCache::bumpGlobalVersion();

        return response()->json(['ok' => true]);
    }
}
