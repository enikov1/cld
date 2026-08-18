<?php

namespace App\Support;

use App\Models\Country;
use App\Models\Genre;
use App\Models\Person;
use App\Models\Voice;
use App\Models\Year;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TaxonomyRegistry
{
    public const TYPE_GENRES = 'genres';
    public const TYPE_COUNTRIES = 'countries';
    public const TYPE_PEOPLE = 'people';
    public const TYPE_YEARS = 'years';
    public const TYPE_VOICES = 'voices';

    /**
     * @var array<string, array{model: class-string<Model>, url_prefix: string, label: string}>
     */
    public const TYPES = [
        self::TYPE_GENRES => [
            'model' => Genre::class,
            'url_prefix' => 'genre',
            'label' => 'Жанры',
        ],
        self::TYPE_COUNTRIES => [
            'model' => Country::class,
            'url_prefix' => 'country',
            'label' => 'Страны',
        ],
        self::TYPE_PEOPLE => [
            'model' => Person::class,
            'url_prefix' => 'person',
            'label' => 'Актёры',
        ],
        self::TYPE_YEARS => [
            'model' => Year::class,
            'url_prefix' => 'year',
            'label' => 'Годы',
        ],
        self::TYPE_VOICES => [
            'model' => Voice::class,
            'url_prefix' => 'voice',
            'label' => 'Озвучки',
        ],
    ];

    /**
     * @return class-string<Model>
     */
    public static function modelClass(string $type): string
    {
        if (!isset(self::TYPES[$type])) {
            abort(404);
        }

        return self::TYPES[$type]['model'];
    }

    public static function publicUrl(string $type, string $slug): string
    {
        $prefix = self::TYPES[$type]['url_prefix'] ?? '';

        return $prefix !== ''
            ? '/' . $prefix . '/' . rawurlencode($slug) . '/'
            : '/';
    }

    public static function isValidType(string $type): bool
    {
        return isset(self::TYPES[$type]);
    }

    /**
     * @return list<string>
     */
    public static function typeKeys(): array
    {
        return array_keys(self::TYPES);
    }

    public static function findPublicItem(string $type, int $id): Model
    {
        $model = self::modelClass($type);

        return $model::query()
            ->whereKey($id)
            ->where('is_active', true)
            ->where('is_hidden', false)
            ->firstOrFail();
    }

    public static function applySeriesScope(Builder $query, string $type, Model $item): Builder
    {
        return match ($type) {
            self::TYPE_GENRES => $query->whereHas('genres', fn (Builder $q) => $q->where('genres.id', $item->id)),
            self::TYPE_COUNTRIES => $query->whereHas('countries', fn (Builder $q) => $q->where('countries.id', $item->id)),
            self::TYPE_PEOPLE => $query->whereHas('actors', fn (Builder $q) => $q->where('people.id', $item->id)),
            self::TYPE_VOICES => $query->whereHas('voices', fn (Builder $q) => $q->where('voices.id', $item->id)),
            self::TYPE_YEARS => $query->where(function (Builder $q) use ($item) {
                $year = (int)$item->slug;
                $q->where('year', $year)
                    ->orWhere('start_year', $year)
                    ->orWhere('end_year', $year);
            }),
            default => $query,
        };
    }

    public static function displayName(Model $item): string
    {
        return Utf8::ucfirst((string)($item->name ?? $item->title ?? ''));
    }

    public static function homeTitle(Model $item): string
    {
        $title = trim((string)($item->home_title ?? ''));

        return $title !== '' ? $title : self::displayName($item);
    }
}
