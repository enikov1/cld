<?php

namespace App\Services;

use App\Models\Collection;
use App\Models\Person;
use App\Models\Series;
use App\Models\Studio;

final class PosterContext
{
    public function __construct(
        public readonly string $type = 'series',
        public readonly ?string $kpId = null,
        public readonly ?string $title = null,
        public readonly ?string $slug = null,
        public readonly ?int $year = null,
        public readonly ?string $collectionSlug = null,
        public readonly ?string $studioSlug = null,
        public readonly ?string $personSlug = null,
    ) {
    }

    public static function forSeries(Series $series): self
    {
        return new self(
            type: 'series',
            kpId: (string)$series->kp_id,
            title: $series->title,
            slug: $series->slug,
            year: $series->year ?: $series->start_year,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function forSeriesData(string $kpId, array $data): self
    {
        $year = isset($data['year']) ? (int)$data['year'] : null;
        if (!$year && isset($data['start_year'])) {
            $year = (int)$data['start_year'];
        }

        return new self(
            type: 'series',
            kpId: $kpId,
            title: isset($data['title']) ? (string)$data['title'] : null,
            slug: isset($data['slug']) ? (string)$data['slug'] : null,
            year: $year ?: null,
        );
    }

    public static function forCollection(Collection $collection): self
    {
        return new self(
            type: 'collection',
            collectionSlug: $collection->slug,
        );
    }

    public static function forCollectionSlug(string $slug): self
    {
        return new self(
            type: 'collection',
            collectionSlug: $slug,
        );
    }

    public static function forStudio(Studio $studio): self
    {
        return new self(
            type: 'studio',
            studioSlug: $studio->slug,
        );
    }

    public static function forStudioSlug(string $slug): self
    {
        return new self(
            type: 'studio',
            studioSlug: $slug,
        );
    }

    public static function forPerson(Person $person): self
    {
        return new self(
            type: 'person',
            personSlug: $person->slug,
        );
    }

    public static function forPersonSlug(string $slug): self
    {
        return new self(
            type: 'person',
            personSlug: $slug,
        );
    }
}
