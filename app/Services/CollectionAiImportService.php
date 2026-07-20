<?php

namespace App\Services;

use App\Models\Collection;
use App\Models\Series;
use App\Support\SlugHelper;
use App\Support\TplCache;

class CollectionAiImportService
{
    public function __construct(
        private readonly CollectionAutoMatcher $matcher,
    ) {
    }

    /**
     * @return array{
     *     ok: bool,
     *     dry_run: bool,
     *     items: list<array<string, mixed>>,
     *     skipped: list<array<string, mixed>>,
     *     errors: list<string>,
     *     created: int
     * }
     */
    public function import(string $payload, bool $dryRun = true): array
    {
        $parsed = $this->parsePayload($payload);
        if ($parsed === null) {
            return [
                'ok' => false,
                'dry_run' => $dryRun,
                'items' => [],
                'skipped' => [],
                'errors' => ['Не удалось распознать JSON. Вставьте ответ ИИ целиком или только JSON-блок.'],
                'created' => 0,
            ];
        }

        $collections = $parsed['collections'] ?? null;
        if (!is_array($collections) || $collections === []) {
            return [
                'ok' => false,
                'dry_run' => $dryRun,
                'items' => [],
                'skipped' => [],
                'errors' => ['В JSON нет массива collections или он пуст.'],
                'created' => 0,
            ];
        }

        $existingSlugs = Collection::query()->pluck('slug')->map(fn ($s) => (string) $s)->all();
        $existingSlugSet = array_fill_keys($existingSlugs, true);
        $validSeriesIds = Series::query()->published()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $validSeriesSet = array_fill_keys($validSeriesIds, true);

        $items = [];
        $skipped = [];
        $errors = [];
        $created = 0;
        $plannedSlugs = [];

        foreach ($collections as $index => $row) {
            if (!is_array($row)) {
                $errors[] = 'Элемент #' . ($index + 1) . ': некорректный формат.';

                continue;
            }

            $title = trim((string) ($row['title'] ?? ''));
            if ($title === '') {
                $skipped[] = [
                    'index' => $index,
                    'title' => '',
                    'reason' => 'Пустое название',
                ];

                continue;
            }

            $manualSlug = trim((string) ($row['slug'] ?? ''));
            $slug = $manualSlug !== ''
                ? SlugHelper::make($manualSlug, $title)
                : SlugHelper::makeUnique(null, $title, fn (string $candidate) => isset($existingSlugSet[$candidate]) || isset($plannedSlugs[$candidate]));

            if (isset($existingSlugSet[$slug])) {
                $skipped[] = [
                    'index' => $index,
                    'title' => $title,
                    'slug' => $slug,
                    'reason' => 'Slug уже занят',
                ];

                continue;
            }

            if (isset($plannedSlugs[$slug])) {
                $skipped[] = [
                    'index' => $index,
                    'title' => $title,
                    'slug' => $slug,
                    'reason' => 'Дублирующийся slug в ответе ИИ',
                ];

                continue;
            }

            $seriesIds = $this->normalizeSeriesIds($row['series_ids'] ?? []);
            $validIds = array_values(array_filter($seriesIds, fn ($id) => isset($validSeriesSet[$id])));
            $invalidCount = count($seriesIds) - count($validIds);

            if ($validIds === []) {
                $skipped[] = [
                    'index' => $index,
                    'title' => $title,
                    'slug' => $slug,
                    'reason' => 'Нет валидных series_ids',
                ];

                continue;
            }

            $keywords = $this->matcher->parseKeywords($row['auto_keywords'] ?? []);
            $warnings = [];
            if ($invalidCount > 0) {
                $warnings[] = 'Пропущено несуществующих series_ids: ' . $invalidCount;
            }

            $preview = [
                'index' => $index,
                'title' => $title,
                'slug' => $slug,
                'description' => trim((string) ($row['description'] ?? '')),
                'meta_title' => trim((string) ($row['meta_title'] ?? '')),
                'meta_description' => trim((string) ($row['meta_description'] ?? '')),
                'auto_keywords' => $keywords,
                'series_ids' => $validIds,
                'series_count' => count($validIds),
                'status' => 'ready',
                'warnings' => $warnings,
            ];

            if (!$dryRun) {
                $collection = Collection::query()->create([
                    'slug' => $slug,
                    'title' => $title,
                    'description' => trim((string) ($row['description'] ?? '')) ?: null,
                    'meta_title' => trim((string) ($row['meta_title'] ?? '')) ?: null,
                    'meta_description' => trim((string) ($row['meta_description'] ?? '')) ?: null,
                    'auto_keywords' => $keywords !== [] ? $keywords : null,
                    'auto_add_enabled' => $keywords !== [],
                    'is_active' => true,
                    'is_hidden' => false,
                    'noindex' => false,
                    'is_pinned' => false,
                    'show_on_home' => false,
                    'sort_order' => 0,
                    'source_updated_at' => now(),
                ]);

                $this->matcher->syncExplicitMembership($collection, $validIds);
                $preview['status'] = 'created';
                $preview['id'] = $collection->id;
                $created++;
                $existingSlugSet[$slug] = true;
            }

            $plannedSlugs[$slug] = true;
            $items[] = $preview;
        }

        if (!$dryRun && $created > 0) {
            TplCache::bumpGlobalVersion();
        }

        return [
            'ok' => $errors === [],
            'dry_run' => $dryRun,
            'items' => $items,
            'skipped' => $skipped,
            'errors' => $errors,
            'created' => $created,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parsePayload(string $payload): ?array
    {
        $payload = trim($payload);
        if ($payload === '') {
            return null;
        }

        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/i', $payload, $matches)) {
            $payload = trim($matches[1]);
        }

        $decoded = json_decode($payload, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  mixed  $value
     * @return list<int>
     */
    private function normalizeSeriesIds(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $ids = [];
        foreach ($value as $item) {
            $id = (int) $item;
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }
}
