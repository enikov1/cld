<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Episode extends Model
{
    public const STATUS_RELEASED = 'released';
    public const STATUS_SCHEDULED = 'scheduled';

    protected $fillable = [
        'season_id',
        'episode_number',
        'title',
        'release_at',
        'status',
        'voice',
    ];

    protected $casts = [
        'episode_number' => 'integer',
        'release_at' => 'datetime',
    ];

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function isReleased(): bool
    {
        return $this->status === self::STATUS_RELEASED;
    }

    public function displayTitle(): string
    {
        return $this->title ?: ('Серия ' . $this->episode_number);
    }
}
