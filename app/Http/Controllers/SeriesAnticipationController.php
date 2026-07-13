<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesSeries;
use App\Models\Series;
use App\Services\AnticipationService;
use App\Support\SiteConfig;
use Illuminate\Http\Request;

class SeriesAnticipationController extends Controller
{
    use ResolvesSeries;

    public function show(Request $request, int $seriesId)
    {
        $series = $this->resolveActiveSeries($seriesId);

        return response()->json(AnticipationService::payloadForSeries($series, $request));
    }

    public function vote(Request $request, int $seriesId)
    {
        if (!AnticipationService::isEnabled()) {
            return response()->json(['ok' => false, 'message' => 'Голосование отключено'], 403);
        }

        $data = $request->validate([
            'value' => ['required', 'in:1,-1'],
        ]);

        $series = $this->resolveActiveSeries($seriesId);

        $payload = AnticipationService::vote($series, $request, (int)$data['value']);

        return response()->json(array_merge(['ok' => true], $payload));
    }
}
