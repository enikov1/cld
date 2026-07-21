<?php

namespace Tests\Unit;

use App\Models\PlayerSource;
use App\Models\Series;
use App\Models\SiteSetting;
use App\Services\AllohaBulkPlayerSync;
use App\Services\AllohaClient;
use App\Services\AllohaPlayerSync;
use App\Services\CdnVideoHubPlayerSync;
use App\Support\PlayerUrlHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class AllohaBulkPlayerSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        SiteSetting::set('alloha_api_token', 'test-token');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_resolve_iframe_uses_exists_api_url(): void
    {
        $series = Series::query()->create([
            'kp_id' => '500003',
            'slug' => 'alloha-exists-test',
            'title' => 'Exists Test',
            'is_active' => true,
        ]);

        $client = Mockery::mock(AllohaClient::class);
        $client->shouldReceive('movieExists')
            ->once()
            ->with(['kp' => '500003'])
            ->andReturn([
                'exists' => true,
                'iframe' => 'https://api.alloha.tv/player/abc123',
            ]);
        $client->shouldNotReceive('getMovieByKp');
        $this->app->instance(AllohaClient::class, $client);

        $resolved = app(AllohaBulkPlayerSync::class)->resolveIframe($series);

        $this->assertSame(1, $resolved['api_calls']);
        $this->assertSame('https://api.alloha.tv/player/abc123', $resolved['iframe']);
    }

    public function test_upsert_places_player_at_requested_position(): void
    {
        $series = Series::query()->create([
            'kp_id' => '500001',
            'slug' => 'alloha-position-test',
            'title' => 'Position Test',
            'is_active' => true,
        ]);

        PlayerSource::query()->create([
            'series_id' => $series->id,
            'provider' => 'Coldfilm',
            'iframe_url' => '<video-player data-title-id="500001"></video-player>',
            'source_key' => CdnVideoHubPlayerSync::SOURCE_KEY,
            'is_active' => true,
            'priority' => 100,
        ]);

        $client = Mockery::mock(AllohaClient::class);
        $client->shouldReceive('movieExists')
            ->once()
            ->with(['kp' => '500001'])
            ->andReturn([
                'exists' => true,
                'iframe' => 'https://api.alloha.tv/player/500001',
            ]);
        $this->app->instance(AllohaClient::class, $client);

        app(AllohaBulkPlayerSync::class)->runProgressiveBatch(true, 'Плеер 2', 2);

        $alloha = PlayerSource::query()
            ->where('series_id', $series->id)
            ->where('source_key', AllohaPlayerSync::SOURCE_KEY)
            ->first();

        $this->assertNotNull($alloha);
        $this->assertSame(10, $alloha->priority);

        $players = PlayerUrlHelper::activePlayersForSeries($series->fresh());
        $this->assertCount(2, $players);
        $this->assertSame('Coldfilm', $players[0]['label']);
        $this->assertSame('Плеер 2', $players[1]['label']);
    }

    public function test_sync_one_at_last_appends_alloha_player(): void
    {
        $series = Series::query()->create([
            'kp_id' => '500002',
            'slug' => 'alloha-last-test',
            'title' => 'Last Position Test',
            'is_active' => true,
        ]);

        PlayerSource::query()->create([
            'series_id' => $series->id,
            'provider' => 'Смотреть онлайн',
            'iframe_url' => '<video-player data-title-id="500002"></video-player>',
            'source_key' => CdnVideoHubPlayerSync::SOURCE_KEY,
            'is_active' => true,
            'priority' => 100,
        ]);

        $client = Mockery::mock(AllohaClient::class);
        $client->shouldReceive('movieExists')
            ->once()
            ->with(['kp' => '500002'])
            ->andReturn([
                'exists' => true,
                'iframe' => 'https://api.alloha.tv/player/500002',
            ]);
        $this->app->instance(AllohaClient::class, $client);

        $result = app(AllohaBulkPlayerSync::class)->syncOneAtLast($series, 'Alloha');

        $this->assertTrue($result['ok']);

        $players = PlayerUrlHelper::activePlayersForSeries($series->fresh());
        $this->assertCount(2, $players);
        $this->assertSame('Смотреть онлайн', $players[0]['label']);
        $this->assertSame('Alloha', $players[1]['label']);
    }

    public function test_upsert_overwrites_existing_alloha_tab(): void
    {
        $series = Series::query()->create([
            'kp_id' => '500004',
            'slug' => 'alloha-bulk-test',
            'title' => 'Test',
            'is_active' => true,
        ]);

        PlayerSource::query()->create([
            'series_id' => $series->id,
            'provider' => 'Старое название',
            'iframe_url' => 'https://example.com/old',
            'source_key' => AllohaPlayerSync::SOURCE_KEY,
            'alloha_translation_id' => 77,
            'is_active' => true,
            'priority' => 50,
        ]);

        $client = Mockery::mock(AllohaClient::class);
        $client->shouldReceive('movieExists')
            ->once()
            ->with(['kp' => '500004'])
            ->andReturn([
                'exists' => true,
                'iframe' => 'https://api.alloha.tv/player/new',
            ]);
        $this->app->instance(AllohaClient::class, $client);

        $progress = app(AllohaBulkPlayerSync::class)->runProgressiveBatch(true, 'Alloha TV', 1);

        $this->assertSame('done', $progress['status']);
        $this->assertSame(1, $progress['synced']);

        $sources = PlayerSource::query()->where('series_id', $series->id)->get();
        $this->assertCount(1, $sources);
        $this->assertSame('Alloha TV', $sources->first()->provider);
        $this->assertSame('https://api.alloha.tv/player/new', $sources->first()->iframe_url);
        $this->assertNull($sources->first()->alloha_translation_id);
    }

    public function test_progressive_batch_processes_in_chunks(): void
    {
        foreach (['600001', '600002', '600003'] as $index => $kpId) {
            Series::query()->create([
                'kp_id' => $kpId,
                'slug' => 'alloha-batch-' . $index,
                'title' => 'Batch ' . $index,
                'is_active' => true,
            ]);
        }

        $client = Mockery::mock(AllohaClient::class);
        $client->shouldReceive('movieExists')
            ->times(3)
            ->andReturn([
                'exists' => true,
                'iframe' => 'https://api.alloha.tv/player/chunk',
            ]);
        $this->app->instance(AllohaClient::class, $client);

        config(['alloha.bulk_batch_size' => 2]);

        $first = app(AllohaBulkPlayerSync::class)->runProgressiveBatch(true, 'Alloha', 1);
        $this->assertSame('running', $first['status']);
        $this->assertSame(2, $first['processed']);
        $this->assertSame(2, $first['synced']);

        $second = app(AllohaBulkPlayerSync::class)->runProgressiveBatch(false, 'Alloha', 1);
        $this->assertSame('done', $second['status']);
        $this->assertSame(3, $second['processed']);
        $this->assertSame(3, $second['synced']);
    }

    public function test_sync_falls_back_to_imdb_when_kp_not_found(): void
    {
        Series::query()->create([
            'kp_id' => '500010',
            'imdb_id' => 'tt0056592',
            'slug' => 'alloha-imdb-fallback',
            'title' => 'IMDB Fallback',
            'is_active' => true,
        ]);

        $client = Mockery::mock(AllohaClient::class);
        $client->shouldReceive('movieExists')
            ->once()
            ->with(['kp' => '500010'])
            ->andReturn(['exists' => false]);
        $client->shouldReceive('getMovieByKp')
            ->once()
            ->with('500010')
            ->andReturn([]);
        $client->shouldReceive('movieExists')
            ->once()
            ->with(['imdb' => 'tt0056592'])
            ->andReturn([
                'exists' => true,
                'iframe' => 'https://api.alloha.tv/player/imdb',
            ]);
        $client->shouldNotReceive('getMovieByImdb');
        $this->app->instance(AllohaClient::class, $client);

        $resolved = app(AllohaBulkPlayerSync::class)->resolveIframe(
            Series::query()->where('kp_id', '500010')->first(),
        );

        $this->assertSame('https://api.alloha.tv/player/imdb', $resolved['iframe']);
    }

    public function test_sync_falls_back_to_tmdb_when_kp_and_imdb_not_found(): void
    {
        Series::query()->create([
            'kp_id' => '500011',
            'imdb_id' => 'tt0000001',
            'tmdb_id' => '298939',
            'slug' => 'alloha-tmdb-fallback',
            'title' => 'TMDB Fallback',
            'is_active' => true,
        ]);

        $client = Mockery::mock(AllohaClient::class);
        $client->shouldReceive('movieExists')
            ->once()
            ->with(['kp' => '500011'])
            ->andReturn(['exists' => false]);
        $client->shouldReceive('getMovieByKp')->once()->with('500011')->andReturn([]);
        $client->shouldReceive('movieExists')
            ->once()
            ->with(['imdb' => 'tt0000001'])
            ->andReturn(['exists' => false]);
        $client->shouldReceive('getMovieByImdb')->once()->with('tt0000001')->andReturn([]);
        $client->shouldReceive('movieExists')
            ->once()
            ->with(['tmdb' => '298939'])
            ->andReturn([
                'exists' => true,
                'iframe' => 'https://api.alloha.tv/player/tmdb',
            ]);
        $client->shouldNotReceive('getMovieByTmdb');
        $this->app->instance(AllohaClient::class, $client);

        $resolved = app(AllohaBulkPlayerSync::class)->resolveIframe(
            Series::query()->where('kp_id', '500011')->first(),
        );

        $this->assertSame('https://api.alloha.tv/player/tmdb', $resolved['iframe']);
    }

    public function test_sync_skipped_when_not_in_alloha(): void
    {
        Series::query()->create([
            'kp_id' => '500002',
            'slug' => 'alloha-bulk-skip',
            'title' => 'Skip',
            'is_active' => true,
        ]);

        $client = Mockery::mock(AllohaClient::class);
        $client->shouldReceive('movieExists')->once()->with(['kp' => '500002'])->andReturn(['exists' => false]);
        $client->shouldReceive('getMovieByKp')->once()->with('500002')->andReturn([]);
        $this->app->instance(AllohaClient::class, $client);

        $progress = app(AllohaBulkPlayerSync::class)->runProgressiveBatch(true, 'Alloha', 1);

        $this->assertSame('done', $progress['status']);
        $this->assertSame(0, $progress['synced']);
        $this->assertSame(1, $progress['skipped']);
    }
}
