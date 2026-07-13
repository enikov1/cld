<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'role' => ['nullable', 'in:user,admin,all'],
            'blocked' => ['nullable', 'in:0,1,all'],
        ]);

        $query = User::query()->orderByDesc('id');

        $search = trim((string)($data['q'] ?? ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%');
            });
        }

        $role = $data['role'] ?? 'all';
        if ($role !== 'all') {
            $query->where('role', $role);
        }

        $blocked = $data['blocked'] ?? 'all';
        if ($blocked === '1') {
            $query->where('is_blocked', true);
        } elseif ($blocked === '0') {
            $query->where('is_blocked', false);
        }

        $items = $query
            ->limit(200)
            ->get(['id', 'name', 'email', 'role', 'is_blocked', 'created_at']);

        return response()->json(['items' => $items]);
    }

    public function update(Request $request, int $id)
    {
        $user = User::query()->findOrFail($id);

        $data = $request->validate([
            'role' => ['nullable', Rule::in(['user', 'admin'])],
            'is_blocked' => ['nullable', 'boolean'],
        ]);

        if (array_key_exists('role', $data) && $data['role'] !== null) {
            $user->role = $data['role'];
        }

        if (array_key_exists('is_blocked', $data)) {
            $user->is_blocked = (bool)$data['is_blocked'];
        }

        $user->save();

        return response()->json([
            'ok' => true,
            'item' => $user->fresh()->only(['id', 'name', 'email', 'role', 'is_blocked', 'created_at']),
        ]);
    }
}
