<?php

namespace App\Http\Controllers;

use App\Support\AdminPath;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AdminPanelController extends Controller
{
    public function serve(Request $request, ?string $spaPath = null)
    {
        if ($request->segment(1) !== AdminPath::path()) {
            abort(404);
        }

        if ($spaPath === null && !str_ends_with($request->getPathInfo(), '/')) {
            $query = $request->getQueryString();
            $target = AdminPath::base() . '/' . ($query ? '?' . $query : '');

            return redirect($target, 301);
        }

        $indexPath = public_path('admin/index.html');
        if (!is_file($indexPath)) {
            return response(
                'Admin UI не собран. Выполните: cd site/admin-ui && npm run build',
                503,
                ['Content-Type' => 'text/plain; charset=UTF-8']
            );
        }

        $adminBase = AdminPath::base();
        $baseHref = htmlspecialchars($adminBase . '/', ENT_QUOTES, 'UTF-8');
        $html = file_get_contents($indexPath);
        $inject = '<base href="' . $baseHref . '">'
            . '<script>window.__ADMIN_BASE__=' . json_encode($adminBase) . ';</script>';
        $html = str_replace('</head>', $inject . '</head>', $html);

        return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public function asset(string $file): BinaryFileResponse
    {
        $base = realpath(public_path('admin/assets'));
        $target = realpath(public_path('admin/assets/' . $file));

        if ($base === false || $target === false || !str_starts_with($target, $base . DIRECTORY_SEPARATOR)) {
            abort(404);
        }

        return response()->file($target);
    }
}
