<?php

namespace App\Http\Controllers;

use App\Models\AdminToken;
use App\Support\AdminAccess;
use App\Support\AdminAudit;
use App\Support\AdminPermissions;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminTokensController extends Controller
{
    public function meta()
    {
        return response()->json([
            'catalog' => AdminPermissions::catalog(),
            'presets' => AdminPermissions::presets(),
            'roles' => [
                ['value' => 'full', 'label' => 'Полный доступ'],
                ['value' => 'content', 'label' => 'Контент (пресет)'],
                ['value' => 'moderation', 'label' => 'Модерация (пресет)'],
                ['value' => 'custom', 'label' => 'Свой набор прав'],
            ],
        ]);
    }

    public function index()
    {
        $items = AdminToken::query()
            ->orderByDesc('id')
            ->get()
            ->map(fn (AdminToken $token) => $token->toAdminArray())
            ->values();

        return response()->json(['items' => $items]);
    }

    public function show(int $id)
    {
        $token = AdminToken::query()->findOrFail($id);

        return response()->json(['item' => $token->toAdminArray()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'role' => ['nullable', Rule::in(AdminToken::ROLES)],
            'preset' => ['nullable', Rule::in(array_keys(AdminPermissions::presets()))],
            'abilities' => ['nullable', 'array', 'min:1'],
            'abilities.*' => ['string', Rule::in(array_merge(AdminPermissions::allAbilityKeys(), [AdminPermissions::ABILITY_ALL]))],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $abilities = $this->resolveAbilitiesFromInput($data);
        if ($abilities === []) {
            return response()->json(['error' => 'Выберите пресет или хотя бы одно право доступа'], 422);
        }

        $actor = AdminAccess::resolveActor($request);
        if ($actor === null || !AdminPermissions::actorMayGrant($actor, $abilities)) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'Нельзя выдать права шире своих',
            ], 403);
        }

        $role = AdminPermissions::inferRole($abilities);
        $plaintext = AdminToken::generatePlaintext();

        $token = AdminToken::query()->create([
            'name' => trim($data['name']),
            'token_hash' => AdminToken::hashToken($plaintext),
            'role' => $role,
            'abilities' => $abilities,
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
        ]);

        AdminAudit::log(
            'admin_token.create',
            'admin_token',
            $token->id,
            'Создан токен «' . $token->name . '» (' . $role . ')',
            ['role' => $role, 'abilities' => $abilities],
            $request,
        );

        return response()->json([
            'ok' => true,
            'item' => $token->toAdminArray(),
            'token' => $plaintext,
        ], 201);
    }

    public function update(Request $request, int $id)
    {
        $token = AdminToken::query()->findOrFail($id);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'role' => ['nullable', Rule::in(AdminToken::ROLES)],
            'preset' => ['nullable', Rule::in(array_keys(AdminPermissions::presets()))],
            'abilities' => ['nullable', 'array', 'min:1'],
            'abilities.*' => ['string', Rule::in(array_merge(AdminPermissions::allAbilityKeys(), [AdminPermissions::ABILITY_ALL]))],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if (array_key_exists('name', $data)) {
            $token->name = trim((string) $data['name']);
        }

        if (
            array_key_exists('abilities', $data)
            || array_key_exists('preset', $data)
            || array_key_exists('role', $data)
        ) {
            $abilities = $this->resolveAbilitiesFromInput($data);
            if ($abilities === []) {
                return response()->json(['error' => 'Выберите пресет или хотя бы одно право доступа'], 422);
            }
            $actor = AdminAccess::resolveActor($request);
            if ($actor === null || !AdminPermissions::actorMayGrant($actor, $abilities)) {
                return response()->json([
                    'error' => 'Forbidden',
                    'message' => 'Нельзя выдать права шире своих',
                ], 403);
            }
            $token->abilities = $abilities;
            $token->role = AdminPermissions::inferRole($abilities);
        }

        if (array_key_exists('is_active', $data)) {
            $actor = AdminAccess::resolveActor($request);
            $disablingSelf = !$data['is_active'] && ($actor['token_id'] ?? null) === (int) $token->id;
            if ($disablingSelf) {
                return response()->json([
                    'error' => 'Нельзя отключить токен, которым вы сейчас вошли',
                ], 422);
            }
            $token->is_active = (bool) $data['is_active'];
        }

        $token->save();

        AdminAudit::log(
            'admin_token.update',
            'admin_token',
            $token->id,
            'Обновлён токен «' . $token->name . '» (' . $token->role . ')',
            [
                'role' => $token->role,
                'abilities' => $token->resolvedAbilities(),
                'is_active' => $token->is_active,
            ],
            $request,
        );

        AdminAccess::invalidateTokenSessions((int) $token->id);

        return response()->json([
            'ok' => true,
            'item' => $token->fresh()->toAdminArray(),
        ]);
    }

    public function regenerate(Request $request, int $id)
    {
        $token = AdminToken::query()->findOrFail($id);
        $plaintext = AdminToken::generatePlaintext();
        $token->token_hash = AdminToken::hashToken($plaintext);
        $token->save();

        AdminAudit::log(
            'admin_token.regenerate',
            'admin_token',
            $token->id,
            'Перевыпущен секрет токена «' . $token->name . '»',
            [],
            $request,
        );

        // New hash invalidates cookie sessions that stored the previous token_hash.
        AdminAccess::invalidateTokenSessions((int) $token->id);

        return response()->json([
            'ok' => true,
            'item' => $token->toAdminArray(),
            'token' => $plaintext,
        ]);
    }

    public function destroy(Request $request, int $id)
    {
        $token = AdminToken::query()->findOrFail($id);

        $actor = AdminAccess::resolveActor($request);
        if (($actor['token_id'] ?? null) === (int) $token->id) {
            return response()->json([
                'error' => 'Нельзя удалить токен, которым вы сейчас вошли',
            ], 422);
        }

        $name = $token->name;
        $role = $token->role;
        $abilities = $token->resolvedAbilities();
        $token->delete();

        AdminAudit::log(
            'admin_token.delete',
            'admin_token',
            $id,
            'Удалён токен «' . $name . '» (' . $role . ')',
            ['role' => $role, 'abilities' => $abilities],
            $request,
        );

        AdminAccess::invalidateTokenSessions($id);

        return response()->json(['ok' => true]);
    }

    public function me(Request $request)
    {
        $actor = AdminAccess::resolveActor($request);
        if ($actor === null) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return response()->json([
            'actor_type' => $actor['type'],
            'name' => $actor['name'],
            'role' => $actor['role'],
            'token_id' => $actor['token_id'] ?? null,
            'abilities' => $actor['abilities'] ?? [],
            'pages' => AdminPermissions::pageKeysForActor($actor),
        ]);
    }

    /**
     * @param array<string, mixed> $data
     * @return list<string>
     */
    private function resolveAbilitiesFromInput(array $data): array
    {
        if (isset($data['abilities']) && is_array($data['abilities']) && $data['abilities'] !== []) {
            return AdminPermissions::normalizeAbilities($data['abilities']);
        }

        if (!empty($data['preset'])) {
            return AdminPermissions::normalizeAbilities(
                AdminPermissions::presets()[(string) $data['preset']] ?? [],
            );
        }

        if (!empty($data['role'])) {
            return AdminPermissions::normalizeAbilities(null, (string) $data['role']);
        }

        return [];
    }
}
