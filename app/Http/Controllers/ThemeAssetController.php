<?php

namespace App\Http\Controllers;

use App\Support\ThemeManager;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Response as ResponseFactory;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ThemeAssetController extends Controller
{
    private const MIME = [
        'css' => 'text/css; charset=utf-8',
        'js' => 'application/javascript; charset=utf-8',
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
    ];

    private const ALLOWED_EXT = 'css|js|svg|png|jpg|jpeg|gif|webp|woff2?';

    public function show(string $theme, string $path): BinaryFileResponse|Response
    {
        if (!ThemeManager::themeExists($theme)) {
            abort(404);
        }

        $resolved = $this->resolveThemeFile($theme, $path);
        if ($resolved === null) {
            abort(404);
        }

        $ext = strtolower(pathinfo($resolved, PATHINFO_EXTENSION));
        if (in_array($ext, ['css', 'js'], true)) {
            return $this->compressedTextResponse($resolved, $ext);
        }

        return ResponseFactory::file($resolved, [
            'Content-Type' => self::MIME[$ext] ?? 'application/octet-stream',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }

    private function compressedTextResponse(string $path, string $ext): Response
    {
        $content = file_get_contents($path);
        if ($content === false) {
            abort(404);
        }

        $headers = [
            'Content-Type' => self::MIME[$ext],
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'Vary' => 'Accept-Encoding',
        ];

        $acceptEncoding = request()->header('Accept-Encoding', '');
        if (str_contains($acceptEncoding, 'gzip')) {
            $compressed = gzencode($content, 9);
            if ($compressed !== false) {
                return response($compressed, 200, array_merge($headers, [
                    'Content-Encoding' => 'gzip',
                ]));
            }
        }

        return response($content, 200, $headers);
    }

    private function resolveThemeFile(string $theme, string $path): ?string
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');
        if ($path === '' || str_contains($path, '..')) {
            return null;
        }

        if (!preg_match('/\.(?:' . self::ALLOWED_EXT . ')$/i', $path)) {
            return null;
        }

        $themeRoot = realpath(ThemeManager::themesRoot() . DIRECTORY_SEPARATOR . $theme);
        if ($themeRoot === false) {
            return null;
        }

        $candidates = [$path];
        if (!str_contains($path, '/')) {
            $candidates[] = 'assets/' . $path;
        }

        foreach ($candidates as $candidate) {
            $full = $themeRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $candidate);
            $real = realpath($full);
            if ($real && is_file($real) && str_starts_with($real, $themeRoot . DIRECTORY_SEPARATOR)) {
                if (ThemeManager::shouldUseMinifiedAssets() && preg_match('/\.(css|js)$/i', $real) && !preg_match('/\.min\.(css|js)$/i', $real)) {
                    $minPath = preg_replace('/\.(css|js)$/i', '.min.$1', $real);
                    if (is_file($minPath)) {
                        return $minPath;
                    }
                }

                return $real;
            }
        }

        return null;
    }
}
