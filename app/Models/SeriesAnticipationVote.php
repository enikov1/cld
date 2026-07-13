<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeriesAnticipationVote extends Model
{
    protected $fillable = [
        'series_id',
        'user_id',
        'voter_key',
        'value',
    ];

    protected $casts = [
        'value' => 'integer',
    ];

    public function series(): BelongsTo
    {
        return $this->belongsTo(Series::class, 'series_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
