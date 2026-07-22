<?php

namespace App\Services;

use App\Models\Series;
use App\Models\Studio;
use App\Models\StudioItem;
use App\Support\ContentTypes;
use App\Support\SlugHelper;
use App\Support\TplCache;
use Illuminate\Support\Facades\Storage;

class TmdbStudioSyncService
{
    public const TYPE_NETWORK = 'network';
    public const TYPE_COMPANY = 'company';

    public function __construct(
        private readonly PosterStorage $posterStorage,
        private readonly TmdbClient $client,
    ) {
    }

    /**
     * Fetch TMDB details for the series and sync studios when tmdb_id is set.
     *
     * @return array{studios: int, logos: int, linked: int}
     */
    public function syncForSeries(Series $series): array
    {
        $empty = ['studios' => 0, 'logos' => 0, 'linked' => 0];

        $tmdbId = trim((string)$series->tmdb_id);
        if ($tmdbId === '' || !$this->client->isConfigured()) {
            return $empty;
        }

        $preferTv = ContentTypes::isSerialLike($series->content_type);
        $details = $preferTv
            ? $this->client->getTvDetails($tmdbId)
            : $this->client->getMovieDetails($tmdbId);

        $usedTvEndpoint = $preferTv;

        if ($details === []) {
            $details = $preferTv
                ? $this->client->getMovieDetails($tmdbId)
                : $this->client->getTvDetails($tmdbId);
            $usedTvEndpoint = !$preferTv;
        }

        if ($details === []) {
            return $empty;
        }

        return $this->syncFromDetails($series, $details, $usedTvEndpoint);
    }

    /**
     * Sync studios from a TMDB TV/movie details payload.
     *
     * TV: networks; movies: production_companies.
     *
     * @param  array<string, mixed>  $details
     * @return array{studios: int, logos: int, linked: int}
     */
    public function syncFromDetails(Series $series, array $details, bool $fromTvEndpoint): array
    {
        $entries = $fromTvEndpoint
            ? $this->extractNetworks($details)
            : $this->extractCompanies($details);

        if ($entries === []) {
            return ['studios' => 0, 'logos' => 0, 'linked' => 0];
        }

        $studioIds = [];
        $createdOrUpdated = 0;
        $logosDownloaded = 0;

        foreach ($entries as $rank => $entry) {
            $studio = $this->findOrCreateStudio($entry);
            if (!$studio) {
                continue;
            }

            $createdOrUpdated++;

            if ($this->ensureLogo($studio, $entry['logo_path'] ?? null)) {
                $logosDownloaded++;
            }

            $studioIds[$studio->id] = $rank;
        }

        if ($studioIds === []) {
            return ['studios' => 0, 'logos' => 0, 'linked' => 0];
        }

        $this->linkSeriesStudios($series, $studioIds);
        TplCache::forgetSeries($series->id);

        return [
            'studios' => $createdOrUpdated,
            'logos' => $logosDownloaded,
            'linked' => count($studioIds),
        ];
    }

    /**
     * Download missing logos for studios that already have TMDB IDs.
     *
     * @return array{checked: int, downloaded: int, failed: int}
     */
    public function fillMissingLogos(int $limit = 100): array
    {
        $result = ['checked' => 0, 'downloaded' => 0, 'failed' => 0];

        if (!$this->client->isConfigured()) {
            return $result;
        }

        $studios = Studio::query()
            ->whereNotNull('tmdb_id')
            ->whereNotNull('tmdb_type')
            ->where(function ($q) {
                $q->whereNull('logo_url')
                    ->orWhere('logo_url', '')
                    ->orWhereRaw("TRIM(COALESCE(logo_url, '')) = ''");
            })
            ->orderBy('id')
            ->limit(max(1, $limit))
            ->get();

        // Also pick studios whose logo_url points to a missing local file.
        if ($studios->count() < $limit) {
            $extra = Studio::query()
                ->whereNotNull('tmdb_id')
                ->whereNotNull('tmdb_type')
                ->whereNotNull('logo_url')
                ->where('logo_url', '!=', '')
                ->where('logo_url', 'like', '/storage/%')
                ->orderBy('id')
                ->limit($limit)
                ->get()
                ->filter(fn (Studio $s) => !$this->hasStoredLogo($s));

            $studios = $studios->merge($extra)->unique('id')->take($limit)->values();
        }

        foreach ($studios as $studio) {
            $result['checked']++;
            // Clear broken local path so ensureLogo will rewrite.
            if (!$this->hasStoredLogo($studio) && trim((string)$studio->logo_url) !== '') {
                $studio->logo_url = null;
                $studio->save();
            }

            $logoPath = $this->fetchLogoPathForStudio($studio);
            if ($this->ensureLogo($studio, $logoPath)) {
                $result['downloaded']++;
            } elseif (!$this->hasStoredLogo($studio->fresh() ?? $studio)) {
                $result['failed']++;
            }
            usleep(150000);
        }

        return $result;
    }

    /**
     * Absolute TMDB image URL for a logo_path.
     */
    public function logoUrl(?string $logoPath, ?string $size = null): ?string
    {
        $logoPath = trim((string)$logoPath);
        if ($logoPath === '') {
            return null;
        }

        if (preg_match('/^https?:\/\//i', $logoPath)) {
            return $logoPath;
        }

        $base = rtrim((string)config('tmdb.image_base_url', 'https://image.tmdb.org/t/p'), '/');
        $size = trim((string)($size ?: config('tmdb.logo_size', 'w500')), '/');
        if ($size === '') {
            $size = 'w500';
        }

        return $base . '/' . $size . '/' . ltrim($logoPath, '/');
    }

    /**
     * @param  array<string, mixed>  $details
     * @return list<array{tmdb_id: int, tmdb_type: string, name: string, logo_path: string|null}>
     */
    private function extractNetworks(array $details): array
    {
        return $this->normalizeEntries($details['networks'] ?? [], self::TYPE_NETWORK);
    }

    /**
     * @param  array<string, mixed>  $details
     * @return list<array{tmdb_id: int, tmdb_type: string, name: string, logo_path: string|null}>
     */
    private function extractCompanies(array $details): array
    {
        return $this->normalizeEntries($details['production_companies'] ?? [], self::TYPE_COMPANY);
    }

    /**
     * @param  mixed  $raw
     * @return list<array{tmdb_id: int, tmdb_type: string, name: string, logo_path: string|null}>
     */
    private function normalizeEntries(mixed $raw, string $type): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $out = [];
        $seen = [];

        foreach ($raw as $item) {
            if (!is_array($item)) {
                continue;
            }

            $name = trim((string)($item['name'] ?? ''));
            $tmdbId = (int)($item['id'] ?? 0);
            if ($name === '' || $tmdbId <= 0) {
                continue;
            }

            $key = $type . ':' . $tmdbId;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $logoPath = isset($item['logo_path']) ? trim((string)$item['logo_path']) : '';
            // TMDB may return literal "null"
            if ($logoPath === '' || strtolower($logoPath) === 'null') {
                $logoPath = '';
            }

            $out[] = [
                'tmdb_id' => $tmdbId,
                'tmdb_type' => $type,
                'name' => $name,
                'logo_path' => $logoPath !== '' ? $logoPath : null,
            ];
        }

        return $out;
    }

    /**
     * @param  array{tmdb_id: int, tmdb_type: string, name: string, logo_path: string|null}  $entry
     */
    private function findOrCreateStudio(array $entry): ?Studio
    {
        $tmdbId = $entry['tmdb_id'];
        $type = $entry['tmdb_type'];
        $name = $entry['name'];

        /** @var Studio|null $studio */
        $studio = Studio::query()
            ->where('tmdb_type', $type)
            ->where('tmdb_id', $tmdbId)
            ->first();

        if ($studio) {
            if ($studio->title !== $name) {
                $studio->title = $name;
                $studio->save();
            }

            return $studio;
        }

        /** @var Studio|null $byTitle */
        $byTitle = Studio::query()->where('title', $name)->first();
        if ($byTitle) {
            if (!$byTitle->tmdb_id) {
                $byTitle->tmdb_id = $tmdbId;
                $byTitle->tmdb_type = $type;
                $byTitle->save();
            }

            return $byTitle;
        }

        $slug = SlugHelper::makeUnique(
            null,
            $name,
            fn (string $candidate) => Studio::query()->where('slug', $candidate)->exists()
        );

        return Studio::query()->create([
            'tmdb_id' => $tmdbId,
            'tmdb_type' => $type,
            'slug' => $slug,
            'title' => $name,
            'sort_order' => 0,
            'is_active' => true,
            'is_hidden' => false,
            'noindex' => true,
            'is_pinned' => false,
        ]);
    }

    private function ensureLogo(Studio $studio, ?string $logoPath): bool
    {
        $studio->refresh();

        // Already have a working logo — keep manual/custom uploads.
        if ($this->hasStoredLogo($studio)) {
            return false;
        }

        // Broken or empty logo_url → clear and rewrite from TMDB.
        if (trim((string)$studio->logo_url) !== '') {
            $studio->logo_url = null;
            $studio->save();
        }

        $paths = [];
        $entryPath = $logoPath !== null ? trim($logoPath) : '';
        if ($entryPath !== '' && strtolower($entryPath) !== 'null') {
            $paths[] = $entryPath;
        }

        $fetched = $this->fetchLogoPathForStudio($studio);
        if ($fetched !== null && !in_array($fetched, $paths, true)) {
            $paths[] = $fetched;
        }

        if ($paths === []) {
            return false;
        }

        $sizes = config('tmdb.logo_size_fallbacks', ['original', 'w500', 'w300', 'w185']);
        if (!is_array($sizes) || $sizes === []) {
            $sizes = ['w500'];
        }

        foreach ($paths as $path) {
            foreach ($sizes as $size) {
                $remoteUrl = $this->logoUrl($path, is_string($size) ? $size : null);
                if ($remoteUrl === null) {
                    continue;
                }

                $stored = $this->posterStorage->storeFromUrl(
                    $remoteUrl,
                    PosterContext::forStudioSlug($studio->slug),
                    false,
                );

                if ($stored) {
                    $studio->logo_url = $stored;
                    $studio->save();

                    return true;
                }
            }
        }

        return false;
    }

    private function hasStoredLogo(Studio $studio): bool
    {
        $url = trim((string)$studio->logo_url);
        if ($url === '') {
            return false;
        }

        if (str_starts_with($url, '/storage/')) {
            $relative = ltrim(substr($url, strlen('/storage/')), '/');

            return $relative !== '' && Storage::disk('public')->exists($relative);
        }

        // External URL — treat as present.
        return true;
    }

    private function fetchLogoPathForStudio(Studio $studio): ?string
    {
        $tmdbId = (int)$studio->tmdb_id;
        if ($tmdbId <= 0) {
            return null;
        }

        $details = match ($studio->tmdb_type) {
            self::TYPE_NETWORK => $this->client->getNetworkDetails($tmdbId),
            self::TYPE_COMPANY => $this->client->getCompanyDetails($tmdbId),
            default => [],
        };

        $logoPath = isset($details['logo_path']) ? trim((string)$details['logo_path']) : '';
        if ($logoPath === '' || strtolower($logoPath) === 'null') {
            return null;
        }

        return $logoPath;
    }

    /**
     * @param  array<int, int>  $studioIds  studio_id => rank_order
     */
    private function linkSeriesStudios(Series $series, array $studioIds): void
    {
        $orderedIds = array_keys($studioIds);

        foreach ($studioIds as $studioId => $rank) {
            StudioItem::query()->updateOrCreate(
                ['studio_id' => $studioId, 'series_id' => $series->id],
                ['rank_order' => (int)$rank],
            );
        }

        // Drop TMDB-linked memberships that are no longer present (keep non-TMDB studios).
        $tmdbStudioIds = Studio::query()
            ->whereNotNull('tmdb_id')
            ->pluck('id')
            ->all();

        if ($tmdbStudioIds !== []) {
            StudioItem::query()
                ->where('series_id', $series->id)
                ->whereIn('studio_id', $tmdbStudioIds)
                ->whereNotIn('studio_id', $orderedIds)
                ->delete();
        }

        $primaryId = $orderedIds[0] ?? null;
        if ($primaryId && !$series->studio_id) {
            $series->studio_id = $primaryId;
            $series->save();
        } elseif ($primaryId && $series->studio_id && !in_array((int)$series->studio_id, $orderedIds, true)) {
            $stillLinked = StudioItem::query()
                ->where('series_id', $series->id)
                ->where('studio_id', $series->studio_id)
                ->exists();
            if (!$stillLinked) {
                $series->studio_id = $primaryId;
                $series->save();
            }
        }
    }
}
