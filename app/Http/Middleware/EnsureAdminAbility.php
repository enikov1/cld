<?php

namespace App\Http\Middleware;

use App\Support\AdminAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminAbility
{
    public function handle(Request $request, Closure $next, string $ability): Response
    {
        if (!AdminAccess::can($ability, $request)) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'Недостаточно прав для этого действия',
            ], 403);
        }

        return $next($request);
    }
}
