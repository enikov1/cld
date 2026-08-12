<?php

namespace App\Models;

use App\Support\SiteConfig;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    protected $fillable = [
        'user_id',
        'series_id',
        'rating',
        'body',
        'status',
        'author_name',
        'is_editorial',
    ];

    protected $casts = [
        'rating' => 'integer',
        'is_editorial' => 'boolean',
    ];

    protected $appends = [
        'author_display',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function series(): BelongsTo
    {
        return $this->belongsTo(Series::class, 'series_id');
    }

    public function displayName(): string
    {
        if ($this->author_name) {
            return $this->author_name;
        }

        if ($this->user_id) {
            return $this->user?->name ?? SiteConfig::str('reviews_label_user');
        }

        return SiteConfig::str('reviews_label_editorial');
    }

    public function getAuthorDisplayAttribute(): string
    {
        return $this->displayName();
    }
}
