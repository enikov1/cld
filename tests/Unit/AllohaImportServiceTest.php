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
}
