<?php

namespace Tests\Unit;

use App\Models\Series;
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
