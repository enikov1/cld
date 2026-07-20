<?php

namespace App\Models;

use App\Services\EpisodeProgressService;
use App\Services\TaxonomyService;
use App\Support\AgeLimitFormatter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Series extends Model
{
    use SoftDeletes;

    protected $table = 'series';

    protected $fillable = [
        'kp_id',
        'imdb_id',
        'tmdb_id',
        'tmdb_popularity',
        'studio_id',
        'slug',
        'title',
        'meta_title',
        'meta_description',
        'title_en',
        'title_original',
        'description',
        'short_description',
        'slogan',
        'poster_url',
        'player_url',
        'premiere_date',
        'translation',
        'channel_name',
        'channel_url',
        'channel_logo_url',
        'year',
        'start_year',
        'end_year',
        'duration_minutes',
        'kp_rating',
        'imdb_rating',
        'kp_votes_count',
        'imdb_votes_count',
        'views_count',
        'popular_badge_active',
        'popular_badge_refreshed_at',
        'content_type',
        'broadcast_status',
        'season_number',
        'last_episode_number',
        'last_episode_changed_at',
        'age_limit',
        'kp_web_url',
        'alloha_token',
        'is_active',
        'is_hidden',
        'noindex',
        'is_pinned',
        'pinned_at',
        'sort_order',
        'is_coming_soon',
        'anticipation_yes_count',
        'anticipation_no_count',
    ];

    protected $casts = [
        'year' => 'integer',
        'start_year' => 'integer',
        'end_year' => 'integer',
        'duration_minutes' => 'integer',
        'season_number' => 'integer',
        'last_episode_number' => 'integer',
        'tmdb_popularity' => 'decimal:4',
        'tmdb_popularity_refreshed_at' => 'datetime',
        'imdb_rating' => 'decimal:1',
        'kp_votes_count' => 'integer',
        'imdb_votes_count' => 'integer',
        'views_count' => 'integer',
        'popular_badge_active' => 'boolean',
        'popular_badge_refreshed_at' => 'datetime',
        'last_episode_changed_at' => 'datetime',
        'is_active' => 'boolean',
        'is_hidden' => 'boolean',
        'noindex' => 'boolean',
        'is_pinned' => 'boolean',
        'pinned_at' => 'datetime',
        'sort_order' => 'integer',
        'is_coming_soon' => 'boolean',
        'anticipation_yes_count' => 'integer',
        'anticipation_no_count' => 'integer',
        'studio_id' => 'integer',
        'premiere_date' => 'date',
    ];

    protected static function booted(): void
    {
        static::saved(function (self $series) {
            if (!$series->wasRecentlyCreated && !$series->wasChanged(['year', 'start_year'])) {
                return;
            }

            app(TaxonomyService::class)->ensureSeriesYear($series);
        });
    }

    public function scopePublished($query)
    {
        return $query
            ->where('is_active', true)
            ->where('is_hidden', false);
    }

    public function scopeCatalogOrder($query)
    {
        return $query->orderByDesc('is_pinned')
            ->orderByDesc('pinned_at')
            ->orderBy('sort_order')
            ->orderByDesc('id');
    }

    public function broadcastStatusLabel(): ?string
    {
        if (!$this->broadcast_status) {
            return null;
        }

        return config('series.broadcast_statuses.' . $this->broadcast_status, $this->broadcast_status);
    }

    /**
     * Бейдж статуса для serial-status (Новинка, Идёт, …).
     *
     * @return array{class: string, label: string}|null
     */
    public function statusBadge(): ?array
    {
        if ($this->season_number === 1 && $this->broadcast_status === 'ongoing') {
            return ['class' => 'new', 'label' => 'Новинка'];
        }

        return match ($this->broadcast_status) {
            'ongoing' => ['class' => 'ongoing', 'label' => 'Идёт'],
            'paused' => ['class' => 'paused', 'label' => 'На паузе'],
            'completed' => ['class' => 'finished', 'label' => 'Завершён'],
            default => null,
        };
    }

    public function premiereDateLabel(): ?string
    {
        $year = $this->premiereDisplayYear();
        if ($year === null) {
            return null;
        }

        if ($this->premiereIsYearOnly()) {
            return (string)$year;
        }

        $dayMonth = $this->premiereDayMonthLabel();
        if ($dayMonth === null) {
            return (string)$year;
        }

        return $dayMonth . ' ' . $year;
    }

    public function premiereDisplayYear(): ?int
    {
        if ($this->premiere_date) {
            return (int)$this->premiere_date->format('Y');
        }

        $year = (int)($this->year ?: $this->start_year ?: 0);

        return $year > 0 ? $year : null;
    }

    public function premiereIsYearOnly(): bool
    {
        if (!$this->premiere_date) {
            return true;
        }

        return $this->premiere_date->format('m-d') === '01-01';
    }

    public function premiereDayMonthLabel(): ?string
    {
        if (!$this->premiere_date || $this->premiereIsYearOnly()) {
            return null;
        }

        $months = self::premiereMonthNames();
        $day = (int)$this->premiere_date->format('j');
        $month = (int)$this->premiere_date->format('n');

        return sprintf('%d %s', $day, $months[$month] ?? '');
    }

    public function ageLimitLabel(): ?string
    {
        return AgeLimitFormatter::label($this->age_limit);
    }

    public function ageLimitTooltip(): ?string
    {
        return AgeLimitFormatter::tooltip($this->age_limit);
    }

    /**
     * @return array<int, string>
     */
    private static function premiereMonthNames(): array
    {
        return [
            1 => 'января', 2 => 'февраля', 3 => 'марта', 4 => 'апреля',
            5 => 'мая', 6 => 'июня', 7 => 'июля', 8 => 'августа',
            9 => 'сентября', 10 => 'октября', 11 => 'ноября', 12 => 'декабря',
        ];
    }

    /**
     * Текст для шаблона: «5 сезон, 12 серия» или пустая строка.
     * Если в графике есть вышедшие серии — берём последнюю оттуда, иначе поля сериала.
     */
    public function episodeProgressLabel(): string
    {
        return EpisodeProgressService::resolvedProgress($this)['label'];
    }

    public function studio()
    {
        return $this->belongsTo(Studio::class, 'studio_id');
    }

    public function genres()
    {
        return $this->belongsToMany(Genre::class, 'series_genre', 'series_id', 'genre_id');
    }

    public function countries()
    {
        return $this->belongsToMany(Country::class, 'series_country', 'series_id', 'country_id');
    }

    public function actors()
    {
        return $this->belongsToMany(Person::class, 'series_person', 'series_id', 'person_id')
            ->wherePivot('role', 'actor');
    }

    public function directors()
    {
        return $this->belongsToMany(Person::class, 'series_person', 'series_id', 'person_id')
            ->wherePivot('role', 'director');
    }

    public function people()
    {
        return $this->belongsToMany(Person::class, 'series_person', 'series_id', 'person_id')
            ->withPivot('role');
    }

    public function collections()
    {
        return $this->belongsToMany(Collection::class, 'collection_items', 'series_id', 'collection_id')
            ->withPivot(['rank_order', 'is_auto'])
            ->orderBy('collection_items.rank_order');
    }

    public function studios()
    {
        return $this->belongsToMany(Studio::class, 'studio_items', 'series_id', 'studio_id')
            ->withPivot(['rank_order'])
            ->orderBy('studio_items.rank_order');
    }

    public function seasons()
    {
        return $this->hasMany(Season::class, 'series_id')->orderBy('season_number');
    }

    public function playerSources()
    {
        return $this->hasMany(PlayerSource::class, 'series_id')->orderByDesc('priority')->orderBy('id');
    }

    public function comments()
    {
        return $this->hasMany(Comment::class, 'series_id');
    }

    public function votes()
    {
        return $this->hasMany(UserVote::class, 'series_id');
    }

    public function guestVotes()
    {
        return $this->hasMany(GuestVote::class, 'series_id');
    }

    public function likesCount(): int
    {
        return (int)$this->votes()->where('value', 1)->count()
            + (int)$this->guestVotes()->where('value', 1)->count();
    }

    public function dislikesCount(): int
    {
        return (int)$this->votes()->where('value', -1)->count()
            + (int)$this->guestVotes()->where('value', -1)->count();
    }

    public function votesCount(): int
    {
        return $this->likesCount() + $this->dislikesCount();
    }

    public function userRating(): ?float
    {
        $total = $this->votesCount();
        if ($total === 0) {
            return null;
        }

        return round($this->likesCount() * 10.0 / $total, 1);
    }

    public function userRatingLabel(): ?string
    {
        $rating = $this->userRating();

        return $rating !== null ? number_format($rating, 1, '.', '') : null;
    }
}
