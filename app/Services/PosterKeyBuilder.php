<?php

namespace App\Services;

use App\Support\SiteConfig;
use Illuminate\Support\Str;

class PosterKeyBuilder
{
    public function build(PosterContext $context): string
    {
        if ($context->type === 'collection') {
            return $this->buildCollectionKey($context);
        }

        if ($context->type === 'studio') {
            return $this->buildStudioKey($context);
        }

        if ($context->type === 'person') {
            return $this->buildPersonKey($context);
        }

        return $this->buildSeriesKey($context);
    }

    private function buildSeriesKey(PosterContext $context): string
    {
        $pattern = SiteConfig::str('images_poster_filename');

        return match ($pattern) {
            'kp_id' => (string)($context->kpId ?? 'item'),
            'slug' => $context->slug ?: ('kp-' . ($context->kpId ?? 'item')),
            'title_year' => $this->titleYearKey($context),
            default => 'kp-' . ($context->kpId ?? 'item'),
        };
    }

    private function buildCollectionKey(PosterContext $context): string
    {
        $slug = $context->collectionSlug ?: 'item';
        $pattern = SiteConfig::str('images_collection_filename');

        return match ($pattern) {
            'slug' => $slug,
            default => 'collection-' . $slug,
        } . ($context->variant === 'banner' ? '-banner' : '');
    }

    private function buildStudioKey(PosterContext $context): string
    {
        $slug = $context->studioSlug ?: 'item';
        $pattern = SiteConfig::str('images_studio_filename');

        return match ($pattern) {
            'slug' => $slug,
            default => 'studio-' . $slug,
        };
    }

    private function buildPersonKey(PosterContext $context): string
    {
        $slug = $context->personSlug ?: 'item';

        return 'person-' . $slug;
    }

    private function titleYearKey(PosterContext $context): string
    {
        $base = $context->slug ?: Str::slug((string)($context->title ?? ''));
        $year = $context->year ? (string)$context->year : '';

        if ($base !== '' && $year !== '') {
            return $base . '-' . $year;
        }

        if ($base !== '') {
            return $base;
        }

        return 'kp-' . ($context->kpId ?? 'item');
    }
}
