<?php

namespace App\Http\Controllers;

use App\Models\Series;
use App\Support\PublicMedia;
use Illuminate\Http\JsonResponse;

class SeriesGalleryController extends Controller
{
    public function show(int $seriesId): JsonResponse
    {
        $series = Series::query()
            ->published()
            ->findOrFail($seriesId);

        $items = collect(is_array($series->gallery_urls) ? $series->gallery_urls : [])
            ->map(static fn ($url) => trim((string)$url))
            ->filter()
            ->values()
            ->map(static fn (string $url) => ['url' => PublicMedia::url($url)])
            ->all();

        return response()->json([
            'ok' => true,
            'id' => (int)$series->id,
            'title' => (string)$series->title,
            'items' => $items,
        ]);
    }
}
