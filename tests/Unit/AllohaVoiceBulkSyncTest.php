<?php

namespace Tests\Unit;

use App\Models\Series;
use App\Models\SiteSetting;
use App\Models\Voice;
use App\Services\AllohaClient;
use App\Services\AllohaVoiceBulkSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class AllohaVoiceBulkSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_batch_attaches_alloha_voices_to_existing_series(): void
    {
        SiteSetting::set('alloha_api_token', 'test-token');

        $series = Series::query()->create([
            'kp_id' => '5165951',
            'slug' => 'voice-bulk-sync',
            'title' => 'Mentalist',
            'is_active' => true,
            'is_hidden' => false,
        ]);

        $client = Mockery::mock(AllohaClient::class);
        $client->shouldReceive('isConfigured')->andReturn(true);
        $client->shouldNotReceive('translations');
        $client->shouldReceive('getMovieWithFallback')
            ->once()
            ->with('5165951', '', '', Mockery::any(), Mockery::any())
            ->andReturn([
                'data' => [
                    'name' => 'Mentalist',
                    'ids' => ['kp' => 5165951],
                    'translations' => [
                        ['id' => 10, 'name' => 'LostFilm', 'iframe' => 'https://example.com/lf'],
                        ['id' => 20, 'name' => 'Coldfilm', 'iframe' => 'https://example.com/cf'],
                    ],
                ],
            ]);
        $this->app->instance(AllohaClient::class, $client);

        $progress = app(AllohaVoiceBulkSync::class)->runProgressiveBatch(true);

        $this->assertSame('done', $progress['status']);
        $this->assertSame(1, $progress['synced']);
        $this->assertSame(2, Voice::query()->count());
        $this->assertEqualsCanonicalizing(
            ['Coldfilm', 'LostFilm'],
            $series->voices()->pluck('name')->all(),
        );
    }

    public function test_does_not_keep_voices_missing_from_series(): void
    {
        SiteSetting::set('alloha_api_token', 'test-token');

        Voice::query()->create([
            'slug' => 'unused-studio',
            'name' => 'Unused Studio',
            'sort_order' => 0,
            'is_active' => true,
        ]);

        Series::query()->create([
            'kp_id' => '5165952',
            'slug' => 'voice-unused-purge',
            'title' => 'No Voices',
            'is_active' => true,
            'is_hidden' => false,
        ]);

        $client = Mockery::mock(AllohaClient::class);
        $client->shouldReceive('isConfigured')->andReturn(true);
        $client->shouldNotReceive('translations');
        $client->shouldReceive('getMovieWithFallback')
            ->once()
            ->andReturn([
                'data' => [
                    'name' => 'No Voices',
                    'ids' => ['kp' => 5165952],
                    'translations' => [],
                ],
            ]);
        $this->app->instance(AllohaClient::class, $client);

        $progress = app(AllohaVoiceBulkSync::class)->runProgressiveBatch(true);

        $this->assertSame('done', $progress['status']);
        $this->assertSame(0, Voice::query()->count());
    }
}
