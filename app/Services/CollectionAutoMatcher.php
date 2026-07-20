<?php

namespace App\Services;

use App\Models\Collection;
use App\Models\CollectionItem;
use App\Models\Series;
use Illuminate\Support\Collection as SupportCollection;

class CollectionAutoMatcher
{
    /**
     * @param  list<string>|string|null  $input
     * @return list<string>
     */
    public function parseKeywords(mixed $input): array
    {
        if ($input === null || $input === '') {
            return [];
        }

        if (is_array($input)) {
            $parts = $input;
        } else {
            $normalized = str_replace(["\r\n", "\r"], "\n", (string) $input);
            $parts = preg_split('/[\n,;]+/', $normalized) ?: [];
        }

        $keywords = [];
        foreach ($parts as $part) {
            $word = trim((string) $part);
            if ($word !== '') {
                $keywords[] = mb_strtolower($word);
            }
        }

        return array_values(array_unique($keywords));
    }

    /**
     * @param  list<string>  $keywords
     */
    public function matchesSeries(Series $series, array $keywords): bool
    {
        if ($keywords === []) {
            return false;
        }

        $haystack = $this->searchableText($series);

        foreach ($keywords as $keyword) {
            if ($keyword === '') {
                continue;
            }

            if (mb_stripos($haystack, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{added: int, removed: int}
     */
    public function refreshAutoItems(Collection $collection): array
    {
        if (!$collection->auto_add_enabled) {
            return ['added' => 0, 'removed' => 0];
        }

        $keywords = $this->parseKeywords($collection->auto_keywords);
        if ($keywords === []) {
            return ['added' => 0, 'removed' => 0];
        }

        $matchingIds = $this->matchingSeriesIds($keywords);
        $existing = CollectionItem::query()
            ->where('collection_id', $collection->id)
            ->get(['series_id', 'is_auto', 'rank_order']);

        $added = 0;
        $removed = 0;
        $maxRank = (int) $existing->max('rank_order');

        foreach ($matchingIds as $seriesId) {
            $row = $existing->firstWhere('series_id', $seriesId);
            if ($row) {
                continue;
            }

            $maxRank++;
            CollectionItem::query()->create([
                'collection_id' => $collection->id,
                'series_id' => $seriesId,
                'rank_order' => $maxRank,
                'is_auto' => true,
            ]);
            $added++;
        }

        foreach ($existing as $row) {
            if (!$row->is_auto) {
                continue;
            }

            if (!in_array((int) $row->series_id, $matchingIds, true)) {
                CollectionItem::query()
                    ->where('collection_id', $collection->id)
                    ->where('series_id', $row->series_id)
                    ->delete();
                $removed++;
            }
        }

        return ['added' => $added, 'removed' => $removed];
    }

    public function syncSeriesToAutoCollections(Series $series): void
    {
        $series->loadMissing(['genres']);

        $collections = Collection::query()
            ->where('auto_add_enabled', true)
            ->whereNotNull('auto_keywords')
            ->get();

        foreach ($collections as $collection) {
            $keywords = $this->parseKeywords($collection->auto_keywords);
            if ($keywords === []) {
                continue;
            }

            $matches = $this->matchesSeries($series, $keywords);
            $item = CollectionItem::query()
                ->where('collection_id', $collection->id)
                ->where('series_id', $series->id)
                ->first();

            if ($matches) {
                if (!$item) {
                    $maxRank = (int) CollectionItem::query()
                        ->where('collection_id', $collection->id)
                        ->max('rank_order');

                    CollectionItem::query()->create([
                        'collection_id' => $collection->id,
                        'series_id' => $series->id,
                        'rank_order' => $maxRank + 1,
                        'is_auto' => true,
                    ]);
                }

                continue;
            }

            if ($item && $item->is_auto) {
                $item->delete();
            }
        }
    }

    /**
     * Replace collection membership with an explicit list from the admin form.
     *
     * @param  list<int>  $seriesIds
     */
    public function syncExplicitMembership(Collection $collection, array $seriesIds): void
    {
        $seriesIds = array_values(array_unique(array_filter(array_map('intval', $seriesIds))));

        CollectionItem::query()
            ->where('collection_id', $collection->id)
            ->when($seriesIds !== [], fn ($q) => $q->whereNotIn('series_id', $seriesIds))
            ->when($seriesIds === [], fn ($q) => $q)
            ->delete();

        foreach ($seriesIds as $rank => $seriesId) {
            CollectionItem::query()->updateOrCreate(
                ['collection_id' => $collection->id, 'series_id' => $seriesId],
                ['rank_order' => $rank + 1, 'is_auto' => false],
            );
        }
    }

    /**
     * @param  list<int>  $seriesIds
     */
    public function syncManualSeries(Collection $collection, array $seriesIds): void
    {
        $seriesIds = array_values(array_unique(array_filter(array_map('intval', $seriesIds))));

        $existing = CollectionItem::query()
            ->where('collection_id', $collection->id)
            ->get(['series_id', 'is_auto']);

        $existingManualIds = $existing
            ->filter(fn ($row) => !$row->is_auto)
            ->pluck('series_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        foreach ($seriesIds as $rank => $seriesId) {
            CollectionItem::query()->updateOrCreate(
                ['collection_id' => $collection->id, 'series_id' => $seriesId],
                ['rank_order' => $rank + 1, 'is_auto' => false],
            );
        }

        $toRemove = array_diff($existingManualIds, $seriesIds);
        if ($toRemove !== []) {
            CollectionItem::query()
                ->where('collection_id', $collection->id)
                ->whereIn('series_id', $toRemove)
                ->where('is_auto', false)
                ->delete();
        }
    }

    /**
     * @param  list<int>  $collectionIds
     */
    public function syncSeriesCollections(Series $series, array $collectionIds): void
    {
        $collectionIds = array_values(array_unique(array_filter(array_map('intval', $collectionIds))));

        $existingManual = CollectionItem::query()
            ->where('series_id', $series->id)
            ->where('is_auto', false)
            ->pluck('collection_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        foreach ($collectionIds as $rank => $collectionId) {
            CollectionItem::query()->updateOrCreate(
                ['collection_id' => $collectionId, 'series_id' => $series->id],
                ['rank_order' => $rank + 1, 'is_auto' => false],
            );
        }

        $toRemove = array_diff($existingManual, $collectionIds);
        if ($toRemove !== []) {
            CollectionItem::query()
                ->where('series_id', $series->id)
                ->whereIn('collection_id', $toRemove)
                ->where('is_auto', false)
                ->delete();
        }
    }

    /**
     * @param  list<string>  $keywords
     * @return list<int>
     */
    private function matchingSeriesIds(array $keywords): array
    {
        return Series::query()
            ->with(['genres:id,name'])
            ->where('is_active', true)
            ->get()
            ->filter(fn (Series $series) => $this->matchesSeries($series, $keywords))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    private function searchableText(Series $series): string
    {
        $parts = [
            $series->title,
            $series->title_en,
            $series->title_original,
            $series->description,
            $series->short_description,
            $series->slogan,
        ];

        /** @var SupportCollection<int, \App\Models\Genre> $genres */
        $genres = $series->relationLoaded('genres') ? $series->genres : collect();
        foreach ($genres as $genre) {
            $parts[] = $genre->name;
        }

        return mb_strtolower(implode(' ', array_filter(array_map(
            fn ($value) => trim((string) $value),
            $parts,
        ))));
    }
}
