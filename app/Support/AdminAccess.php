<?php

namespace App\Support;

use App\Http\Controllers\AdminPanelController;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

class AdminAccess
{
    public const COOKIE_NAME = 'admin_site_access';

    private const SESSION_TTL_MINUTES = 60 * 24 * 7;

    private const SESSION_CACHE_PREFIX = 'admin_session:';

    public static function expectedToken(): string
    {
        return (string) config('admin.token', '');
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
        $expected = self::expectedToken();
        if ($expected === '') {
            return false;
        }

        $header = (string) $request->header('X-ADMIN-TOKEN', '');
        if ($header !== '' && hash_equals($expected, $header)) {
            return true;
        }

        $cookie = (string) $request->cookie(self::COOKIE_NAME, '');
        if ($cookie === '') {
            return false;
        }

        // Legacy cookies stored the raw ADMIN_TOKEN — accept once until re-login.
        if (hash_equals($expected, $cookie)) {
            return true;
        }

        return self::sessionIsValid($cookie);
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

    public static function makeCookie(): ?Cookie
    {
        if (self::expectedToken() === '') {
            return null;
        }

        $sessionId = bin2hex(random_bytes(32));
        self::sessionStore()->put(
            self::SESSION_CACHE_PREFIX . hash('sha256', $sessionId),
            [
                'created_at' => now()->toIso8601String(),
                'ip' => request()->ip(),
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
        if ($cookie !== '' && !hash_equals(self::expectedToken(), $cookie)) {
            self::sessionStore()->forget(self::SESSION_CACHE_PREFIX . hash('sha256', $cookie));
        }

        return cookie()->forget(self::COOKIE_NAME);
    }

    private static function sessionIsValid(string $sessionId): bool
    {
        if (strlen($sessionId) < 32) {
            return false;
        }

        return self::sessionStore()->has(self::SESSION_CACHE_PREFIX . hash('sha256', $sessionId));
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
