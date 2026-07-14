<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CronRun extends Model
{
    public const STATUS_RUNNING = 'running';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';

    public const TRIGGER_SCHEDULE = 'schedule';
    public const TRIGGER_ADMIN = 'admin';
    public const TRIGGER_CLI = 'cli';

    protected $fillable = [
        'job_key',
        'command',
        'trigger',
        'status',
        'started_at',
        'finished_at',
        'duration_ms',
        'counts',
        'message',
        'error',
        'log',
        'meta',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'duration_ms' => 'integer',
        'counts' => 'array',
        'meta' => 'array',
    ];
}
