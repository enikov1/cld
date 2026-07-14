<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NavItem extends Model
{
    public const LINK_HOME = 'home';
    public const LINK_CATEGORY = 'category';
    public const LINK_TAXONOMY = 'taxonomy';
    public const LINK_COLLECTIONS = 'collections';
    public const LINK_STUDIOS = 'studios';
    public const LINK_CATALOG = 'catalog';
    public const LINK_COMING_SOON = 'coming_soon';
    public const LINK_CUSTOM = 'custom';

    protected $fillable = [
        'title',
        'link_type',
        'taxonomy_type',
        'taxonomy_id',
        'custom_url',
        'sort_order',
        'is_active',
        'show_desktop',
        'show_mobile',
        'has_mega',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
        'show_desktop' => 'boolean',
        'show_mobile' => 'boolean',
        'has_mega' => 'boolean',
    ];

    public function megaButtons(): HasMany
    {
        return $this->hasMany(NavMegaButton::class)->orderBy('sort_order')->orderBy('id');
    }

    public function megaSections(): HasMany
    {
        return $this->hasMany(NavMegaSection::class)->orderBy('sort_order')->orderBy('id');
    }
}
