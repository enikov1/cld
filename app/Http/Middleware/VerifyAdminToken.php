<?php

namespace App\Http\Middleware;

use App\Support\AdminAccess;
use App\Support\AdminPermissions;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyAdminToken
{
    /**
     * Verify admin token by header or session cookie.
     * Accepts env ADMIN_TOKEN (master/full) or active rows from admin_tokens.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!AdminAccess::isConfigured()) {
            return response()->json([
                'error' => 'ADMIN_TOKEN не задан. Укажите токен в .env для доступа к API админки.',
            ], 503);
        }

        if (!AdminAccess::hasValidToken($request)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        if ($this->requiresCsrfGuard($request) && !$this->hasCsrfHeader($request)) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'Отсутствует заголовок X-Requested-With',
            ], 403);
        }

        $ability = AdminPermissions::requiredAbility($request);
        if ($ability === AdminPermissions::ABILITY_DENY) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'Недостаточно прав для этого действия',
            ], 403);
        }
        if ($ability !== null && !AdminAccess::can($ability, $request)) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'Недостаточно прав для этого действия',
            ], 403);
        }

        return $next($request);
    }

    private function requiresCsrfGuard(Request $request): bool
    {
        if (in_array(strtoupper($request->method()), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return false;
        }

        // Explicit token header authenticates the request; cookie-only sessions need CSRF header.
        return trim((string) $request->header('X-ADMIN-TOKEN', '')) === '';
    }

    private function hasCsrfHeader(Request $request): bool
    {
        return $request->header('X-Requested-With') === 'XMLHttpRequest';
    }
}
