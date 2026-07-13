<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NavMegaLink extends Model
{
    protected $fillable = [
        'nav_mega_section_id',
        'label',
        'url',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function section(): BelongsTo
    {
        return $this->belongsTo(NavMegaSection::class, 'nav_mega_section_id');
    }
}
