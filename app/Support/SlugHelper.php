<?php

namespace App\Support;

use Illuminate\Support\Str;

class SlugHelper
{
    /**
     * Slug из ручного значения или из названия, если поле пустое.
     */
    public static function make(?string $value, string $source): string
    {
        $value = trim((string)$value);
        if ($value !== '') {
            return Str::slug($value);
        }

        return Str::slug($source);
    }

    /**
     * Уникальный slug: base, base-2, base-3...
     */
    public static function makeUnique(?string $value, string $source, callable $isTaken): string
    {
        $base = self::make($value, $source);
        if ($base === '') {
            $base = 'item';
        }

        $slug = $base;
        $n = 2;
        while ($isTaken($slug)) {
            $slug = $base . '-' . $n;
            $n++;
        }

        return $slug;
    }
}
