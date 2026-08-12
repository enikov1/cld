<?php

namespace App\Http\Controllers;

use App\Models\Collection;
use App\Models\CollectionItem;
use App\Services\CollectionAiImportService;
use App\Services\CollectionAiPromptService;
use App\Services\CollectionAutoMatcher;
use App\Services\ImageOptimizer;
use App\Services\PosterContext;
use App\Services\PosterStorage;
use App\Support\AdminSeriesResolver;
use App\Support\SeriesItemResolver;
use App\Support\SlugHelper;
use App\Support\TplCache;
use Illuminate\Http\Request;

class AdminCollectionController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $id = (int) $request->query('id', 0);
        $slug = trim((string) $request->query('slug', ''));
        $perPage = min(200, max(10, (int) $request->query('per_page', 50)));
        $page = max(1, (int) $request->query('page', 1));

        $query = Collection::query()->withCount('items')->catalogOrder();
        if ($id > 0) {
            $query->where('id', $id);
        }
        if ($slug !== '') {
            $query->where('slug', $slug);
        }
        if ($q !== '') {
            $query->where(function ($builder) use ($q) {
                $builder->where('title', 'like', '%' . $q . '%')
                    ->orWhere('slug', 'like', '%' . $q . '%');
            });
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'items' => $paginator->items(),
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'last_page' => $paginator->lastPage(),
        ]);
    }

    public function aiPrompt()
    {
        $result = app(CollectionAiPromptService::class)->build();

        return response()->json(['ok' => true, ...$result]);
    }

    public function aiImport(Request $request)
    {
        $data = $request->validate([
            'payload' => ['required', 'string', 'max:500000'],
            'dry_run' => ['nullable', 'boolean'],
        ]);

        $result = app(CollectionAiImportService::class)->import(
            (string) $data['payload'],
            (bool) ($data['dry_run'] ?? true),
        );

        $status = $result['ok'] || ($result['items'] !== [] || $result['skipped'] !== []) ? 200 : 422;

        return response()->json($result, $status);
    }

    public function upsert(Request $request)
    {
        $data = $request->validate([
            'id' => ['nullable', 'integer', 'exists:collections,id'],
            'slug' => ['nullable', 'string', 'regex:/^[a-z0-9\-]*$/'],
            'title' => ['required', 'string', 'max:255'],
            'studio_id' => ['nullable', 'integer', 'exists:studios,id'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:65535'],
            'meta_description' => ['nullable', 'string', 'max:65535'],
            'seo_html' => ['nullable', 'string', 'max:65535'],
            'cover_url' => ['nullable', 'string', 'max:2048'],
            'home_banner_url' => ['nullable', 'string', 'max:2048'],
            'sort_order' => ['nullable', 'integer'],
            'is_pinned' => ['nullable', 'boolean'],
            'show_on_home' => ['nullable', 'boolean'],
            'source_updated_at' => ['nullable', 'date'],
            'is_active' => ['nullable', 'boolean'],
            'is_hidden' => ['nullable', 'boolean'],
            'noindex' => ['nullable', 'boolean'],
            'auto_add_enabled' => ['nullable', 'boolean'],
            'auto_keywords' => ['nullable'],
            'series_ids' => ['nullable', 'array'],
            'series_ids.*' => ['integer', 'exists:series,id'],
        ]);

        $matcher = app(CollectionAutoMatcher::class);
        $keywordsChanged = false;

        $manual = trim((string) ($data['slug'] ?? ''));
        $collection = !empty($data['id'])
            ? Collection::query()->findOrFail($data['id'])
            : null;

        if ($collection) {
            $slug = $collection->slug;
            $oldKeywords = $matcher->parseKeywords($collection->auto_keywords);
            $newKeywords = array_key_exists('auto_keywords', $data)
                ? $matcher->parseKeywords($data['auto_keywords'])
                : $oldKeywords;
            $keywordsChanged = $oldKeywords !== $newKeywords
                || (array_key_exists('auto_add_enabled', $data) && (bool) $data['auto_add_enabled'] !== (bool) $collection->auto_add_enabled);
        } elseif ($manual !== '') {
            $slug = \Illuminate\Support\Str::slug($manual);
        } else {
            $slug = SlugHelper::makeUnique(
                null,
                $data['title'],
                fn (string $candidate) => Collection::query()->where('slug', $candidate)->exists()
            );
        }

        if (!$collection && Collection::query()->where('slug', $slug)->exists()) {
            return response()->json(['ok' => false, 'error' => 'Slug already taken'], 422);
        }

        $attrs = [
            'title' => $data['title'],
            'studio_id' => $data['studio_id'] ?? null,
            'meta_title' => $data['meta_title'] ?? null,
            'description' => $data['description'] ?? null,
            'meta_description' => $data['meta_description'] ?? null,
            'seo_html' => isset($data['seo_html']) ? str_replace("\r\n", "\n", (string) $data['seo_html']) : null,
            'cover_url' => $data['cover_url'] ?? null,
            'home_banner_url' => $data['home_banner_url'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
            'is_pinned' => $data['is_pinned'] ?? false,
            'show_on_home' => $data['show_on_home'] ?? false,
            'is_active' => $data['is_active'] ?? true,
            'is_hidden' => $data['is_hidden'] ?? false,
            'noindex' => $data['noindex'] ?? false,
        ];

        if (array_key_exists('source_updated_at', $data) && $data['source_updated_at']) {
            $attrs['source_updated_at'] = $data['source_updated_at'];
        }

        if (array_key_exists('auto_add_enabled', $data)) {
            $attrs['auto_add_enabled'] = (bool) $data['auto_add_enabled'];
        } elseif (!$collection) {
            $attrs['auto_add_enabled'] = false;
        }

        if (array_key_exists('auto_keywords', $data)) {
            $parsed = $matcher->parseKeywords($data['auto_keywords']);
            $attrs['auto_keywords'] = $parsed !== [] ? $parsed : null;
        }

        if ($collection) {
            $collection->update($attrs);
        } else {
            $collection = Collection::query()->create(array_merge($attrs, [
                'slug' => $slug,
                'source_updated_at' => $data['source_updated_at'] ?? now(),
            ]));
            $keywordsChanged = !empty($attrs['auto_add_enabled']) && !empty($attrs['auto_keywords']);
        }

        if (array_key_exists('series_ids', $data)) {
            $matcher->syncManualSeries($collection, $data['series_ids'] ?? []);
        }

        $autoSync = ['added' => 0, 'removed' => 0];
        if ($keywordsChanged) {
            $autoSync = $matcher->refreshAutoItems($collection->fresh());
        }

        TplCache::bumpGlobalVersion();

        $seriesIds = CollectionItem::query()
            ->where('collection_id', $collection->id)
            ->orderBy('rank_order')
            ->pluck('series_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $manualSeriesIds = CollectionItem::query()
            ->where('collection_id', $collection->id)
            ->where('is_auto', false)
            ->orderBy('rank_order')
            ->pluck('series_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        return response()->json([
            'ok' => true,
            'item' => $collection->fresh(),
            'series_ids' => $seriesIds,
            'manual_series_ids' => $manualSeriesIds,
            'auto_sync' => $autoSync,
        ]);
    }

    public function autoSync(string $collection_slug)
    {
        $collection = Collection::query()->where('slug', $collection_slug)->firstOrFail();
        $result = app(CollectionAutoMatcher::class)->refreshAutoItems($collection);

        TplCache::bumpGlobalVersion();

        return response()->json(['ok' => true, ...$result]);
    }

    public function uploadCover(Request $request, string $slug)
    {
        $maxKb = (int) ceil(app(ImageOptimizer::class)->maxUploadBytes() / 1024);

        $request->validate([
            'cover' => ['required', 'file', 'image', 'max:' . $maxKb],
            'title' => ['nullable', 'string', 'max:255'],
            'variant' => ['nullable', 'string', 'in:cover,banner'],
        ]);

        $variant = $request->input('variant', 'cover');
        $isBanner = $variant === 'banner';
        $slugInput = in_array($slug, ['_draft', 'new'], true) ? '' : $slug;
        $normalizedSlug = SlugHelper::make(
            $slugInput,
            trim((string) $request->input('title', '')),
        );

        $collection = Collection::query()->where('slug', $normalizedSlug)->first();
        $contextVariant = $isBanner ? 'banner' : null;
        $url = app(PosterStorage::class)->storeFromUpload(
            $request->file('cover'),
            $collection
                ? PosterContext::forCollection($collection, $contextVariant)
                : PosterContext::forCollectionSlug($normalizedSlug, $contextVariant),
        );

        if ($collection) {
            if ($isBanner) {
                $collection->home_banner_url = $url;
            } else {
                $collection->cover_url = $url;
            }
            $collection->save();
        }

        return response()->json([
            'ok' => true,
            'cover_url' => $isBanner ? null : $url,
            'home_banner_url' => $isBanner ? $url : null,
            'slug' => $normalizedSlug,
            'item' => $collection,
        ]);
    }

    public function destroyCover(Request $request, string $slug)
    {
        $data = $request->validate([
            'variant' => ['nullable', 'string', 'in:cover,banner'],
        ]);

        $variant = $data['variant'] ?? 'cover';
        $isBanner = $variant === 'banner';
        $collection = Collection::query()->where('slug', $slug)->firstOrFail();

        if ($isBanner) {
            $collection->home_banner_url = null;
        } else {
            $collection->cover_url = null;
        }
        $collection->save();

        TplCache::bumpGlobalVersion();

        return response()->json([
            'ok' => true,
            'cover_url' => $collection->cover_url,
            'home_banner_url' => $collection->home_banner_url,
            'item' => $collection->fresh(),
        ]);
    }

    public function items(string $collection_slug)
    {
        $collection = Collection::query()->where('slug', $collection_slug)->firstOrFail();

        return response()->json([
            'items' => CollectionItem::query()
                ->where('collection_id', $collection->id)
                ->with('series:id,kp_id,tmdb_id,title,slug,year')
                ->orderBy('rank_order')
                ->get()
                ->map(fn (CollectionItem $item) => [
                    'id' => $item->id,
                    'collection_id' => $item->collection_id,
                    'series_id' => $item->series_id,
                    'rank_order' => $item->rank_order,
                    'is_auto' => (bool) $item->is_auto,
                    'series' => $item->series,
                ]),
            'series_ids' => CollectionItem::query()
                ->where('collection_id', $collection->id)
                ->orderBy('rank_order')
                ->pluck('series_id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all(),
            'manual_series_ids' => CollectionItem::query()
                ->where('collection_id', $collection->id)
                ->where('is_auto', false)
                ->orderBy('rank_order')
                ->pluck('series_id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all(),
        ]);
    }

    public function saveItems(Request $request, string $collection_slug)
    {
        $data = $request->validate([
            'items' => ['required', 'array'],
            'items.*.series_id' => ['nullable', 'integer', 'exists:series,id'],
            'items.*.kp_id' => ['nullable', 'string'],
            'items.*.tmdb_id' => ['nullable', 'string'],
            'items.*.rank_order' => ['nullable', 'integer'],
        ]);

        $collection = Collection::query()->where('slug', $collection_slug)->firstOrFail();
        $added = 0;
        $skipped = 0;

        foreach ($data['items'] as $idx => $item) {
            $series = SeriesItemResolver::fromItem($item);
            if (!$series) {
                $skipped++;
                continue;
            }

            CollectionItem::query()->updateOrCreate(
                ['collection_id' => $collection->id, 'series_id' => $series->id],
                ['rank_order' => $item['rank_order'] ?? (int) $idx, 'is_auto' => false]
            );
            $added++;
        }

        TplCache::bumpGlobalVersion();

        return response()->json(['ok' => true, 'added' => $added, 'skipped' => $skipped]);
    }

    public function destroyItem(string $collection_slug, string $seriesKey)
    {
        $collection = Collection::query()->where('slug', $collection_slug)->firstOrFail();
        $series = AdminSeriesResolver::byKey($seriesKey);

        CollectionItem::query()
            ->where('collection_id', $collection->id)
            ->where('series_id', $series->id)
            ->delete();

        TplCache::bumpGlobalVersion();

        return response()->json(['ok' => true]);
    }

    public function destroy(string $collection_slug)
    {
        $collection = Collection::query()->where('slug', $collection_slug)->firstOrFail();
        $collection->delete();

        TplCache::bumpGlobalVersion();

        return response()->json(['ok' => true]);
    }
}
