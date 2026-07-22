<?php

namespace App\Support;

/**
 * Типы контента и tpl-теги [type-N] / {series.content_type_label}.
 *
 * type-1 — фильм, type-2 — сериал, type-3 — мультфильм, type-4 — мультсериал,
 * type-5 — аниме, type-6 — дорама, type-7 — тв-шоу.
 */
class ContentTypes
{
    /** @var list<string> */
    private const FILM_LIKE = ['film', 'cartoon'];

    /** @var list<string> */
    private const SERIAL_LIKE = ['series', 'cartoon_series', 'anime', 'dorama', 'tv_show'];

    /**
     * @return array<string, string> slug => label
     */
    public static function all(): array
    {
        return config('series.content_types', []);
    }

    /**
     * @return list<string>
     */
    public static function slugs(): array
    {
        return array_keys(self::all());
    }

    public static function label(?string $slug): string
    {
        if ($slug === null || $slug === '') {
            return '';
        }

        return (string)(self::all()[$slug] ?? $slug);
    }

    public static function isValid(?string $slug): bool
    {
        return $slug !== null && $slug !== '' && array_key_exists($slug, self::all());
    }

    public static function index(?string $slug): ?int
    {
        if (!self::isValid($slug)) {
            return null;
        }

        $position = array_search($slug, self::slugs(), true);

        return $position === false ? null : $position + 1;
    }

    public static function slugByIndex(int $index): ?string
    {
        if ($index < 1) {
            return null;
        }

        return self::slugs()[$index - 1] ?? null;
    }

    public static function isFilmLike(?string $slug): bool
    {
        return in_array($slug, self::FILM_LIKE, true);
    }

    public static function isSerialLike(?string $slug): bool
    {
        return in_array($slug, self::SERIAL_LIKE, true);
    }

    /**
     * @return array<string, string> truthy flags for tpl blocks [type-N]
     */
    public static function forTpl(?string $contentType): array
    {
        $out = [];
        $index = 0;

        foreach (self::slugs() as $slug) {
            $index++;
            $active = $contentType === $slug;
            $out['type-' . $index] = $active ? '1' : '';
        }

        return $out;
    }

    public static function validationInRule(): string
    {
        return 'in:' . implode(',', self::slugs());
    }
}
