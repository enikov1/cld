<?php

namespace App\Models;

use App\Support\RedirectPath;
use App\Support\SeriesUrl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteRedirect extends Model
{
    public const TYPE_URL = 'url';

    public const TYPE_SERIES = 'series';

    protected $table = 'redirects';

    protected $fillable = [
        'from_path',
        'to_type',
        'to_path',
        'series_id',
        'status_code',
        'is_active',
        'note',
        'hits_count',
    ];

    protected $casts = [
        'series_id' => 'integer',
        'status_code' => 'integer',
        'is_active' => 'boolean',
        'hits_count' => 'integer',
    ];

    public function series(): BelongsTo
    {
        return $this->belongsTo(Series::class);
    }

    public function resolveTargetPath(): ?string
    {
        if ($this->to_type === self::TYPE_SERIES && $this->series_id) {
            $series = $this->relationLoaded('series')
                ? $this->series
                : Series::query()->find($this->series_id);

            if (!$series) {
                return null;
            }

            return SeriesUrl::path($series);
        }

        $path = trim((string) $this->to_path);

        return $path !== '' ? $path : null;
    }

    public function normalizeFromPath(): void
    {
        $this->from_path = RedirectPath::normalizeFrom((string) $this->from_path);
    }

    public function normalizeToPath(): void
    {
        if ($this->to_type === self::TYPE_SERIES) {
            $this->to_path = $this->resolveTargetPath();

            return;
        }

        $this->to_path = RedirectPath::normalizeTo((string) $this->to_path);
    }
}
