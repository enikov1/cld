<?php

namespace App\Support;

final class Utf8
{
    public static function sanitize(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        foreach (['Windows-1251', 'CP866', 'ISO-8859-1'] as $encoding) {
            $converted = @iconv($encoding, 'UTF-8//IGNORE', $value);
            if ($converted !== false && $converted !== '' && mb_check_encoding($converted, 'UTF-8')) {
                return $converted;
            }
        }

        $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $value);

        return $clean !== false ? $clean : '';
    }

    /**
     * @param list<string>|null $lines
     * @return list<string>|null
     */
    public static function sanitizeLines(?array $lines): ?array
    {
        if ($lines === null) {
            return null;
        }

        return array_map(
            static fn (string $line): string => (string)self::sanitize($line),
            $lines,
        );
    }

    /**
     * Capitalize the first character without changing the rest (Cyrillic-safe).
     */
    public static function ucfirst(?string $value): string
    {
        $value = trim((string)$value);
        if ($value === '') {
            return '';
        }

        return mb_strtoupper(mb_substr($value, 0, 1)) . mb_substr($value, 1);
    }
}
