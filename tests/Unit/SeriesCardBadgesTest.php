<?php

namespace Tests\Unit;

use App\Models\NotificationEvent;
use App\Models\Series;
use App\Services\SeriesCardMapper;
use App\Services\SeriesViewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SeriesViewServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_record_increments_views_once_per_session_window(): void
    {
        $series = $this->makeSeries();
        $request = Request::create('/series/test.html', 'GET');
        $request->setLaravelSession(app('session')->driver());
        $request->session()->start();

        SeriesViewService::record($series, $request);
        SeriesViewService::record($series, $request);

        $series->refresh();

        $this->assertSame(1, $series->views_count);
        $this->assertDatabaseHas('series_view_daily', [
            'series_id' => $series->id,
            'views_count' => 1,
        ]);
    }

    public function test_record_counts_again_after_dedup_cache_expires(): void
    {
        Cache::flush();
        $series = $this->makeSeries();
        $request = Request::create('/series/test.html', 'GET');
        $request->setLaravelSession(app('session')->driver());
        $request->session()->start();

        SeriesViewService::record($series, $request);
        Cache::flush();
        SeriesViewService::record($series, $request);

        $series->refresh();
        $this->assertSame(2, $series->views_count);
    }

    private function makeSeries(): Series
    {
        return Series::query()->create([
            'kp_id' => 'kp-' . uniqid(),
            'slug' => 'test-series-' . uniqid(),
            'title' => 'Test Series',
            'is_active' => true,
            'is_hidden' => false,
        ]);
    }
}

class SeriesCardMapperTest extends TestCase
{
    use RefreshDatabase;

    public function test_map_series_includes_badges_and_labels(): void
    {
        $series = Series::query()->create([
            'kp_id' => 'kp-card-mapper',
            'slug' => 'card-mapper-series',
            'title' => 'Mapper Series',
            'season_number' => 2,
            'last_episode_number' => 7,
            'popular_badge_active' => true,
            'last_episode_changed_at' => now(),
            'is_active' => true,
            'is_hidden' => false,
        ]);

        NotificationEvent::query()->create([
            'series_id' => $series->id,
            'season_number' => 2,
            'episode_number' => 7,
            'event_type' => 'new_episode',
            'created_at' => now(),
        ]);

        $mapped = SeriesCardMapper::mapSeries([$series])[0];

        $this->assertSame('S2', $mapped['season_badge']);
        $this->assertSame('E7', $mapped['episode_badge']);
        $this->assertTrue($mapped['badge_new_episode']);
        $this->assertTrue($mapped['badge_popular']);
        $this->assertSame('2 сезон, 7 серия', $mapped['episode_progress_label']);
    }
}
