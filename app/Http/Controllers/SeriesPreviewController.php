<?php

namespace App\Http\Controllers;

use App\Models\Series;
use App\Services\SeriesPreviewService;
use Illuminate\Http\JsonResponse;

class SeriesPreviewController extends TplController
{
    public function show(int $seriesId): JsonResponse
    {
        $series = Series::query()
            ->with(['genres', 'actors', 'directors'])
            ->where('is_active', true)
            ->where('is_hidden', false)
            ->findOrFail($seriesId);

        $preview = SeriesPreviewService::payloadForSeries($series);

        return response()->json([
            'ok' => true,
            'id' => $series->id,
            'url' => url($preview['url']),
            'html' => $this->renderPartial('partials/series_preview.tpl', ['preview' => $preview]),
        ]);
    }
}
