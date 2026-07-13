<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationDelivery extends Model
{
    protected $fillable = [
        'notification_event_id',
        'user_id',
        'status',
        'sent_at',
        'read_at',
        'dismissed_at',
        'error',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'read_at' => 'datetime',
        'dismissed_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(NotificationEvent::class, 'notification_event_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
