<?php

namespace Tests\Unit;

use App\Models\PlayerSource;
use App\Models\Series;
use App\Services\RutubeBulkTrailerProgress;
use App\Services\RutubeBulkTrailerSync;
use App\Services\RutubeTrailerService;
use App\Support\PlayerUrlHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RutubeBulkTrailerSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            'rutube.ru/api/search/video/*' => Http::response([
                'results' => [[
                    'id' => 'bulktrailer000000000000000000001',
                    'title' => 'Тест трейлер 2026',
                    'duration' => 90,
                    'hits' => 500,
                    'embed_url' => 'https://rutube.ru/play/embed/bulktrailer000000000000000000001',
                    'video_url' => 'https://rutube.ru/video/bulktrailer000000000000000000001/',
                ]],
            ]),
        ]);
    }

    public function test_progressive_batches_process_all_series(): void
    {
        foreach (['Alpha', 'Beta', 'Gamma'] as $i => $title) {
            Series::query()->create([
                'kp_id' => (string) (900100 + $i),
                'slug' => 'rutube-bulk-' . ($i + 1),
                'title' => $title,
                'year' => 2026,
                'is_active' => true,
                'is_hidden' => false,
            ]);
        }

        $first = app(RutubeBulkTrailerSync::class)->runProgressiveBatch(true, 'Трейлер', 'skip', null, 0, 2);
        $this->assertSame('running', $first['status']);
        $this->assertSame(2, $first['processed']);

        $second = app(RutubeBulkTrailerSync::class)->runProgressiveBatch(false, 'Трейлер', 'skip', null, 0, 2);
        $this->assertSame('done', $second['status']);
        $this->assertSame(3, $second['processed']);
        $this->assertSame(3, $second['synced']);
        $this->assertSame(0, $second['failed']);
    }

    public function test_skip_mode_does_not_replace_existing_trailer(): void
    {
        $series = Series::query()->create([
            'kp_id' => '900201',
            'slug' => 'rutube-skip',
            'title' => 'Skip Film',
            'year' => 2026,
            'is_active' => true,
            'is_hidden' => false,
        ]);

        PlayerSource::query()->create([
            'series_id' => $series->id,
            'provider' => 'Трейлер',
            'iframe_url' => 'https://rutube.ru/play/embed/oldtrailer000000000000000000001',
            'source_key' => RutubeTrailerService::SOURCE_KEY,
            'is_active' => true,
            'priority' => 10,
        ]);

        $progress = app(RutubeBulkTrailerSync::class)->runProgressiveBatch(true, 'Трейлер', 'skip', null, 0, 10);

        $this->assertSame('done', $progress['status']);
        $this->assertSame(1, $progress['skipped']);
        $this->assertSame(0, $progress['synced']);
        $this->assertSame(
            'https://rutube.ru/play/embed/oldtrailer000000000000000000001',
            PlayerSource::query()->where('series_id', $series->id)->value('iframe_url'),
        );
    }

    public function test_update_mode_replaces_and_keeps_trailer_last(): void
    {
        $series = Series::query()->create([
            'kp_id' => '900301',
            'slug' => 'rutube-update',
            'title' => 'Update Film',
            'year' => 2026,
            'is_active' => true,
            'is_hidden' => false,
        ]);

        PlayerSource::query()->create([
            'series_id' => $series->id,
            'provider' => 'Смотреть онлайн',
            'iframe_url' => 'https://example.com/main',
            'is_active' => true,
            'priority' => 100,
        ]);
        PlayerSource::query()->create([
            'series_id' => $series->id,
            'provider' => 'Трейлер',
            'iframe_url' => 'https://rutube.ru/play/embed/oldtrailer000000000000000000002',
            'source_key' => RutubeTrailerService::SOURCE_KEY,
            'is_active' => true,
            'priority' => 50,
        ]);

        $progress = app(RutubeBulkTrailerSync::class)->runProgressiveBatch(true, 'Трейлер', 'update', null, 0, 10);

        $this->assertSame('done', $progress['status']);
        $this->assertSame(1, $progress['synced']);

        $players = PlayerUrlHelper::activePlayersForSeries($series->fresh());
        $this->assertCount(2, $players);
        $this->assertSame('Смотреть онлайн', $players[0]['label']);
        $this->assertSame('Трейлер', $players[1]['label']);
        $this->assertSame(
            'https://rutube.ru/play/embed/bulktrailer000000000000000000001',
            $players[1]['url'],
        );
    }

    public function test_pause_and_resume_preserve_cursor(): void
    {
        foreach (['One', 'Two', 'Three'] as $i => $title) {
            Series::query()->create([
                'kp_id' => (string) (900400 + $i),
                'slug' => 'rutube-pause-' . ($i + 1),
                'title' => $title,
                'year' => 2026,
                'is_active' => true,
                'is_hidden' => false,
            ]);
        }

        $first = app(RutubeBulkTrailerSync::class)->runProgressiveBatch(true, 'Трейлер', 'skip', null, 0, 1);
        $this->assertSame('running', $first['status']);
        $this->assertSame(1, $first['processed']);

        $paused = app(RutubeBulkTrailerSync::class)->pause();
        $this->assertSame('paused', $paused['status']);
        $this->assertGreaterThan(0, $paused['after_id']);

        $blocked = app(RutubeBulkTrailerSync::class)->runProgressiveBatch(false, 'Трейлер', 'skip', null, 0, 1);
        $this->assertSame('paused', $blocked['status']);
        $this->assertSame(1, $blocked['processed']);

        app(RutubeBulkTrailerSync::class)->resume();

        $second = app(RutubeBulkTrailerSync::class)->runProgressiveBatch(false, 'Трейлер', 'skip', null, 0, 10);
        $this->assertSame('running', $second['status']);
        $this->assertSame(2, $second['processed']);

        $third = app(RutubeBulkTrailerSync::class)->runProgressiveBatch(false, 'Трейлер', 'skip', null, 0, 10);
        $this->assertSame('done', $third['status']);
        $this->assertSame(3, $third['processed']);
        $this->assertSame(3, $third['synced']);
    }

    public function test_stop_prevents_continue(): void
    {
        Series::query()->create([
            'kp_id' => '900501',
            'slug' => 'rutube-stop-1',
            'title' => 'Stop One',
            'year' => 2026,
            'is_active' => true,
            'is_hidden' => false,
        ]);
        Series::query()->create([
            'kp_id' => '900502',
            'slug' => 'rutube-stop-2',
            'title' => 'Stop Two',
            'year' => 2026,
            'is_active' => true,
            'is_hidden' => false,
        ]);

        app(RutubeBulkTrailerSync::class)->runProgressiveBatch(true, 'Трейлер', 'skip', null, 0, 1);
        $stopped = app(RutubeBulkTrailerSync::class)->stop();
        $this->assertSame('stopped', $stopped['status']);

        $blocked = app(RutubeBulkTrailerSync::class)->runProgressiveBatch(false, 'Трейлер', 'skip', null, 0, 10);
        $this->assertSame('stopped', $blocked['status']);
        $this->assertSame(1, RutubeBulkTrailerProgress::get()['processed']);
    }
}
