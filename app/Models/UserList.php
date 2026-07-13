<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserList extends Model
{
    public const TYPES = [
        'watching',
        'will-watch',
        'seen',
        'favourite',
        'abandoned',
    ];

    protected $table = 'user_lists';

    protected $fillable = [
        'user_id',
        'series_id',
        'type',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function series(): BelongsTo
    {
        return $this->belongsTo(Series::class, 'series_id');
    }
}
