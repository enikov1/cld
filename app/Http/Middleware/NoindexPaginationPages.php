<?php

namespace App\Http\Middleware;

use App\Support\PaginationHelper;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NoindexPaginationPages
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $page = (int) $request->route('page', 1);
        if (!PaginationHelper::isPaginatedRequest($page, '/' . ltrim($request->path(), '/'))) {
            return $response;
        }

        if (!$response->headers->has('X-Robots-Tag')) {
            $response->headers->set('X-Robots-Tag', 'noindex, follow', false);
        }

        return $response;
    }
}
