<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SearchQueryLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'query',
        'query_normalized',
        'source',
        'found',
        'results_count',
        'ip',
        'created_at',
    ];

    protected $casts = [
        'found' => 'boolean',
        'results_count' => 'integer',
        'created_at' => 'datetime',
    ];
}
