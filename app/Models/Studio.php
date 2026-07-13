<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Studio extends Model
{
    protected $table = 'studios';

    protected $fillable = [
        'slug',
        'title',
        'meta_title',
        'description',
        'meta_description',
        'seo_html',
        'logo_url',
        'sort_order',
        'is_pinned',
        'is_active',
        'is_hidden',
        'noindex',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_hidden' => 'boolean',
        'noindex' => 'boolean',
        'is_pinned' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeCatalogOrder($query)
    {
        return $query->orderByDesc('is_pinned')->orderBy('sort_order')->orderByDesc('id');
    }

    public function items()
    {
        return $this->hasMany(StudioItem::class, 'studio_id');
    }

    public function series()
    {
        return $this->belongsToMany(Series::class, 'studio_items', 'studio_id', 'series_id')
            ->withPivot(['rank_order'])
            ->orderBy('studio_items.rank_order');
    }

    public function collections()
    {
        return $this->hasMany(Collection::class, 'studio_id');
    }
}
