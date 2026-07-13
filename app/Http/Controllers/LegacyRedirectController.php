<?php

namespace App\Http\Controllers;

use App\Models\Series;
use App\Support\SeriesUrl;
use Illuminate\Http\RedirectResponse;

class LegacyRedirectController extends Controller
{
    public function series(string $category, string $slug): RedirectResponse
    {
        $series = Series::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->where('is_hidden', false)
            ->firstOrFail();

        return redirect(SeriesUrl::path($series), 301);
    }

    public function category(string $category): RedirectResponse
    {
        return redirect('/', 301);
    }

    public function categoryPage(string $category, int $page): RedirectResponse
    {
        if ($page > 1) {
            return redirect('/page/' . $page . '/', 301);
        }

        return redirect('/', 301);
    }
}
