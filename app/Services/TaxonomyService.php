<?php

namespace App\Services;

use App\Models\Country;
use App\Models\Genre;
use App\Models\Person;
use App\Models\Series;
use App\Models\Year;
use App\Support\SiteConfig;
use App\Support\SlugHelper;
use App\Support\TplCache;
use App\Support\Utf8;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TaxonomyService
{
    /**
     * @param array{genre_ids?: int[]|null, country_ids?: int[]|null, actor_ids?: int[]|null, director_ids?: int[]|null} $relations
     */
    public function syncSeriesRelations(Series $series, array $relations): void
    {
        if (array_key_exists('genre_ids', $relations)) {
            $series->genres()->sync($this->normalizeIds($relations['genre_ids']));
        }

        if (array_key_exists('country_ids', $relations)) {
            $series->countries()->sync($this->normalizeIds($relations['country_ids']));
        }

        if (array_key_exists('actor_ids', $relations)) {
            $this->syncPeopleByIds($series, $this->normalizeIds($relations['actor_ids']), 'actor');
        }

        if (array_key_exists('director_ids', $relations)) {
            $this->syncPeopleByIds($series, $this->normalizeIds($relations['director_ids']), 'director');
        }
    }

    /**
     * @param list<string> $genreNames
     * @param list<string> $countryNames
     */
    public function syncSeriesFromNames(Series $series, array $genreNames, array $countryNames): void
    {
        $genreIds = [];
        foreach ($genreNames as $name) {
            $entity = $this->findOrCreateByName(Genre::class, $name);
            if ($entity) {
                $genreIds[] = $entity->id;
            }
        }

        $countryIds = [];
        foreach ($countryNames as $name) {
            $entity = $this->findOrCreateByName(Country::class, $name);
            if ($entity) {
                $countryIds[] = $entity->id;
            }
        }

        $series->genres()->sync(array_unique($genreIds));
        $series->countries()->sync(array_unique($countryIds));
    }

    public function ensureSeriesYear(Series $series): ?Year
    {
        $year = (int)($series->year ?: $series->start_year ?: 0);
        if ($year < 1900 || $year > 2100) {
            return null;
        }

        $slug = (string)$year;
        $record = Year::query()->firstOrCreate(
            ['slug' => $slug],
            [
                'name' => $slug,
                'sort_order' => $year,
                'is_active' => true,
                'is_hidden' => false,
                'noindex' => true,
            ],
        );

        if ($record->wasRecentlyCreated) {
            TplCache::bumpGlobalVersion();
        }

        return $record;
    }

    public function syncMissingYearsFromSeries(): int
    {
        $created = 0;

        $years = Series::query()
            ->selectRaw('DISTINCT COALESCE(NULLIF(year, 0), start_year) AS y')
            ->whereNotNull(DB::raw('COALESCE(NULLIF(year, 0), start_year)'))
            ->orderByDesc('y')
            ->pluck('y')
            ->map(fn ($year) => (int)$year)
            ->filter(fn (int $year) => $year >= 1900 && $year <= 2100)
            ->unique()
            ->values();

        foreach ($years as $year) {
            $slug = (string)$year;
            if (Year::query()->where('slug', $slug)->exists()) {
                continue;
            }

            Year::query()->create([
                'slug' => $slug,
                'name' => $slug,
                'sort_order' => $year,
                'is_active' => true,
                'is_hidden' => false,
                'noindex' => true,
            ]);
            $created++;
        }

        if ($created > 0) {
            TplCache::bumpGlobalVersion();
        }

        return $created;
    }

    /**
     * @param list<array{name: string, photo_url?: string|null}> $actors
     * @param list<array{name: string, photo_url?: string|null}> $directors
     */
    public function syncSeriesPeople(Series $series, array $actors, array $directors): void
    {
        if ($actors !== []) {
            $maxActors = SiteConfig::int('import_max_actors');
            if ($maxActors > 0 && count($actors) > $maxActors) {
                $actors = array_slice($actors, 0, $maxActors);
            }
            $actorIds = $this->resolvePeopleIds($actors);
            $this->syncPeopleByIds($series, $actorIds, 'actor');
        }

        if ($directors !== []) {
            $directorIds = $this->resolvePeopleIds($directors);
            $this->syncPeopleByIds($series, $directorIds, 'director');
        }
    }

    /**
     * Parse comma-separated credits string into people entries.
     *
     * @return list<array{name: string, photo_url: null}>
     */
    public static function parseCreditsString(?string $credits): array
    {
        if ($credits === null || trim($credits) === '') {
            return [];
        }

        $people = [];
        foreach (preg_split('/\s*[,;]\s*/', trim($credits)) as $name) {
            $name = trim($name);
            if ($name !== '') {
                $people[] = ['name' => $name, 'photo_url' => null];
            }
        }

        return $people;
    }

    /**
     * @param list<array{name: string, photo_url?: string|null}> $people
     * @return list<int>
     */
    private function resolvePeopleIds(array $people): array
    {
        $ids = [];
        foreach ($people as $entry) {
            $name = trim((string)($entry['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $person = $this->findOrCreatePerson($name, $entry['photo_url'] ?? null);
            if ($person) {
                $ids[] = $person->id;
            }
        }

        return array_values(array_unique($ids));
    }

    private function findOrCreatePerson(string $name, ?string $photoUrl = null): ?Person
    {
        $name = Utf8::ucfirst(trim($name));
        if ($name === '') {
            return null;
        }

        /** @var Person|null $existing */
        $existing = Person::query()
            ->whereIn('name', array_values(array_unique([$name, mb_strtolower($name)])))
            ->first();
        if ($existing) {
            if ($photoUrl && !$existing->photo_url) {
                $existing->photo_url = $this->storePersonPhoto($photoUrl, $existing->slug);
                $existing->save();
            }

            return $existing;
        }

        $slug = SlugHelper::makeUnique(
            null,
            $name,
            fn (string $candidate) => Person::query()->where('slug', $candidate)->exists()
        );

        return Person::query()->create([
            'slug' => $slug,
            'name' => $name,
            'photo_url' => $photoUrl ? $this->storePersonPhoto($photoUrl, $slug) : null,
            'sort_order' => 0,
            'is_active' => true,
        ]);
    }

    private function storePersonPhoto(?string $photoUrl, string $slug): ?string
    {
        $photoUrl = $photoUrl !== null ? trim($photoUrl) : null;
        if ($photoUrl === null || $photoUrl === '') {
            return null;
        }

        if (str_starts_with($photoUrl, '/storage/')) {
            return $photoUrl;
        }

        if (preg_match('/^https?:\/\//i', $photoUrl)) {
            $stored = app(PosterStorage::class)->storeFromUrl(
                $photoUrl,
                PosterContext::forPersonSlug($slug),
            );

            return $stored ?: $photoUrl;
        }

        return $photoUrl;
    }

    /**
     * @param list<int> $personIds
     */
    private function syncPeopleByIds(Series $series, array $personIds, string $role): void
    {
        $sync = [];
        foreach ($personIds as $id) {
            $sync[$id] = ['role' => $role];
        }

        if ($role === 'actor') {
            $series->actors()->sync($sync);
        } else {
            $series->directors()->sync($sync);
        }
    }

    /**
     * @param class-string<Genre|Country|Person> $modelClass
     */
    public function findOrCreateByName(string $modelClass, string $name): ?Model
    {
        $name = Utf8::ucfirst(trim($name));
        if ($name === '') {
            return null;
        }

        /** @var Model|null $existing */
        $existing = $modelClass::query()
            ->whereIn('name', array_values(array_unique([$name, mb_strtolower($name)])))
            ->first();
        if ($existing) {
            return $existing;
        }

        $slug = SlugHelper::makeUnique(
            null,
            $name,
            fn (string $candidate) => $modelClass::query()->where('slug', $candidate)->exists()
        );

        return $modelClass::query()->create([
            'slug' => $slug,
            'name' => $name,
            'sort_order' => 0,
            'is_active' => true,
        ]);
    }

    /**
     * @param array<int|string>|null $ids
     * @return list<int>
     */
    private function normalizeIds(?array $ids): array
    {
        if ($ids === null) {
            return [];
        }

        return array_values(array_unique(array_map('intval', array_filter($ids))));
    }
}
