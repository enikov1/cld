<?php

namespace App\Support;

use App\Http\Controllers\AdminPanelController;
use App\Models\AdminToken;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

class AdminAccess
{
    public const COOKIE_NAME = 'admin_site_access';

    public const ACTOR_MASTER = 'master';

    public const ACTOR_TOKEN = 'token';

    public const ROLE_FULL = 'full';

    public const ROLE_CONTENT = 'content';

    public const ROLE_MODERATION = 'moderation';

    public const ROLE_CUSTOM = 'custom';

    private const SESSION_TTL_MINUTES = 60 * 24 * 7;

    private const SESSION_CACHE_PREFIX = 'admin_session:';

    /** @var array{type: string, role: string, name: string, token_id?: int|null, abilities: list<string>}|null */
    private static ?array $resolvedActor = null;

    private static ?string $resolvedRequestId = null;

    public static function expectedToken(): string
    {
        return (string) config('admin.token', '');
    }

    public static function isConfigured(): bool
    {
        if (self::expectedToken() !== '') {
            return true;
        }

        try {
            return AdminToken::query()->where('is_active', true)->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    public static function isAdminPath(Request $request): bool
    {
        $path = trim($request->path(), '/');
        $adminPath = AdminPath::path();

        return $path === $adminPath || str_starts_with($path, $adminPath . '/');
    }

    public static function isAdminApiPath(Request $request): bool
    {
        return $request->is('api/admin') || $request->is('api/admin/*');
    }

    public static function isAdminAssetPath(Request $request): bool
    {
        $prefix = AdminPanelController::ASSET_ROUTE;
        $ui = AdminPanelController::UI_DIR;

        return $request->is($prefix)
            || $request->is($prefix . '/*')
            || $request->is($ui)
            || $request->is($ui . '/*');
    }

    public static function hasValidToken(Request $request): bool
    {
        return self::resolveActor($request) !== null;
    }

    /**
     * @return array{type: string, role: string, name: string, token_id?: int|null, abilities: list<string>}|null
     */
    public static function resolveActor(Request $request): ?array
    {
        $requestId = spl_object_id($request);
        if (self::$resolvedRequestId === (string) $requestId && self::$resolvedActor !== null) {
            return self::$resolvedActor;
        }

        $actor = self::resolveActorUncached($request);
        self::$resolvedActor = $actor;
        self::$resolvedRequestId = (string) $requestId;

        return $actor;
    }

    public static function role(?Request $request = null): ?string
    {
        $actor = self::resolveActor($request ?? request());

        return $actor['role'] ?? null;
    }

    /**
     * @return list<string>
     */
    public static function abilities(?Request $request = null): array
    {
        $actor = self::resolveActor($request ?? request());

        return is_array($actor['abilities'] ?? null) ? $actor['abilities'] : [];
    }

    public static function can(string $ability, ?Request $request = null): bool
    {
        $actor = self::resolveActor($request ?? request());
        if ($actor === null) {
            return false;
        }

        return AdminPermissions::actorCan($actor, $ability);
    }

    public static function matchesMasterToken(?string $token): bool
    {
        $expected = self::expectedToken();
        $token = trim((string) $token);

        return $expected !== '' && $token !== '' && hash_equals($expected, $token);
    }

    public static function canBypassMaintenance(Request $request): bool
    {
        if (self::isAdminPath($request) || self::isAdminApiPath($request) || self::isAdminAssetPath($request)) {
            return true;
        }

        return self::hasValidToken($request);
    }

    public static function makeCookie(?Request $request = null): ?Cookie
    {
        $request ??= request();
        $actor = self::resolveActor($request);
        if ($actor === null) {
            return null;
        }

        $sessionId = bin2hex(random_bytes(32));
        $tokenHash = null;
        $tokenId = $actor['token_id'] ?? null;
        if ($tokenId) {
            try {
                $tokenHash = AdminToken::query()->where('id', (int) $tokenId)->value('token_hash');
            } catch (\Throwable) {
                $tokenHash = null;
            }
        }

        self::sessionStore()->put(
            self::SESSION_CACHE_PREFIX . hash('sha256', $sessionId),
            [
                'created_at' => now()->toIso8601String(),
                'ip' => $request->ip(),
                'role' => $actor['role'],
                'name' => $actor['name'],
                'actor_type' => $actor['type'],
                'token_id' => $tokenId,
                'token_hash' => is_string($tokenHash) ? $tokenHash : null,
                'abilities' => $actor['abilities'],
            ],
            now()->addMinutes(self::SESSION_TTL_MINUTES),
        );

        return cookie(
            self::COOKIE_NAME,
            $sessionId,
            self::SESSION_TTL_MINUTES,
            '/',
            null,
            self::cookieSecure(),
            true,
            false,
            'Lax',
        );
    }

    public static function forgetCookie(?Request $request = null): Cookie
    {
        $request ??= request();
        $cookie = (string) $request->cookie(self::COOKIE_NAME, '');
        if ($cookie !== '' && strlen($cookie) >= 32) {
            self::sessionStore()->forget(self::SESSION_CACHE_PREFIX . hash('sha256', $cookie));
        }

        self::$resolvedActor = null;
        self::$resolvedRequestId = null;

        return cookie()->forget(self::COOKIE_NAME);
    }

    /**
     * Invalidate all cookie sessions bound to a DB token (e.g. after regenerate / deactivate).
     * Sessions store token_hash; actorFromSession rejects mismatched hashes.
     * Callers should bump token_hash or deactivate so existing sessions fail the check.
     */
    public static function invalidateTokenSessions(int $tokenId): void
    {
        // Sessions are opaque cache entries keyed by random id — we cannot enumerate them.
        // Invalidation is enforced in actorFromSession via token_hash / is_active checks.
        unset($tokenId);
        self::clearResolvedActor();
    }

    /**
     * Clear per-request memoization (tests / after token mutations).
     */
    public static function clearResolvedActor(): void
    {
        self::$resolvedActor = null;
        self::$resolvedRequestId = null;
    }

    /**
     * @return array{type: string, role: string, name: string, token_id?: int|null, abilities: list<string>}|null
     */
    private static function resolveActorUncached(Request $request): ?array
    {
        $header = trim((string) $request->header('X-ADMIN-TOKEN', ''));
        if ($header !== '') {
            $fromHeader = self::actorFromPlainToken($header);
            if ($fromHeader !== null) {
                return $fromHeader;
            }
        }

        $cookie = (string) $request->cookie(self::COOKIE_NAME, '');
        if ($cookie === '') {
            return null;
        }

        return self::actorFromSession($cookie);
    }

    /**
     * @return array{type: string, role: string, name: string, token_id: null, abilities: list<string>}
     */
    private static function masterActor(): array
    {
        return [
            'type' => self::ACTOR_MASTER,
            'role' => self::ROLE_FULL,
            'name' => 'ADMIN_TOKEN',
            'token_id' => null,
            'abilities' => [AdminPermissions::ABILITY_ALL],
        ];
    }

    /**
     * @return array{type: string, role: string, name: string, token_id?: int|null, abilities: list<string>}|null
     */
    private static function actorFromPlainToken(string $token): ?array
    {
        if (self::matchesMasterToken($token)) {
            return self::masterActor();
        }

        $hash = AdminToken::hashToken($token);

        try {
            $row = AdminToken::query()
                ->where('is_active', true)
                ->where('token_hash', $hash)
                ->first();
        } catch (\Throwable) {
            return null;
        }

        if (!$row || !hash_equals((string) $row->token_hash, $hash)) {
            return null;
        }

        try {
            $row->touchLastUsed();
        } catch (\Throwable) {
            // Auth must not fail if last_used_at write is unavailable.
        }

        return self::actorFromTokenRow($row);
    }

    /**
     * @return array{type: string, role: string, name: string, token_id: int, abilities: list<string>}
     */
    private static function actorFromTokenRow(AdminToken $row): array
    {
        $abilities = $row->resolvedAbilities();

        return [
            'type' => self::ACTOR_TOKEN,
            'role' => AdminPermissions::inferRole($abilities),
            'name' => (string) $row->name,
            'token_id' => (int) $row->id,
            'abilities' => $abilities,
        ];
    }

    /**
     * @return array{type: string, role: string, name: string, token_id?: int|null, abilities: list<string>}|null
     */
    private static function actorFromSession(string $sessionId): ?array
    {
        if (strlen($sessionId) < 32) {
            return null;
        }

        $payload = self::sessionStore()->get(self::SESSION_CACHE_PREFIX . hash('sha256', $sessionId));
        if (!is_array($payload)) {
            return null;
        }

        $type = (string) ($payload['actor_type'] ?? self::ACTOR_MASTER);
        $tokenId = isset($payload['token_id']) ? (int) $payload['token_id'] : null;

        if ($type === self::ACTOR_MASTER || $tokenId === null || $tokenId === 0) {
            if ($type === self::ACTOR_MASTER) {
                return self::masterActor();
            }

            return null;
        }

        // Always re-load from DB so ability edits apply without re-login.
        try {
            $row = AdminToken::query()
                ->where('id', $tokenId)
                ->where('is_active', true)
                ->first();
        } catch (\Throwable) {
            return null;
        }

        if (!$row) {
            return null;
        }

        $sessionHash = (string) ($payload['token_hash'] ?? '');
        $currentHash = (string) $row->token_hash;
        if ($sessionHash === '' || $currentHash === '' || !hash_equals($currentHash, $sessionHash)) {
            // Secret rotated or legacy session without hash — force re-login.
            self::sessionStore()->forget(self::SESSION_CACHE_PREFIX . hash('sha256', $sessionId));

            return null;
        }

        return self::actorFromTokenRow($row);
    }

    private static function sessionStore(): CacheRepository
    {
        return cache()->store((string) config('admin.session_store', 'admin'));
    }

    private static function cookieSecure(): bool
    {
        if ((bool) config('session.secure', false)) {
            return true;
        }

        $request = request();

        return $request->isSecure() || $request->header('X-Forwarded-Proto') === 'https';
    }
}
