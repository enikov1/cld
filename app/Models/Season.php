<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Season extends Model
{
    protected $fillable = [
        'series_id',
        'season_number',
        'title',
    ];

    protected $casts = [
        'season_number' => 'integer',
    ];

    public function series(): BelongsTo
    {
        return $this->belongsTo(Series::class, 'series_id');
    }

    public function episodes(): HasMany
    {
        return $this->hasMany(Episode::class)->orderBy('episode_number');
    }

    public function displayTitle(): string
    {
        return $this->title ?: ('Сезон ' . $this->season_number);
    }
}
