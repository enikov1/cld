<?php

namespace Tests\Unit;

use App\Models\Series;
use App\Models\SiteSetting;
use App\Services\AllohaClient;
use App\Services\AllohaImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class AllohaImportServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        SiteSetting::set('alloha_api_token', 'test-token');
        SiteSetting::set('player_alloha_sync_enabled', '0');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_import_falls_back_to_imdb_when_kp_not_found(): void
    {
        Series::query()->create([
            'kp_id' => '900001',
            'imdb_id' => 'tt0056592',
            'slug' => 'import-imdb-fallback',
            'title' => 'Before import',
            'is_active' => true,
        ]);

        $client = Mockery::mock(AllohaClient::class);
        $client->shouldReceive('isConfigured')->andReturn(true);
        $client->shouldReceive('getMovieWithFallback')
            ->once()
            ->with('900001', 'tt0056592', '')
            ->andReturn([
                'data' => [
                    'name' => 'Imported via IMDb',
                    'ids' => ['kp' => 900001, 'imdb' => 'tt0056592'],
                    'token' => 'import-token',
                    'category' => ['slug' => 'serial'],
                ],
            ]);
        $this->app->instance(AllohaClient::class, $client);

        $result = app(AllohaImportService::class)->importByKpId('900001', [
            'sync_metadata' => true,
            'download_poster' => false,
            'sync_genres_countries' => false,
            'imdb_id' => 'tt0056592',
        ]);

        $this->assertTrue($result['ok']);
        $this->assertSame('Imported via IMDb', $result['series']?->title);
    }

    public function test_import_creates_series_when_not_in_database_yet(): void
    {
        $client = Mockery::mock(AllohaClient::class);
        $client->shouldReceive('isConfigured')->andReturn(true);
        $client->shouldReceive('getMovieWithFallback')
            ->once()
            ->with('5165951', 'tt0056592', '66732')
            ->andReturn([
                'data' => [
                    'name' => 'New series from Alloha',
                    'ids' => ['kp' => 5165951, 'imdb' => 'tt0056592', 'tmdb' => 66732],
                    'token' => 'import-token',
                    'category' => ['slug' => 'serial'],
                ],
            ]);
        $this->app->instance(AllohaClient::class, $client);

        $this->assertNull(Series::query()->where('kp_id', '5165951')->first());

        $result = app(AllohaImportService::class)->importByKpId('5165951', [
            'sync_metadata' => true,
            'download_poster' => false,
            'sync_genres_countries' => false,
            'imdb_id' => 'tt0056592',
            'tmdb_id' => '66732',
        ]);

        $this->assertTrue($result['ok']);
        $this->assertSame('5165951', $result['series']?->kp_id);
        $this->assertSame('New series from Alloha', $result['series']?->title);
    }

    public function test_bump_date_updates_created_at_when_episode_increases(): void
    {
        $oldDate = now()->subDays(10);
        $series = Series::query()->create([
            'kp_id' => '800001',
            'slug' => 'bump-date-episode',
            'title' => 'Before bump',
            'is_active' => true,
            'season_number' => 1,
            'last_episode_number' => 5,
        ]);
        $series->created_at = $oldDate;
        $series->save();

        $this->mockAllohaMovie('800001', [
            'name' => 'Before bump',
            'ids' => ['kp' => 800001],
            'token' => 'bump-token',
            'category' => ['slug' => 'serial'],
            'seasons_count' => 1,
            'last_episode' => 6,
        ]);

        $result = app(AllohaImportService::class)->importByKpId('800001', [
            'bump_date' => true,
            'sync_metadata' => false,
            'sync_ratings' => false,
            'download_poster' => false,
            'sync_genres_countries' => false,
            'sync_tmdb' => false,
        ]);

        $this->assertTrue($result['ok']);
        $fresh = $result['series'];
        $this->assertNotNull($fresh);
        $this->assertTrue($fresh->created_at->greaterThan($oldDate->copy()->addDay()));
        $this->assertSame(6, $fresh->last_episode_number);
        $this->assertNotNull($fresh->last_episode_changed_at);
    }

    public function test_bump_date_skips_when_episode_unchanged(): void
    {
        $oldDate = now()->subDays(10);
        $series = Series::query()->create([
            'kp_id' => '800002',
            'slug' => 'bump-date-skip',
            'title' => 'Same episode',
            'is_active' => true,
            'season_number' => 1,
            'last_episode_number' => 6,
        ]);
        $series->created_at = $oldDate;
        $series->save();

        $this->mockAllohaMovie('800002', [
            'name' => 'Same episode',
            'ids' => ['kp' => 800002],
            'token' => 'bump-token',
            'category' => ['slug' => 'serial'],
            'seasons_count' => 1,
            'last_episode' => 6,
        ]);

        $result = app(AllohaImportService::class)->importByKpId('800002', [
            'bump_date' => true,
            'sync_metadata' => false,
            'sync_ratings' => false,
            'download_poster' => false,
            'sync_genres_countries' => false,
            'sync_tmdb' => false,
        ]);

        $this->assertTrue($result['ok']);
        $fresh = $result['series'];
        $this->assertNotNull($fresh);
        $this->assertSame($oldDate->timestamp, $fresh->created_at->timestamp);
    }

    /**
     * @param array<string,mixed> $data
     */
    private function mockAllohaMovie(string $kpId, array $data): void
    {
        $client = Mockery::mock(AllohaClient::class);
        $client->shouldReceive('isConfigured')->andReturn(true);
        $client->shouldReceive('getMovieWithFallback')
            ->once()
            ->with($kpId, '', '')
            ->andReturn(['data' => $data]);
        $this->app->instance(AllohaClient::class, $client);
        $this->app->forgetInstance(AllohaImportService::class);
    }
}
