<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Series;

trait ResolvesSeries
{
    protected function resolveActiveSeries(int $seriesId): Series
    {
        return Series::query()
            ->where('is_active', true)
            ->findOrFail($seriesId);
    }
}
