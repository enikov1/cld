<?php

namespace App\Http\Middleware;

use App\Support\AdminAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyAdminToken
{
    /**
     * Verify admin token by header.
     * Header: X-ADMIN-TOKEN
     * Token is taken from env ADMIN_TOKEN.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expected = AdminAccess::expectedToken();

        if ($expected === '') {
            if (app()->environment('local')) {
                return $next($request);
            }

            return response()->json([
                'error' => 'ADMIN_TOKEN не задан. Укажите токен в .env для доступа к API админки.',
            ], 503);
        }

        if (!AdminAccess::hasValidToken($request)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}

