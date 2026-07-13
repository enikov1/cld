<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReactionType extends Model
{
    protected $fillable = [
        'emoji',
        'label',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function votes(): HasMany
    {
        return $this->hasMany(SeriesReactionVote::class);
    }
}
