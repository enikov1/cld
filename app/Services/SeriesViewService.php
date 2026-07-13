<?php

namespace App\Services;

use App\Models\Series;
use App\Models\SeriesViewDaily;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SeriesViewService
{
    private const DEDUP_MINUTES = 30;

    public static function record(Series $series, Request $request): void
    {
        $sessionHash = hash('sha256', $request->session()->getId());
        $cacheKey = 'series_view:' . $series->id . ':' . $sessionHash;

        if (!Cache::add($cacheKey, 1, now()->addMinutes(self::DEDUP_MINUTES))) {
            return;
        }

        $today = now()->toDateString();

        DB::transaction(function () use ($series, $today): void {
            Series::query()->whereKey($series->id)->increment('views_count');

            $daily = SeriesViewDaily::query()->firstOrNew([
                'series_id' => $series->id,
                'view_date' => $today,
            ]);

            $daily->views_count = (int)($daily->views_count ?? 0) + 1;
            $daily->save();
        });

        $series->refresh();
    }

    /**
     * @param array<int> $seriesIds
     * @return array<int, int> series_id => views sum
     */
    public static function viewsSumForSeriesIds(array $seriesIds, int $days): array
    {
        $seriesIds = array_values(array_unique(array_filter(array_map('intval', $seriesIds))));
        if ($seriesIds === [] || $days < 1) {
            return [];
        }

        $fromDate = now()->subDays($days - 1)->toDateString();

        return SeriesViewDaily::query()
            ->selectRaw('series_id, SUM(views_count) as total')
            ->whereIn('series_id', $seriesIds)
            ->where('view_date', '>=', $fromDate)
            ->groupBy('series_id')
            ->pluck('total', 'series_id')
            ->map(fn ($v) => (int)$v)
            ->all();
    }
}
