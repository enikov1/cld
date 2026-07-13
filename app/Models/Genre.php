<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Genre extends Model
{
    protected $attributes = [
        'noindex' => true,
    ];

    protected $fillable = [
        'slug',
        'name',
        'meta_title',
        'meta_description',
        'seo_html',
        'sort_order',
        'is_active',
        'is_hidden',
        'noindex',
        'show_on_home',
        'home_title',
        'home_item_limit',
        'home_show_tabs',
        'home_default_sort',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
        'is_hidden' => 'boolean',
        'noindex' => 'boolean',
        'show_on_home' => 'boolean',
        'home_item_limit' => 'integer',
        'home_show_tabs' => 'boolean',
    ];

    public function series()
    {
        return $this->belongsToMany(Series::class, 'series_genre', 'genre_id', 'series_id');
    }
}
