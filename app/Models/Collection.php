<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Collection extends Model
{
    protected $table = 'collections';

    protected $fillable = [
        'slug',
        'title',
        'studio_id',
        'meta_title',
        'description',
        'meta_description',
        'seo_html',
        'cover_url',
        'sort_order',
        'is_pinned',
        'source_updated_at',
        'is_active',
        'is_hidden',
        'noindex',
    ];

    protected $casts = [
        'source_updated_at' => 'datetime',
        'is_active' => 'boolean',
        'is_hidden' => 'boolean',
        'noindex' => 'boolean',
        'is_pinned' => 'boolean',
        'sort_order' => 'integer',
        'studio_id' => 'integer',
    ];

    public function scopeCatalogOrder($query)
    {
        return $query->orderByDesc('is_pinned')->orderBy('sort_order')->orderByDesc('id');
    }

    public function items()
    {
        return $this->hasMany(CollectionItem::class, 'collection_id');
    }

    public function series()
    {
        return $this->belongsToMany(Series::class, 'collection_items', 'collection_id', 'series_id')
            ->withPivot(['rank_order'])
            ->orderBy('collection_items.rank_order');
    }

    public function studio()
    {
        return $this->belongsTo(Studio::class, 'studio_id');
    }
}
