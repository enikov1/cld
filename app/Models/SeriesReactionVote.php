<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeriesReactionVote extends Model
{
    protected $fillable = [
        'series_id',
        'reaction_type_id',
        'user_id',
        'voter_key',
    ];

    public function series(): BelongsTo
    {
        return $this->belongsTo(Series::class);
    }

    public function reactionType(): BelongsTo
    {
        return $this->belongsTo(ReactionType::class);
    }
}
