<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HomeSection extends Model
{
    public const SORT_LATEST = 'latest';
    public const SORT_POPULAR = 'popular';
    public const SORT_RATING = 'rating';

    protected $fillable = [
        'title',
        'filters',
        'link_url',
        'sort_order',
        'is_active',
        'item_limit',
        'show_tabs',
        'default_sort',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
        'item_limit' => 'integer',
        'show_tabs' => 'boolean',
        'filters' => 'array',
    ];
}
