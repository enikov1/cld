<?php

namespace App\Support;

use App\Http\Controllers\AdminPanelController;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

class AdminAccess
{
    public const COOKIE_NAME = 'admin_site_access';

    public static function expectedToken(): string
    {
        return (string)config('admin.token', '');
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

        return $request->is($prefix) || $request->is($prefix . '/*');
    }

    public static function hasValidToken(Request $request): bool
    {
        $expected = self::expectedToken();
        if ($expected === '') {
            return app()->environment('local');
        }

        $header = (string)$request->header('X-ADMIN-TOKEN', '');
        if ($header !== '' && hash_equals($expected, $header)) {
            return true;
        }

        $cookie = (string)$request->cookie(self::COOKIE_NAME, '');

        return $cookie !== '' && hash_equals($expected, $cookie);
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
        $token = self::expectedToken();
        if ($token === '') {
            return null;
        }

        return cookie(
            self::COOKIE_NAME,
            $token,
            60 * 24 * 30,
            '/',
            null,
            (bool)config('session.secure', false),
            true,
            false,
            'Lax',
        );
    }

    public static function forgetCookie(): Cookie
    {
        return cookie()->forget(self::COOKIE_NAME);
    }
}
