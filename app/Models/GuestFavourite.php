<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuestFavourite extends Model
{
    protected $fillable = [
        'series_id',
        'guest_key',
    ];

    public function series(): BelongsTo
    {
        return $this->belongsTo(Series::class, 'series_id');
    }
}
