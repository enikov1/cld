<?php

namespace Tests\Unit;

use App\Models\Series;
use App\Services\TmdbImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TmdbImportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_creates_series_without_kp_id(): void
    {
        config(['tmdb.api_key' => 'tmdb-test']);

        Http::fake([
            '*/tv/66732*' => Http::response([
                'id' => 66732,
                'name' => 'Stranger Things',
                'original_name' => 'Stranger Things',
                'overview' => 'Overview',
                'first_air_date' => '2016-07-15',
                'status' => 'Returning Series',
                'popularity' => 100,
                'vote_average' => 8.6,
                'vote_count' => 1000,
                'genres' => [['id' => 1, 'name' => 'Драма']],
                'origin_country' => ['US'],
                'poster_path' => '/poster.jpg',
                'episode_run_time' => [50],
                'external_ids' => ['imdb_id' => 'tt4574334'],
                'credits' => [
                    'cast' => [['name' => 'Actor One', 'profile_path' => null]],
                    'crew' => [['name' => 'Director One', 'job' => 'Director', 'profile_path' => null]],
                ],
            ]),
            '*/tv/66732/season/*' => Http::response(['episodes' => []]),
            '*/network/*' => Http::response([]),
            '*/company/*' => Http::response([]),
        ]);

        $result = app(TmdbImportService::class)->import('66732', null, [
            'download_poster' => false,
            'sync_schedule' => false,
        ]);

        $this->assertTrue($result['ok']);
        $this->assertInstanceOf(Series::class, $result['series']);
        $this->assertSame('66732', $result['series']->tmdb_id);
        $this->assertNull($result['series']->kp_id);
        $this->assertSame('Stranger Things', $result['series']->title);
        $this->assertDatabaseHas('series', [
            'tmdb_id' => '66732',
            'kp_id' => null,
        ]);
    }
}
