<?php

namespace App\Http\Controllers;

use App\Models\Country;
use App\Models\Genre;
use App\Models\Person;
use App\Models\Series;
use App\Models\Year;
use App\Services\ImageOptimizer;
use App\Services\PosterContext;
use App\Services\PosterStorage;
use App\Support\SlugHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminTaxonomyController extends Controller
{
    private const TYPES = [
        'genres' => Genre::class,
        'countries' => Country::class,
        'people' => Person::class,
        'years' => Year::class,
    ];

    public function index(string $type)
    {
        $model = $this->model($type);

        $query = $model::query()
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($type === 'years') {
            $query
                ->select('years.*')
                ->selectSub(
                    Series::query()
                        ->selectRaw('count(*)')
                        ->whereNull('series.deleted_at')
                        ->whereRaw('COALESCE(NULLIF(series.year, 0), series.start_year) = CAST(years.slug AS UNSIGNED)'),
                    'series_count',
                );
        } else {
            $query->withCount('series as series_count');
        }

        return response()->json([
            'items' => $query->get(),
        ]);
    }

    public function options()
    {
        return response()->json([
            'genres' => Genre::query()->where('is_active', true)->orderBy('name')->get(['id', 'slug', 'name']),
            'countries' => Country::query()->where('is_active', true)->orderBy('name')->get(['id', 'slug', 'name']),
            'people' => Person::query()->where('is_active', true)->orderBy('name')->get(['id', 'slug', 'name']),
            'years' => Year::query()->where('is_active', true)->orderByDesc('sort_order')->get(['id', 'slug', 'name']),
        ]);
    }

    public function upsert(Request $request, string $type)
    {
        $model = $this->model($type);

        $rules = [
            'id' => ['nullable', 'integer'],
            'slug' => ['nullable', 'string', 'regex:/^[a-z0-9\-]*$/'],
            'name' => ['required', 'string'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:65535'],
            'seo_html' => ['nullable', 'string', 'max:65535'],
            'sort_order' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
            'is_hidden' => ['nullable', 'boolean'],
            'noindex' => ['nullable', 'boolean'],
            'show_on_home' => ['nullable', 'boolean'],
            'home_title' => ['nullable', 'string', 'max:200'],
            'home_item_limit' => ['nullable', 'integer', 'min:1', 'max:60'],
            'home_show_tabs' => ['nullable', 'boolean'],
            'home_default_sort' => ['nullable', 'in:latest,popular,rating'],
        ];

        if ($type === 'people') {
            $rules['photo_url'] = ['nullable', 'string'];
        }

        $data = $request->validate($rules);

        $manual = trim((string)($data['slug'] ?? ''));
        if ($type === 'years') {
            $yearValue = $manual !== '' ? $manual : (string)$data['name'];
            if (!preg_match('/^(19|20)\d{2}$/', $yearValue)) {
                return response()->json(['ok' => false, 'error' => 'Год должен быть в формате YYYY (1900–2099)'], 422);
            }
            $slug = $yearValue;
            $data['name'] = $yearValue;
        } elseif ($manual !== '') {
            $slug = Str::slug($manual);
        } elseif (!empty($data['id'])) {
            $existing = $model::query()->findOrFail($data['id']);
            $slug = SlugHelper::makeUnique(
                null,
                $data['name'],
                fn (string $candidate) => $model::query()
                    ->where('slug', $candidate)
                    ->where('id', '!=', $existing->id)
                    ->exists()
            );
        } else {
            $slug = SlugHelper::makeUnique(
                null,
                $data['name'],
                fn (string $candidate) => $model::query()->where('slug', $candidate)->exists()
            );
        }

        if (!empty($data['id'])) {
            $item = $model::query()->findOrFail($data['id']);
            if ($model::query()->where('slug', $slug)->where('id', '!=', $item->id)->exists()) {
                return response()->json(['ok' => false, 'error' => 'Slug already taken'], 422);
            }
        } elseif ($model::query()->where('slug', $slug)->exists()) {
            return response()->json(['ok' => false, 'error' => 'Slug already taken'], 422);
        }

        $attrs = [
            'slug' => $slug,
            'name' => $data['name'],
            'meta_title' => $data['meta_title'] ?? null,
            'meta_description' => $data['meta_description'] ?? null,
            'seo_html' => isset($data['seo_html']) ? str_replace("\r\n", "\n", (string)$data['seo_html']) : null,
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => $data['is_active'] ?? true,
            'is_hidden' => $data['is_hidden'] ?? false,
            'noindex' => $data['noindex'] ?? true,
            'show_on_home' => $data['show_on_home'] ?? false,
            'home_title' => $data['home_title'] ?? null,
            'home_item_limit' => $data['home_item_limit'] ?? 18,
            'home_show_tabs' => $data['home_show_tabs'] ?? true,
            'home_default_sort' => $data['home_default_sort'] ?? 'latest',
        ];

        if ($type === 'people') {
            $attrs['photo_url'] = $this->resolvePersonPhotoUrl($attrs['photo_url'] ?? null, $slug);
        }

        if (!empty($data['id'])) {
            $item = $model::query()->findOrFail($data['id']);
            $item->update($attrs);
        } else {
            $item = $model::query()->create($attrs);
        }

        \App\Support\TplCache::bumpGlobalVersion();
        \App\Support\TplCache::forgetHome();

        return response()->json(['ok' => true, 'item' => $item->fresh()]);
    }

    public function destroy(string $type, int $id)
    {
        $model = $this->model($type);
        $item = $model::query()->findOrFail($id);
        $item->delete();

        return response()->json(['ok' => true]);
    }

    public function uploadPhoto(Request $request, int $id)
    {
        $maxKb = (int)ceil(app(ImageOptimizer::class)->maxUploadBytes() / 1024);

        $request->validate([
            'photo' => ['required', 'file', 'image', 'max:' . $maxKb],
        ]);

        $person = Person::query()->findOrFail($id);
        $url = app(PosterStorage::class)->storeFromUpload(
            $request->file('photo'),
            PosterContext::forPerson($person),
        );
        $person->photo_url = $url;
        $person->save();

        \App\Support\TplCache::bumpGlobalVersion();

        return response()->json(['ok' => true, 'photo_url' => $url, 'item' => $person->fresh()]);
    }

    private function resolvePersonPhotoUrl(?string $photoUrl, string $slug): ?string
    {
        $photoUrl = $photoUrl !== null ? trim($photoUrl) : null;
        if ($photoUrl === null || $photoUrl === '') {
            return null;
        }

        if ($this->isLocalStorageUrl($photoUrl)) {
            return $photoUrl;
        }

        if (preg_match('/^https?:\/\//i', $photoUrl)) {
            $stored = app(PosterStorage::class)->storeFromUrl(
                $photoUrl,
                PosterContext::forPersonSlug($slug),
            );

            return $stored ?: $photoUrl;
        }

        return $photoUrl;
    }

    private function isLocalStorageUrl(string $url): bool
    {
        return str_starts_with($url, '/storage/');
    }

    /**
     * @return class-string<Model>
     */
    private function model(string $type): string
    {
        if (!isset(self::TYPES[$type])) {
            abort(404);
        }

        return self::TYPES[$type];
    }
}
