<?php

namespace App\Http\Middleware;

use App\Services\RedirectService;
use App\Support\AdminPath;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplySiteRedirects
{
    public function __construct(
        private readonly RedirectService $redirects,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!$this->shouldCheck($request)) {
            return $next($request);
        }

        $match = $this->redirects->match($request->getPathInfo());
        if ($match === null) {
            return $next($request);
        }

        $this->redirects->recordHit($match['id']);

        $target = $match['to'];
        $query = $request->getQueryString();
        if ($query !== null && $query !== '' && !str_contains($target, '?')) {
            $target .= '?' . $query;
        }

        if (preg_match('#^https?://#i', $target)) {
            return redirect()->away($target, $match['code']);
        }

        return redirect($target, $match['code']);
    }

    private function shouldCheck(Request $request): bool
    {
        if (!$request->isMethod('GET') && !$request->isMethod('HEAD')) {
            return false;
        }

        if ($request->is('api/*') || $request->is('up') || $request->is('robots.txt') || $request->is('sitemap.xml')) {
            return false;
        }

        $adminBase = trim(AdminPath::base(), '/');
        if ($adminBase !== '' && $request->is($adminBase, $adminBase . '/*')) {
            return false;
        }

        return true;
    }
}
