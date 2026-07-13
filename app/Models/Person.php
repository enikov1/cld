<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Person extends Model
{
    protected $table = 'people';

    protected $attributes = [
        'noindex' => true,
    ];

    protected $fillable = [
        'slug',
        'name',
        'photo_url',
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
        return $this->belongsToMany(Series::class, 'series_person', 'person_id', 'series_id')
            ->withPivot('role');
    }

    public function actorSeries()
    {
        return $this->belongsToMany(Series::class, 'series_person', 'person_id', 'series_id')
            ->wherePivot('role', 'actor');
    }
}
