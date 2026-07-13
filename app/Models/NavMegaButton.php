<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NavMegaButton extends Model
{
    protected $fillable = [
        'nav_item_id',
        'title',
        'subtitle',
        'link_type',
        'taxonomy_type',
        'taxonomy_id',
        'custom_url',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function navItem(): BelongsTo
    {
        return $this->belongsTo(NavItem::class);
    }
}
