<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotificationEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'series_id',
        'episode_id',
        'season_number',
        'episode_number',
        'voice',
        'event_type',
        'payload',
        'created_at',
    ];

    protected $casts = [
        'season_number' => 'integer',
        'episode_number' => 'integer',
        'payload' => 'array',
        'created_at' => 'datetime',
    ];

    public function series(): BelongsTo
    {
        return $this->belongsTo(Series::class, 'series_id');
    }

    public function episode(): BelongsTo
    {
        return $this->belongsTo(Episode::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(NotificationDelivery::class);
    }
}
