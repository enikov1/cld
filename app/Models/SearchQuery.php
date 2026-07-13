<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SearchQuery extends Model
{
    protected $fillable = [
        'query_normalized',
        'query',
        'hits',
        'suggest_hits',
        'full_hits',
        'last_searched_at',
    ];

    protected $casts = [
        'hits' => 'integer',
        'suggest_hits' => 'integer',
        'full_hits' => 'integer',
        'last_searched_at' => 'datetime',
    ];
}
