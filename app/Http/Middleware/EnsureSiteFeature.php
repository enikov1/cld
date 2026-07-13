<?php

namespace App\Http\Middleware;

use App\Support\SiteConfig;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSiteFeature
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        if (!SiteConfig::bool($feature)) {
            abort(404);
        }

        return $next($request);
    }
}
