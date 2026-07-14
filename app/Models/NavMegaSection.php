<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NavMegaSection extends Model
{
    public const SOURCE_GENRES = 'genres';
    public const SOURCE_COUNTRIES = 'countries';
    public const SOURCE_COLLECTIONS = 'collections';
    public const SOURCE_STUDIOS = 'studios';
    public const SOURCE_YEARS = 'years';
    public const SOURCE_CUSTOM = 'custom';

    protected $fillable = [
        'nav_item_id',
        'title',
        'source_type',
        'item_limit',
        'css_class',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'item_limit' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function navItem(): BelongsTo
    {
        return $this->belongsTo(NavItem::class);
    }

    public function links(): HasMany
    {
        return $this->hasMany(NavMegaLink::class)->orderBy('sort_order')->orderBy('id');
    }
}
