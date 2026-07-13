<?php

namespace App\Http\Middleware;

use App\Support\AdminAccess;
use App\Support\MaintenancePageRenderer;
use App\Support\SiteConfig;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckMaintenanceMode
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!SiteConfig::bool('maintenance_enabled')) {
            return $next($request);
        }

        if ($request->is('up') || $request->is('robots.txt') || $request->is('sitemap.xml') || $request->is('api/site/admin-path')) {
            return $next($request);
        }

        if (AdminAccess::canBypassMaintenance($request)) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => SiteConfig::str('maintenance_message') ?: 'Сайт временно недоступен.',
            ], 503)->header('Retry-After', '3600');
        }

        return MaintenancePageRenderer::response();
    }
}
