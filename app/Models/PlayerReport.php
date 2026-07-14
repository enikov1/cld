<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerReport extends Model
{
    protected $fillable = [
        'series_id',
        'user_id',
        'reason',
        'reason_label',
        'message',
        'player_label',
        'ip',
        'user_agent',
    ];

    public function series(): BelongsTo
    {
        return $this->belongsTo(Series::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
