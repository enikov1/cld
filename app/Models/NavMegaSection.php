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
    public const SOURCE_VOICES = 'voices';
    public const SOURCE_CUSTOM = 'custom';

    public const SORT_NAME = 'name';
    public const SORT_SERIES_COUNT = 'series_count';
    public const SORT_ORDER = 'sort_order';

    protected $fillable = [
        'nav_item_id',
        'title',
        'source_type',
        'item_limit',
        'item_sort',
        'css_class',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'item_limit' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function itemSort(): string
    {
        $sort = trim((string) ($this->item_sort ?? ''));
        if (in_array($sort, [self::SORT_NAME, self::SORT_SERIES_COUNT, self::SORT_ORDER], true)) {
            return $sort;
        }

        return $this->source_type === self::SOURCE_VOICES
            ? self::SORT_SERIES_COUNT
            : self::SORT_NAME;
    }

    public function navItem(): BelongsTo
    {
        return $this->belongsTo(NavItem::class);
    }

    public function links(): HasMany
    {
        return $this->hasMany(NavMegaLink::class)->orderBy('sort_order')->orderBy('id');
    }
}
