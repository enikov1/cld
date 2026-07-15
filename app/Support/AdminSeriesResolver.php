<?php

namespace App\Support;

use App\Models\Series;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class AdminSeriesResolver
{
    public static function byKey(string $key, bool $withTrashed = false): Series
    {
        $key = trim($key);
        if ($key === '') {
            throw (new ModelNotFoundException())->setModel(Series::class);
        }

        $query = Series::query();
        if ($withTrashed) {
            $query->withTrashed();
        }

        if (ctype_digit($key)) {
            $byId = (clone $query)->find((int)$key);
            if ($byId) {
                return $byId;
            }
        }

        $series = (clone $query)
            ->where('kp_id', $key)
            ->orWhere('tmdb_id', $key)
            ->first();

        if (!$series) {
            throw (new ModelNotFoundException())->setModel(Series::class, [$key]);
        }

        return $series;
    }
}
