<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudioItem extends Model
{
    protected $table = 'studio_items';

    protected $fillable = [
        'studio_id',
        'series_id',
        'rank_order',
    ];

    protected $casts = [
        'rank_order' => 'integer',
    ];

    public function studio()
    {
        return $this->belongsTo(Studio::class, 'studio_id');
    }

    public function series()
    {
        return $this->belongsTo(Series::class, 'series_id');
    }
}
