<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeriesViewDaily extends Model
{
    protected $table = 'series_view_daily';

    protected $fillable = [
        'series_id',
        'view_date',
        'views_count',
    ];

    protected $casts = [
        'view_date' => 'date',
        'views_count' => 'integer',
    ];

    public function series(): BelongsTo
    {
        return $this->belongsTo(Series::class, 'series_id');
    }
}
