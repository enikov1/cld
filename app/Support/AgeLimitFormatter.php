<?php

namespace App\Support;

class AgeLimitFormatter
{
    public static function normalize(?string $value): ?string
    {
        $label = self::label($value);
        if ($label === null) {
            return null;
        }

        return rtrim($label, '+');
    }

    public static function label(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $raw = trim($value);
        if ($raw === '' || $raw === '0') {
            return null;
        }

        if (preg_match('/^age(\d{1,2})$/i', $raw, $matches)) {
            return $matches[1] . '+';
        }

        if (preg_match('/^(\d{1,2})\+?$/', $raw, $matches)) {
            return $matches[1] . '+';
        }

        return $raw;
    }

    public static function tooltip(?string $value): ?string
    {
        $label = self::label($value);
        if ($label === null) {
            return null;
        }

        if (preg_match('/^(\d{1,2})\+$/', $label, $matches)) {
            return 'зрителям, достигшим ' . $matches[1] . '+ лет';
        }

        return 'возрастное ограничение: ' . $label;
    }
}
