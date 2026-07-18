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

        // Prefer external IDs over PK so tmdb_id/kp_id cannot collide with another row's id.
        // Group orWhere so SoftDeletes (deleted_at IS NULL) applies to both branches.
        $series = (clone $query)
            ->where(function ($q) use ($key) {
                $q->where('kp_id', $key)->orWhere('tmdb_id', $key);
            })
            ->first();

        if ($series) {
            return $series;
        }

        if (ctype_digit($key)) {
            $byId = (clone $query)->find((int) $key);
            if ($byId) {
                return $byId;
            }
        }

        throw (new ModelNotFoundException())->setModel(Series::class, [$key]);
    }
}
