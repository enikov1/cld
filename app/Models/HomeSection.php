<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HomeSection extends Model
{
    public const SORT_LATEST = 'latest';
    public const SORT_POPULAR = 'popular';
    public const SORT_RATING = 'rating';

    protected $fillable = [
        'category_id',
        'title',
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
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function displayTitle(): string
    {
        if ($this->title !== '') {
            return $this->title;
        }

        return $this->category?->title ?? '';
    }

    public function categorySlug(): string
    {
        return $this->category?->slug ?? '';
    }
}
