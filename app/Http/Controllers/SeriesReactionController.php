<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesSeries;
use App\Models\Series;
use App\Services\ReactionWidgetService;
use App\Support\SiteConfig;
use App\Support\TplCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SeriesReactionController extends Controller
{
    use ResolvesSeries;

    public function show(Request $request, int $seriesId)
    {
        $series = $this->resolveActiveSeries($seriesId);

        if (!ReactionWidgetService::isEnabled()) {
            return response()->json(['enabled' => false]);
        }

        return response()->json(ReactionWidgetService::payloadForSeries($series, $request));
    }

    public function vote(Request $request, int $seriesId)
    {
        $data = $request->validate([
            'reaction_type_id' => ['required', 'integer', 'exists:reaction_types,id'],
        ]);

        $series = $this->resolveActiveSeries($seriesId);

        if (!ReactionWidgetService::isEnabled()) {
            return response()->json(['ok' => false, 'message' => SiteConfig::str('reactions_msg_disabled')], 403);
        }

        if (!Auth::check() && !SiteConfig::bool('reactions_guest_enabled')) {
            return response()->json(['ok' => false, 'message' => SiteConfig::str('auth_msg_auth_required')], 401);
        }

        $payload = ReactionWidgetService::vote($series, $request, (int)$data['reaction_type_id']);
        TplCache::forgetSeries($series->id);

        return response()->json(array_merge(['ok' => true], $payload));
    }
}
