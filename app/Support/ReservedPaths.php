<?php

namespace App\Support;

class ReservedPaths
{
    /**
     * @return list<string>
     */
    public static function slugs(): array
    {
        $paths = config('reserved_paths.paths', []);

        return array_values(array_unique(array_merge(
            is_array($paths) ? $paths : [],
            [AdminPath::path(), AdminPath::DEFAULT, 'admin'],
        )));
    }

    public static function legacyCategoryConstraint(): string
    {
        $reserved = implode('|', array_map('preg_quote', self::slugs()));

        return '(?!' . $reserved . '$)[a-z0-9][a-z0-9\-]*';
    }

    public static function seriesPathConstraint(): string
    {
        return '[0-9]+-[a-z0-9\-]+-[0-9]+';
    }
}
