<?php

namespace App\Http\Controllers;

use App\Models\AdminAuditLog;
use Illuminate\Http\Request;

class AdminAuditLogController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'action' => ['nullable', 'string', 'max:64'],
            'actor_role' => ['nullable', 'string', 'max:32'],
            'entity_type' => ['nullable', 'string', 'max:64'],
            'q' => ['nullable', 'string', 'max:200'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $perPage = (int) ($data['per_page'] ?? 50);
        $page = (int) ($data['page'] ?? 1);

        $query = AdminAuditLog::query()->orderByDesc('id');

        if (!empty($data['action'])) {
            $query->where('action', $data['action']);
        }
        if (!empty($data['actor_role'])) {
            $query->where('actor_role', $data['actor_role']);
        }
        if (!empty($data['entity_type'])) {
            $query->where('entity_type', $data['entity_type']);
        }

        $q = trim((string) ($data['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
            $query->where(function ($builder) use ($like) {
                $builder->where('summary', 'like', $like)
                    ->orWhere('actor_name', 'like', $like)
                    ->orWhere('action', 'like', $like)
                    ->orWhere('entity_id', 'like', $like);
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
}
