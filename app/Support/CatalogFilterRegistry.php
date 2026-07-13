<?php

namespace App\Support;

use App\Models\Country;
use App\Models\Genre;
use App\Models\Series;
use App\Models\Year;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class CatalogFilterRegistry
{
    /**
     * @return list<string>
     */
    public static function layout(): array
    {
        $layout = config('catalog_filters.layout', []);

        return is_array($layout) ? array_values($layout) : [];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function fields(): array
    {
        $fields = config('catalog_filters.fields', []);

        return is_array($fields) ? $fields : [];
    }

    /**
     * @return list<string>
     */
    public static function sortFieldKeys(): array
    {
        $keys = [];
        foreach (self::layout() as $key) {
            $definition = self::fields()[$key] ?? null;
            if ($definition && (string)($definition['role'] ?? '') === 'sort') {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /**
     * @return array<string, 'asc'|'desc'>
     */
    public static function parseSorts(Request $request): array
    {
        $sorts = [];

        foreach (self::sortFieldKeys() as $key) {
            $definition = self::fields()[$key] ?? [];
            $default = (string)($definition['default'] ?? 'desc');
            $raw = trim((string)$request->query($key, $default));
            $sorts[$key] = $raw === 'asc' ? 'asc' : 'desc';
        }

        return $sorts;
    }

    /**
     * @param array<string, 'asc'|'desc'> $sorts
     */
    public static function applyCatalogSorts(Builder $query, array $sorts): void
    {
        $query->orderByDesc('is_pinned')->orderByDesc('pinned_at');

        foreach (self::sortFieldKeys() as $key) {
            $direction = $sorts[$key] ?? 'desc';

            match ($key) {
                'popularity_sort' => self::applyNullableColumnSort($query, 'tmdb_popularity', $direction),
                'views_sort' => self::applyColumnSort($query, 'views_count', $direction),
                'user_rating_sort' => self::applyExpressionSort($query, self::userRatingPercentSql(), $direction),
                'comments_sort' => self::applyExpressionSort($query, self::approvedCommentsCountSql(), $direction),
                default => null,
            };
        }

        $query->orderByDesc('id');
    }

    public static function userRatingPercentSql(): string
    {
        return '(
            (
                (SELECT COUNT(*) FROM user_votes uv WHERE uv.series_id = series.id AND uv.value = 1)
                + (SELECT COUNT(*) FROM guest_votes gv WHERE gv.series_id = series.id AND gv.value = 1)
            ) * 100.0
            / GREATEST(
                (SELECT COUNT(*) FROM user_votes uv2 WHERE uv2.series_id = series.id)
                + (SELECT COUNT(*) FROM guest_votes gv2 WHERE gv2.series_id = series.id),
                1
            )
        )';
    }

    public static function approvedCommentsCountSql(): string
    {
        return '(SELECT COUNT(*) FROM comments WHERE comments.series_id = series.id AND comments.status = \'approved\')';
    }

    private static function applyColumnSort(Builder $query, string $column, string $direction): void
    {
        if ($direction === 'asc') {
            $query->orderBy($column);

            return;
        }

        $query->orderByDesc($column);
    }

    private static function applyNullableColumnSort(Builder $query, string $column, string $direction): void
    {
        $query->orderByRaw($column . ' IS NULL');

        if ($direction === 'asc') {
            $query->orderBy($column);

            return;
        }

        $query->orderByDesc($column);
    }

    private static function applyExpressionSort(Builder $query, string $expression, string $direction): void
    {
        if ($direction === 'asc') {
            $query->orderByRaw('(' . $expression . ') ASC');

            return;
        }

        $query->orderByRaw('(' . $expression . ') DESC');
    }

    private static function isSortField(?array $definition): bool
    {
        return $definition !== null && (string)($definition['role'] ?? '') === 'sort';
    }

    /**
     * @return array<string, string>
     */
    public static function parse(Request $request): array
    {
        $out = [];

        foreach (self::layout() as $key) {
            $definition = self::fields()[$key] ?? null;
            if (!$definition) {
                continue;
            }

            $raw = trim((string)$request->query($key, ''));
            if ($raw === '') {
                continue;
            }

            $normalized = self::normalizeValue($key, $definition, $raw);
            if ($normalized !== null && $normalized !== '') {
                $out[$key] = $normalized;
            }
        }

        return $out;
    }

    /**
     * @param array<string, string> $filters
     */
    public static function applyToQuery(Builder $query, array $filters): Builder
    {
        foreach ($filters as $key => $value) {
            $definition = self::fields()[$key] ?? null;
            if ($definition && (string)($definition['role'] ?? '') === 'sort') {
                continue;
            }

            self::applyFilter($query, $key, $value);
        }

        return $query;
    }

    /**
     * @param array<string, string> $filters
     * @param callable(string, array<string, mixed>): string $renderPartial
     * @return array<string, mixed>
     */
    public static function buildViewData(array $filters, callable $renderPartial): array
    {
        $filterFields = [];
        $filtersByKey = [];

        foreach (self::layout() as $key) {
            $definition = self::fields()[$key] ?? null;
            if (!$definition) {
                continue;
            }

            $field = self::buildField($key, $definition, $filters);
            if ($field === null) {
                continue;
            }

            $partial = config('catalog_filters.partials.' . $field['type'], '');
            if (!is_string($partial) || $partial === '') {
                continue;
            }

            $field['html'] = $renderPartial($partial, ['filter' => $field]);
            $filterFields[] = $field;
            $filtersByKey[$key] = $field;
        }

        return [
            'has_active' => self::hasActiveFilters($filters),
            'filter_fields' => $filterFields,
            'filters' => $filtersByKey,
        ];
    }

    /**
     * @param array<string, string> $filters
     */
    private static function hasActiveFilters(array $filters): bool
    {
        foreach ($filters as $key => $value) {
            $definition = self::fields()[$key] ?? null;
            if (self::isSortField($definition)) {
                if ($value === 'asc') {
                    return true;
                }

                continue;
            }

            return true;
        }

        return false;
    }

    /**
     * @param array<string, mixed> $definition
     */
    private static function normalizeValue(string $key, array $definition, string $raw): ?string
    {
        $type = (string)($definition['type'] ?? 'select');

        if ($type === 'select') {
            if (!preg_match('/^[a-zA-Z0-9_\-.]+$/', $raw)) {
                return null;
            }

            if ($key === 'year_from' || $key === 'year_to') {
                $year = (int)$raw;

                return ($year >= 1900 && $year <= 2100) ? (string)$year : null;
            }

            if (self::isSortField($definition)) {
                return in_array($raw, ['asc', 'desc'], true) ? $raw : null;
            }

            return $raw;
        }

        if ($type === 'range' || $type === 'number') {
            if (!is_numeric($raw)) {
                return null;
            }

            $num = (float)$raw;
            $min = (float)($definition['min'] ?? 0);
            $max = (float)($definition['max'] ?? PHP_FLOAT_MAX);

            if ($num <= $min) {
                return null;
            }

            if ($num > $max) {
                $num = $max;
            }

            $step = (float)($definition['step'] ?? 1);
            if ($step >= 1 && $key !== 'rating_min') {
                return (string)(int)$num;
            }

            return rtrim(rtrim(number_format($num, 1, '.', ''), '0'), '.');
        }

        return null;
    }

    /**
     * @param array<string, mixed> $definition
     * @param array<string, string> $filters
     * @return array<string, mixed>|null
     */
    private static function buildField(string $key, array $definition, array $filters): ?array
    {
        $type = (string)($definition['type'] ?? 'select');
        $value = $filters[$key] ?? '';
        if ($value === '' && isset($definition['default'])) {
            $value = (string)$definition['default'];
        }

        $field = [
            'key' => $key,
            'type' => $type,
            'label' => (string)($definition['label'] ?? $key),
            'value' => $value,
            'is_active' => self::isSortField($definition) ? $value === 'asc' : $value !== '',
        ];

        if ($type === 'select') {
            $options = self::buildSelectOptions($key, $definition, $value);
            if ($options === []) {
                return null;
            }

            $field['empty_label'] = (string)($definition['empty'] ?? 'Все');
            $field['hide_empty'] = (bool)($definition['hide_empty'] ?? false);
            $field['options'] = $options;

            return $field;
        }

        if ($type === 'range' || $type === 'number') {
            $min = (float)($definition['min'] ?? 0);
            $max = (float)($definition['max'] ?? 100);
            $step = (float)($definition['step'] ?? 1);
            $current = $value !== '' ? (float)$value : $min;
            $suffix = (string)($definition['suffix'] ?? '');

            $field['min'] = $min;
            $field['max'] = $max;
            $field['step'] = $step;
            $field['suffix'] = $suffix;
            $field['display_value'] = self::formatRangeDisplay($current, $suffix, $min);

            return $field;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $definition
     * @return list<array{value: string, label: string, selected: bool}>
     */
    private static function buildSelectOptions(string $key, array $definition, string $value): array
    {
        $source = (string)($definition['source'] ?? '');

        if ($source === 'years') {
            return self::yearOptions($value);
        }

        if ($source === 'taxonomy:genre') {
            return self::taxonomyOptions(
                Genre::query()->where('is_active', true)->where('is_hidden', false)->orderBy('name')->get(),
                $value
            );
        }

        if ($source === 'taxonomy:country') {
            return self::taxonomyOptions(
                Country::query()->where('is_active', true)->where('is_hidden', false)->orderBy('name')->get(),
                $value
            );
        }

        if ($source === 'taxonomy:year') {
            return self::taxonomyOptions(
                Year::query()->where('is_active', true)->where('is_hidden', false)->orderByDesc('sort_order')->get(),
                $value
            );
        }

        if ($source === 'static' && isset($definition['options']) && is_array($definition['options'])) {
            $options = [];
            foreach ($definition['options'] as $option) {
                if (!is_array($option)) {
                    continue;
                }
                $optionValue = (string)($option['value'] ?? '');
                if ($optionValue === '') {
                    continue;
                }
                $options[] = [
                    'value' => $optionValue,
                    'label' => (string)($option['label'] ?? $optionValue),
                    'selected' => $value === $optionValue,
                ];
            }

            return $options;
        }

        return [];
    }

    /**
     * @param iterable<int, object{slug: string, name: string}> $items
     * @return list<array{value: string, label: string, selected: bool}>
     */
    private static function taxonomyOptions(iterable $items, string $selected): array
    {
        $options = [];
        foreach ($items as $item) {
            $options[] = [
                'value' => $item->slug,
                'label' => $item->name,
                'selected' => $selected === $item->slug,
            ];
        }

        return $options;
    }

    /**
     * @return list<array{value: string, label: string, selected: bool}>
     */
    private static function yearOptions(string $selected): array
    {
        $years = Series::query()
            ->published()
            ->get(['year', 'start_year'])
            ->map(fn (Series $series) => (int)($series->year ?: $series->start_year ?: 0))
            ->filter(fn (int $year) => $year > 0)
            ->unique()
            ->sortDesc()
            ->values()
            ->map(fn (int $year) => (string)$year)
            ->all();

        if ($years === []) {
            $current = (int)date('Y');
            for ($y = $current; $y >= $current - 30; $y--) {
                $years[] = (string)$y;
            }
        }

        $options = [];
        foreach ($years as $year) {
            $options[] = [
                'value' => $year,
                'label' => $year,
                'selected' => $selected === $year,
            ];
        }

        return $options;
    }

    private static function formatRangeDisplay(float $value, string $suffix, float $min): string
    {
        if ($value <= $min) {
            return 'Любой';
        }

        $formatted = fmod($value, 1.0) === 0.0 ? (string)(int)$value : number_format($value, 1, '.', '');

        return $suffix !== '' ? $formatted . $suffix : $formatted;
    }

    private static function applyFilter(Builder $query, string $key, string $value): void
    {
        match ($key) {
            'genre' => $query->whereHas(
                'genres',
                fn (Builder $q) => $q->where('slug', $value)->where('is_active', true)
            ),
            'country' => $query->whereHas(
                'countries',
                fn (Builder $q) => $q->where('slug', $value)->where('is_active', true)
            ),
            'rating_min' => $query->where(function (Builder $q) use ($value) {
                $min = (float)$value;
                $q->where('kp_rating', '>=', $min)
                    ->orWhere('imdb_rating', '>=', $min);
            }),
            'year_from' => $query->whereRaw('COALESCE(NULLIF(year, 0), start_year) >= ?', [(int)$value]),
            'year_to' => $query->whereRaw('COALESCE(NULLIF(year, 0), start_year) <= ?', [(int)$value]),
            default => null,
        };
    }
}
