<?php

namespace App\Http\Controllers;

use App\Models\Series;
use App\Models\SiteRedirect;
use App\Services\RedirectService;
use App\Support\RedirectPath;
use App\Support\SeriesUrl;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminRedirectController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $perPage = min(200, max(10, (int) $request->query('per_page', 50)));
        $page = max(1, (int) $request->query('page', 1));

        $query = SiteRedirect::query()
            ->with('series:id,title,slug,year,start_year,kp_id')
            ->orderByDesc('id');

        if ($q !== '') {
            $query->where(function ($builder) use ($q) {
                $builder->where('from_path', 'like', '%' . $q . '%')
                    ->orWhere('to_path', 'like', '%' . $q . '%')
                    ->orWhere('note', 'like', '%' . $q . '%')
                    ->orWhereHas('series', function ($seriesQuery) use ($q) {
                        $seriesQuery->where('title', 'like', '%' . $q . '%')
                            ->orWhere('slug', 'like', '%' . $q . '%')
                            ->orWhere('kp_id', 'like', '%' . $q . '%');
                    });
            });
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'items' => collect($paginator->items())->map(fn (SiteRedirect $item) => $this->serialize($item))->values()->all(),
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'last_page' => $paginator->lastPage(),
            'status_code_options' => $this->statusCodeOptions(),
        ]);
    }

    public function upsert(Request $request, RedirectService $redirectService)
    {
        $data = $request->validate([
            'id' => ['nullable', 'integer', 'exists:redirects,id'],
            'from_path' => ['required', 'string', 'max:2048'],
            'to_type' => ['required', 'string', Rule::in([SiteRedirect::TYPE_URL, SiteRedirect::TYPE_SERIES])],
            'to_path' => ['nullable', 'string', 'max:2048'],
            'series_id' => ['nullable', 'integer', 'exists:series,id'],
            'status_code' => ['nullable', 'integer', Rule::in([301, 302, 307, 308])],
            'is_active' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        if ($data['to_type'] === SiteRedirect::TYPE_SERIES && empty($data['series_id'])) {
            throw ValidationException::withMessages([
                'series_id' => ['Выберите сериал для редиректа.'],
            ]);
        }

        if ($data['to_type'] === SiteRedirect::TYPE_URL && trim((string) ($data['to_path'] ?? '')) === '') {
            throw ValidationException::withMessages([
                'to_path' => ['Укажите URL или путь назначения.'],
            ]);
        }

        $fromPath = RedirectPath::normalizeFrom((string) $data['from_path']);
        if ($fromPath === '/') {
            throw ValidationException::withMessages([
                'from_path' => ['Нельзя создать редирект с главной страницы.'],
            ]);
        }

        $redirect = !empty($data['id'])
            ? SiteRedirect::query()->findOrFail($data['id'])
            : new SiteRedirect();

        $duplicate = SiteRedirect::query()
            ->where('from_path', $fromPath)
            ->when($redirect->exists, fn ($q) => $q->where('id', '!=', $redirect->id))
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'from_path' => ['Редирект с таким исходным путём уже существует.'],
            ]);
        }

        $redirect->fill([
            'from_path' => $fromPath,
            'to_type' => $data['to_type'],
            'series_id' => $data['to_type'] === SiteRedirect::TYPE_SERIES ? (int) $data['series_id'] : null,
            'to_path' => $data['to_type'] === SiteRedirect::TYPE_URL
                ? RedirectPath::normalizeTo((string) ($data['to_path'] ?? ''))
                : null,
            'status_code' => (int) ($data['status_code'] ?? 301),
            'is_active' => $data['is_active'] ?? true,
            'note' => trim((string) ($data['note'] ?? '')) ?: null,
        ]);

        if ($redirect->to_type === SiteRedirect::TYPE_SERIES) {
            $redirect->normalizeToPath();
        }

        if ($redirect->to_type === SiteRedirect::TYPE_URL && $redirect->from_path === $redirect->to_path) {
            throw ValidationException::withMessages([
                'to_path' => ['Путь назначения не может совпадать с исходным путём.'],
            ]);
        }

        $redirect->save();
        $redirect->load('series:id,title,slug,year,start_year,kp_id');

        $redirectService->forgetCache();

        return response()->json([
            'ok' => true,
            'item' => $this->serialize($redirect),
        ]);
    }

    public function toggle(Request $request, int $id, RedirectService $redirectService)
    {
        $data = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $redirect = SiteRedirect::query()->findOrFail($id);
        $redirect->is_active = (bool) $data['is_active'];
        $redirect->save();

        $redirectService->forgetCache();

        return response()->json(['ok' => true, 'item' => $this->serialize($redirect->fresh('series'))]);
    }

    public function destroy(int $id, RedirectService $redirectService)
    {
        SiteRedirect::query()->whereKey($id)->delete();
        $redirectService->forgetCache();

        return response()->json(['ok' => true]);
    }

    public function seriesOptions(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $limit = min(30, max(5, (int) $request->query('limit', 20)));

        $query = Series::query()
            ->select(['id', 'title', 'slug', 'year', 'start_year', 'kp_id'])
            ->orderByDesc('id');

        if ($q !== '') {
            $query->where(function ($builder) use ($q) {
                $builder->where('title', 'like', '%' . $q . '%')
                    ->orWhere('title_en', 'like', '%' . $q . '%')
                    ->orWhere('title_original', 'like', '%' . $q . '%')
                    ->orWhere('slug', 'like', '%' . $q . '%')
                    ->orWhere('kp_id', 'like', '%' . $q . '%');
            });
        }

        $items = $query->limit($limit)->get()->map(function (Series $series) {
            return [
                'id' => (int) $series->id,
                'title' => $series->title,
                'kp_id' => $series->kp_id,
                'path' => SeriesUrl::path($series),
            ];
        })->values()->all();

        return response()->json(['items' => $items]);
    }

    /**
     * @return list<array{value: int, label: string}>
     */
    private function statusCodeOptions(): array
    {
        return [
            ['value' => 301, 'label' => '301 — постоянный'],
            ['value' => 302, 'label' => '302 — временный'],
            ['value' => 307, 'label' => '307 — временный (сохраняет метод)'],
            ['value' => 308, 'label' => '308 — постоянный (сохраняет метод)'],
        ];
    }

    private function serialize(SiteRedirect $redirect): array
    {
        $targetPath = $redirect->resolveTargetPath();

        return [
            'id' => (int) $redirect->id,
            'from_path' => $redirect->from_path,
            'to_type' => $redirect->to_type,
            'to_path' => $redirect->to_path,
            'target_path' => $targetPath,
            'series_id' => $redirect->series_id ? (int) $redirect->series_id : null,
            'status_code' => (int) $redirect->status_code,
            'is_active' => (bool) $redirect->is_active,
            'note' => $redirect->note,
            'hits_count' => (int) $redirect->hits_count,
            'created_at' => optional($redirect->created_at)?->toIso8601String(),
            'updated_at' => optional($redirect->updated_at)?->toIso8601String(),
            'series' => $redirect->series ? [
                'id' => (int) $redirect->series->id,
                'title' => $redirect->series->title,
                'kp_id' => $redirect->series->kp_id,
                'path' => SeriesUrl::path($redirect->series),
            ] : null,
        ];
    }
}
