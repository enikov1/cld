<?php

namespace App\Http\Controllers;

use App\Support\AdminPath;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AdminPanelController extends Controller
{
    public const ASSET_ROUTE = '_admin-assets';

    public const UI_DIR = '_admin_ui';

    public static function assetRoutePrefix(): string
    {
        return self::ASSET_ROUTE;
    }

    public static function uiPath(string $relative = ''): string
    {
        $base = public_path(self::UI_DIR);

        return $relative === '' ? $base : $base . DIRECTORY_SEPARATOR . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative), DIRECTORY_SEPARATOR);
    }

    public function serve(Request $request, ?string $spaPath = null)
    {
        if ($request->segment(1) !== AdminPath::path()) {
            abort(404);
        }

        // No trailing-slash redirect: .htaccess strips slashes for non-directories,
        // so forcing /admin → /admin/ would loop with R=301 + directory rules.

        $indexPath = self::uiPath('index.html');
        if (!is_file($indexPath)) {
            return response(
                'Admin UI не собран. Выполните: cd site/admin-ui && npm run build',
                503,
                ['Content-Type' => 'text/plain; charset=UTF-8']
            );
        }

        $adminBase = AdminPath::base();
        $html = self::prepareAdminHtml(file_get_contents($indexPath), $adminBase);

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            // Hashed asset filenames change every build — never cache the shell HTML.
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
        ]);
    }

    public static function prepareAdminHtml(string $html, string $adminBase): string
    {
        // Prefer the fixed public folder so Apache/nginx can serve files without PHP.
        $assetBase = '/' . self::UI_DIR . '/assets/';
        $html = str_replace('./assets/', $assetBase, $html);
        $html = str_replace('href="./favicon.svg"', 'href="/' . self::UI_DIR . '/favicon.svg"', $html);
        $html = str_replace('href="./icons.svg"', 'href="/' . self::UI_DIR . '/icons.svg"', $html);

        $baseHref = htmlspecialchars($adminBase . '/', ENT_QUOTES, 'UTF-8');
        $inject = '<base href="' . $baseHref . '">'
            . '<script>window.__ADMIN_BASE__=' . json_encode($adminBase) . ';</script>';

        return str_replace('</head>', $inject . '</head>', $html);
    }

    public function asset(string $file): BinaryFileResponse
    {
        $base = realpath(self::uiPath('assets'));
        $target = realpath(self::uiPath('assets/' . $file));

        if ($base === false || $target === false || !str_starts_with($target, $base . DIRECTORY_SEPARATOR)) {
            abort(404);
        }

        return response()->file($target, self::assetHeaders($file));
    }

    /**
     * @return array<string, string>
     */
    private static function assetHeaders(string $file): array
    {
        $lower = strtolower($file);
        $headers = [
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ];

        if (str_ends_with($lower, '.js')) {
            $headers['Content-Type'] = 'application/javascript; charset=UTF-8';
        } elseif (str_ends_with($lower, '.css')) {
            $headers['Content-Type'] = 'text/css; charset=UTF-8';
        }

        return $headers;
    }
}
