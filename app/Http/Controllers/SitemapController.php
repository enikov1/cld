<?php

namespace App\Http\Controllers;

use App\Services\SitemapService;
use App\Support\SiteConfig;
use Illuminate\Support\Facades\File;

class SitemapController extends Controller
{
    public function index(SitemapService $sitemap)
    {
        if (SiteConfig::bool('maintenance_enabled')) {
            abort(404);
        }

        $sitemap->ensureFresh();

        $path = $sitemap->path();
        if (!File::exists($path)) {
            abort(404);
        }

        return response(File::get($path), 200, [
            'Content-Type' => 'application/xml; charset=utf-8',
        ]);
    }
}
