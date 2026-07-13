<?php

namespace App\Support;

use App\Models\Series;
use Illuminate\Support\Str;

class SeriesUrl
{
    public static function path(Series $series): string
    {
        return '/' . self::segment($series) . '.html';
    }

    public static function segment(Series $series): string
    {
        return (int)$series->id . '-' . self::titleSlug($series) . '-' . self::displayYear($series);
    }

    public static function titleSlug(Series $series): string
    {
        $slug = trim((string)$series->slug);
        if ($slug !== '') {
            return $slug;
        }

        $generated = Str::slug((string)$series->title);

        return $generated !== '' ? $generated : 'series';
    }

    public static function displayYear(Series $series): string
    {
        $year = (int)($series->year ?: $series->start_year ?: 0);
        if ($year >= 1900 && $year <= 2100) {
            return (string)$year;
        }

        if ($series->premiere_date) {
            $premiereYear = (int)$series->premiere_date->format('Y');
            if ($premiereYear >= 1900 && $premiereYear <= 2100) {
                return (string)$premiereYear;
            }
        }

        return '0000';
    }

    public static function parseId(string $seriesPath): ?int
    {
        if (preg_match('/^(\d+)-/', $seriesPath, $matches)) {
            return (int)$matches[1];
        }

        return null;
    }

    public static function isCanonicalPath(Series $series, string $seriesPath): bool
    {
        return self::segment($series) === trim($seriesPath, '/');
    }

    public static function route(Series $series): string
    {
        return route('series.show', ['seriesPath' => self::segment($series)]);
    }
}
