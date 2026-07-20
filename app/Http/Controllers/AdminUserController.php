<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\SiteConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'name' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'string', 'max:255'],
            'ip' => ['nullable', 'string', 'max:45'],
            'role' => ['nullable', 'in:user,admin,all'],
            'blocked' => ['nullable', 'in:0,1,all'],
            'exact_name' => ['nullable', 'boolean'],
            'exact_email' => ['nullable', 'boolean'],
            'registered_from' => ['nullable', 'date'],
            'registered_to' => ['nullable', 'date'],
            'last_login_from' => ['nullable', 'date'],
            'last_login_to' => ['nullable', 'date'],
            'sort' => ['nullable', 'in:id,name,email,role,created_at,last_login_at'],
            'dir' => ['nullable', 'in:asc,desc'],
            'limit' => ['nullable', 'integer', 'min:10', 'max:500'],
        ]);

        $query = User::query();

        $search = trim((string)($data['q'] ?? ''));
        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%')
                    ->orWhere('last_ip', 'like', '%' . $search . '%')
                    ->orWhere('registration_ip', 'like', '%' . $search . '%');
            });
        }

        $name = trim((string)($data['name'] ?? ''));
        if ($name !== '') {
            if (!empty($data['exact_name'])) {
                $query->where('name', $name);
            } else {
                $query->where('name', 'like', '%' . $name . '%');
            }
        }

        $email = trim((string)($data['email'] ?? ''));
        if ($email !== '') {
            if (!empty($data['exact_email'])) {
                $query->where('email', $email);
            } else {
                $query->where('email', 'like', '%' . $email . '%');
            }
        }

        $ip = trim((string)($data['ip'] ?? ''));
        if ($ip !== '') {
            $query->where(function ($builder) use ($ip) {
                $builder->where('last_ip', 'like', '%' . $ip . '%')
                    ->orWhere('registration_ip', 'like', '%' . $ip . '%');
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

        $registeredFrom = trim((string)($data['registered_from'] ?? ''));
        if ($registeredFrom !== '') {
            $query->where('created_at', '>=', $registeredFrom . ' 00:00:00');
        }

        $registeredTo = trim((string)($data['registered_to'] ?? ''));
        if ($registeredTo !== '') {
            $query->where('created_at', '<=', $registeredTo . ' 23:59:59');
        }

        $lastLoginFrom = trim((string)($data['last_login_from'] ?? ''));
        if ($lastLoginFrom !== '') {
            $query->where('last_login_at', '>=', $lastLoginFrom . ' 00:00:00');
        }

        $lastLoginTo = trim((string)($data['last_login_to'] ?? ''));
        if ($lastLoginTo !== '') {
            $query->where('last_login_at', '<=', $lastLoginTo . ' 23:59:59');
        }

        $sort = $data['sort'] ?? 'id';
        $dir = $data['dir'] ?? 'desc';
        $limit = (int)($data['limit'] ?? 100);

        $items = $query
            ->orderBy($sort, $dir)
            ->when($sort !== 'id', fn ($builder) => $builder->orderByDesc('id'))
            ->limit($limit)
            ->get($this->userColumns());

        return response()->json([
            'items' => $items,
            'total' => $items->count(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatedPayload($request, null);

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
            'is_blocked' => (bool)($data['is_blocked'] ?? false),
            'registration_ip' => $data['registration_ip'] ?? null,
            'last_ip' => $data['last_ip'] ?? ($data['registration_ip'] ?? null),
        ]);

        return response()->json([
            'ok' => true,
            'item' => $user->fresh()->only($this->userColumns()),
        ], 201);
    }

    public function update(Request $request, int $id)
    {
        $user = User::query()->findOrFail($id);
        $data = $this->validatedPayload($request, $user);

        if (array_key_exists('name', $data) && $data['name'] !== null) {
            $user->name = $data['name'];
        }

        if (array_key_exists('email', $data) && $data['email'] !== null) {
            $user->email = $data['email'];
        }

        if (!empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        if (array_key_exists('role', $data) && $data['role'] !== null) {
            if ($user->role === 'admin' && $data['role'] !== 'admin' && $this->isLastAdmin($user->id)) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Нельзя снять роль с последнего администратора',
                ], 422);
            }
            $user->role = $data['role'];
        }

        if (array_key_exists('is_blocked', $data)) {
            if ($user->role === 'admin' && (bool)$data['is_blocked'] && $this->isLastAdmin($user->id)) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Нельзя заблокировать последнего администратора',
                ], 422);
            }
            $user->is_blocked = (bool)$data['is_blocked'];
        }

        if (array_key_exists('registration_ip', $data)) {
            $user->registration_ip = $data['registration_ip'] ?: null;
        }

        if (array_key_exists('last_ip', $data)) {
            $user->last_ip = $data['last_ip'] ?: null;
        }

        $user->save();

        return response()->json([
            'ok' => true,
            'item' => $user->fresh()->only($this->userColumns()),
        ]);
    }

    public function destroy(int $id)
    {
        $user = User::query()->findOrFail($id);

        if ($user->role === 'admin' && $this->isLastAdmin($user->id)) {
            return response()->json([
                'ok' => false,
                'message' => 'Нельзя удалить последнего администратора',
            ], 422);
        }

        $user->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * @return list<string>
     */
    private function userColumns(): array
    {
        return [
            'id',
            'name',
            'email',
            'role',
            'is_blocked',
            'last_login_at',
            'last_ip',
            'registration_ip',
            'created_at',
            'updated_at',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedPayload(Request $request, ?User $user): array
    {
        $nameMax = SiteConfig::int('auth_name_max_length');
        $emailMax = SiteConfig::int('auth_email_max_length');
        $creating = $user === null;

        return $request->validate([
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:' . $nameMax],
            'email' => [
                $creating ? 'required' : 'sometimes',
                'email',
                'max:' . $emailMax,
                Rule::unique('users', 'email')->ignore($user?->id),
            ],
            'password' => [
                $creating ? 'required' : 'nullable',
                'string',
                SiteConfig::passwordRule(),
            ],
            'role' => [$creating ? 'required' : 'nullable', Rule::in(['user', 'admin'])],
            'is_blocked' => ['nullable', 'boolean'],
            'registration_ip' => ['nullable', 'string', 'max:45'],
            'last_ip' => ['nullable', 'string', 'max:45'],
        ]);
    }

    private function isLastAdmin(int $exceptId): bool
    {
        return !User::query()
            ->where('role', 'admin')
            ->where('is_blocked', false)
            ->where('id', '!=', $exceptId)
            ->exists();
    }
}
