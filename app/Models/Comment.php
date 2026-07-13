<?php

namespace App\Models;

use App\Support\SiteConfig;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Comment extends Model
{
    protected $fillable = [
        'user_id',
        'series_id',
        'parent_id',
        'body',
        'status',
        'guest_name',
        'is_anonymous',
        'is_pinned',
        'pinned_at',
    ];

    protected $casts = [
        'is_anonymous' => 'boolean',
        'is_pinned' => 'boolean',
        'pinned_at' => 'datetime',
    ];

    protected $appends = [
        'author_name',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function series(): BelongsTo
    {
        return $this->belongsTo(Series::class, 'series_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(CommentVote::class);
    }

    public function likesCount(): int
    {
        return (int)$this->votes()->where('value', 1)->count();
    }

    public function dislikesCount(): int
    {
        return (int)$this->votes()->where('value', -1)->count();
    }

    public function displayName(): string
    {
        if ($this->is_anonymous) {
            return SiteConfig::str('comments_label_anonymous');
        }

        if ($this->user_id) {
            return $this->user?->name ?? SiteConfig::str('comments_label_user');
        }

        return $this->guest_name ?: SiteConfig::str('comments_label_guest');
    }

    public function getAuthorNameAttribute(): string
    {
        return $this->displayName();
    }
}
