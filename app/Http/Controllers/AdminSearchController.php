<?php

namespace App\Http\Controllers;

use App\Models\SearchQuery;
use App\Support\SearchQueryStatService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class AdminSearchController extends Controller
{
    public function index(Request $request)
    {
        if (!Schema::hasTable('search_queries')) {
            return response()->json([
                'ready' => false,
                'summary' => SearchQueryStatService::summary(),
                'items' => [],
            ]);
        }

        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'sort' => ['nullable', 'in:hits,suggest_hits,full_hits,query,last_searched_at,created_at'],
            'dir' => ['nullable', 'in:asc,desc'],
            'limit' => ['nullable', 'integer', 'min:10', 'max:500'],
        ]);

        $sort = $data['sort'] ?? 'hits';
        $dir = $data['dir'] ?? 'desc';
        $limit = (int)($data['limit'] ?? 200);

        $query = SearchQuery::query();

        $search = trim((string)($data['q'] ?? ''));
        if ($search !== '') {
            $like = '%' . $search . '%';
            $query->where(function ($builder) use ($like) {
                $builder->where('query', 'like', $like)
                    ->orWhere('query_normalized', 'like', mb_strtolower($like));
            });
        }

        $items = $query
            ->orderBy($sort, $dir)
            ->when($sort !== 'last_searched_at', fn ($builder) => $builder->orderByDesc('last_searched_at'))
            ->limit($limit)
            ->get([
                'id',
                'query',
                'hits',
                'suggest_hits',
                'full_hits',
                'last_searched_at',
                'created_at',
            ]);

        return response()->json([
            'ready' => true,
            'summary' => SearchQueryStatService::summary(),
            'items' => $items,
        ]);
    }

    public function destroy(int $id)
    {
        if (!Schema::hasTable('search_queries')) {
            return response()->json(['ok' => false, 'message' => 'Таблица статистики не создана'], 409);
        }

        SearchQuery::query()->whereKey($id)->delete();

        return response()->json(['ok' => true]);
    }
}
