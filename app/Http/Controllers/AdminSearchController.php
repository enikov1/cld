<?php

namespace App\Http\Controllers;

use App\Models\SearchQuery;
use App\Models\SearchQueryLog;
use App\Support\SearchQueryStatService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class AdminSearchController extends Controller
{
    public function index(Request $request)
    {
        $hasAggregated = Schema::hasTable('search_queries');
        $hasLogs = Schema::hasTable('search_query_logs');

        if (!$hasAggregated && !$hasLogs) {
            return response()->json([
                'ready' => false,
                'logs_ready' => false,
                'summary' => SearchQueryStatService::summary(),
                'top_queries' => [],
                'items' => [],
                'aggregated' => [],
            ]);
        }

        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'found' => ['nullable', 'in:1,0,yes,no'],
            'source' => ['nullable', 'in:suggest,full'],
            'ip' => ['nullable', 'string', 'max:45'],
            'view' => ['nullable', 'in:log,aggregated'],
            'sort' => ['nullable', 'string'],
            'dir' => ['nullable', 'in:asc,desc'],
            'limit' => ['nullable', 'integer', 'min:10', 'max:500'],
        ]);

        $filters = [
            'q' => $data['q'] ?? '',
            'date_from' => $data['date_from'] ?? '',
            'date_to' => $data['date_to'] ?? '',
            'found' => $data['found'] ?? null,
            'source' => $data['source'] ?? '',
            'ip' => $data['ip'] ?? '',
        ];

        $view = $data['view'] ?? 'log';
        $dir = $data['dir'] ?? 'desc';
        $limit = (int)($data['limit'] ?? 200);

        $summary = SearchQueryStatService::summary($filters);
        $topQueries = SearchQueryStatService::topQueries($filters, 5);

        $items = [];
        if ($view === 'log' && $hasLogs) {
            $sort = $data['sort'] ?? 'created_at';
            if (!in_array($sort, ['created_at', 'query', 'source', 'found', 'results_count', 'ip'], true)) {
                $sort = 'created_at';
            }

            $items = SearchQueryStatService::filteredLogQuery($filters)
                ->orderBy($sort, $dir)
                ->when($sort !== 'created_at', fn ($builder) => $builder->orderByDesc('created_at'))
                ->limit($limit)
                ->get([
                    'id',
                    'query',
                    'source',
                    'found',
                    'results_count',
                    'ip',
                    'created_at',
                ]);
        }

        $aggregated = [];
        if ($hasAggregated) {
            $aggSort = $data['sort'] ?? 'hits';
            if (!in_array($aggSort, ['hits', 'suggest_hits', 'full_hits', 'query', 'last_searched_at', 'created_at'], true)) {
                $aggSort = 'hits';
            }

            $aggQuery = SearchQuery::query();
            $search = trim((string)($filters['q'] ?? ''));
            if ($search !== '') {
                $like = '%' . $search . '%';
                $aggQuery->where(function ($builder) use ($like) {
                    $builder->where('query', 'like', $like)
                        ->orWhere('query_normalized', 'like', mb_strtolower($like));
                });
            }

            $dateFrom = trim((string)($filters['date_from'] ?? ''));
            if ($dateFrom !== '') {
                $aggQuery->where('last_searched_at', '>=', $dateFrom . ' 00:00:00');
            }

            $dateTo = trim((string)($filters['date_to'] ?? ''));
            if ($dateTo !== '') {
                $aggQuery->where('last_searched_at', '<=', $dateTo . ' 23:59:59');
            }

            $aggregated = $aggQuery
                ->orderBy($aggSort, $dir)
                ->when($aggSort !== 'last_searched_at', fn ($builder) => $builder->orderByDesc('last_searched_at'))
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
        }

        return response()->json([
            'ready' => $hasAggregated,
            'logs_ready' => $hasLogs,
            'view' => $view,
            'summary' => $summary,
            'top_queries' => $topQueries,
            'items' => $items,
            'aggregated' => $aggregated,
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

    public function destroyLog(int $id)
    {
        if (!Schema::hasTable('search_query_logs')) {
            return response()->json(['ok' => false, 'message' => 'Журнал поиска не создан'], 409);
        }

        SearchQueryLog::query()->whereKey($id)->delete();

        return response()->json(['ok' => true]);
    }

    public function clear(Request $request)
    {
        $data = $request->validate([
            'scope' => ['required', 'in:logs,aggregated,all'],
        ]);

        $scope = $data['scope'];

        if (($scope === 'logs' || $scope === 'all') && Schema::hasTable('search_query_logs')) {
            SearchQueryLog::query()->delete();
        }

        if (($scope === 'aggregated' || $scope === 'all') && Schema::hasTable('search_queries')) {
            SearchQuery::query()->delete();
        }

        return response()->json(['ok' => true]);
    }
}
