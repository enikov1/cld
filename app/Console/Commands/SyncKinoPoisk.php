<?php

namespace App\Console\Commands;

use App\Models\Series;
use App\Services\CdnVideoHubPlayerSync;
use App\Services\KinoPoiskClient;
use App\Services\KinoPoiskMapper;
use App\Services\KinoPoiskStaffMapper;
use App\Services\PosterContext;
use App\Services\PosterStorage;
use App\Services\SitemapService;
use App\Services\TaxonomyService;
use App\Services\TmdbPopularitySyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SyncKinoPoisk extends Command
{
    protected $signature = 'kp:sync
        {keyword : Ключевое слово для поиска}
        {--limit=20 : Сколько результатов обработать}
        {--sleep=0 : Пауза (сек) между запросами}
        {--download-poster : Скачать постер на сервер}
    ';

    protected $description = 'Sync series metadata from KinoPoisk API';

    public function handle(): int
    {
        $keyword = (string)$this->argument('keyword');
        $limit = (int)$this->option('limit');
        $sleep = (float)$this->option('sleep');
        $downloadPoster = (bool)$this->option('download-poster');

        if ($keyword === '') {
            $this->error('Missing keyword.');
            return self::FAILURE;
        }

        /** @var KinoPoiskClient $client */
        $client = app(KinoPoiskClient::class);
        if (!$client->isConfigured()) {
            $this->error('API-ключ KinoPoisk не настроен. Укажите его в админке: Настройки → KinoPoisk API.');
            return self::FAILURE;
        }

        $this->info('Searching KinoPoisk...');
        $films = $client->searchByKeyword($keyword, $limit);
        if (count($films) === 0) {
            $this->warn('No films found.');
            return self::SUCCESS;
        }

        $posterStorage = app(PosterStorage::class);
        $count = 0;

        foreach ($films as $film) {
            $filmId = $film['filmId'] ?? $film['kinopoiskId'] ?? $film['id'] ?? null;
            if ($filmId === null || (string)$filmId === '') {
                continue;
            }

            $details = $client->getFilm($filmId);
            if ($details === []) {
                continue;
            }

            $mapped = KinoPoiskMapper::toSeriesAttributes(
                $details,
                $film,
                $client->getDistributions($filmId),
            );
            if ($mapped === []) {
                continue;
            }

            $kpId = (string)$mapped['kp_id'];
            $baseSlug = Str::slug($mapped['title']);
            $slug = Series::query()->where('slug', $baseSlug)->where('kp_id', '!=', $kpId)->exists()
                ? $baseSlug . '-' . $kpId
                : $baseSlug;

            $posterUrl = null;
            if ($downloadPoster && !empty($mapped['poster_source_url'])) {
                $posterUrl = $posterStorage->storeFromUrl(
                    $mapped['poster_source_url'],
                    PosterContext::forSeriesData($kpId, array_merge($mapped, ['slug' => $slug])),
                );
            }
            if (!$posterUrl && !empty($mapped['poster_source_url'])) {
                $posterUrl = $mapped['poster_source_url'];
            }

            $genreNames = $mapped['_genre_names'] ?? [];
            $countryNames = $mapped['_country_names'] ?? [];
            unset($mapped['poster_source_url'], $mapped['_genre_names'], $mapped['_country_names']);

            $series = Series::query()->updateOrCreate(
                ['kp_id' => $kpId],
                array_merge($mapped, [
                    'slug' => $slug,
                    'poster_url' => $posterUrl,
                    'is_active' => true,
                ])
            );

            app(TaxonomyService::class)->syncSeriesFromNames($series, $genreNames, $countryNames);

            $staff = $client->getStaff($filmId);
            $people = KinoPoiskStaffMapper::toPeopleLists($staff);
            app(TaxonomyService::class)->syncSeriesPeople(
                $series,
                $people['_actor_people'],
                $people['_director_people'],
            );

            app(CdnVideoHubPlayerSync::class)->syncIfEnabled($series);

            if (trim((string)$series->tmdb_id) !== '') {
                app(TmdbPopularitySyncService::class)->syncSeries($series->fresh(), true, false);
            }

            $count++;
            if ($sleep > 0) {
                usleep((int)($sleep * 1_000_000));
            }
        }

        $this->info("Synced series: {$count}");

        if ($count > 0) {
            app(SitemapService::class)->markDirty();
        }

        return self::SUCCESS;
    }
}
