<?php

namespace Tests\Unit;

use App\Models\Series;
use App\Models\SeriesViewDaily;
use App\Services\AdminViewsStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AdminViewsStatsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_aggregates_period_and_top_series(): void
    {
        Cache::flush();

        $hot = $this->makeSeries('Hot Show');
        $cold = $this->makeSeries('Cold Show');

        $this->seedDaily($hot->id, now()->toDateString(), 40);
        $this->seedDaily($hot->id, now()->subDay()->toDateString(), 10);
        $this->seedDaily($cold->id, now()->toDateString(), 5);

        $report = AdminViewsStatsService::report('7d', 'day');

        $this->assertTrue($report['ready']);
        $this->assertSame(55, $report['summary']['views_period']);
        $this->assertSame(45, $report['summary']['views_today']);
        $this->assertSame(2, $report['summary']['series_active_period']);
        $this->assertNotEmpty($report['timeseries']);
        $this->assertSame($hot->id, $report['top_series'][0]['id']);
        $this->assertSame(50, $report['top_series'][0]['views']);
    }

    public function test_report_is_cached(): void
    {
        Cache::flush();
        $series = $this->makeSeries('Cached');
        $this->seedDaily($series->id, now()->toDateString(), 3);

        $first = AdminViewsStatsService::report('today', 'day');
        $this->seedDaily($series->id, now()->toDateString(), 100);
        $second = AdminViewsStatsService::report('today', 'day');

        $this->assertSame($first['summary']['views_period'], $second['summary']['views_period']);
    }

    private function makeSeries(string $title): Series
    {
        return Series::query()->create([
            'kp_id' => 'kp-' . uniqid(),
            'slug' => 'series-' . uniqid(),
            'title' => $title,
            'is_active' => true,
            'is_hidden' => false,
            'views_count' => 0,
        ]);
    }

    private function seedDaily(int $seriesId, string $date, int $views): void
    {
        $row = SeriesViewDaily::query()
            ->where('series_id', $seriesId)
            ->whereDate('view_date', $date)
            ->first();

        if ($row) {
            $row->views_count = $views;
            $row->save();
            return;
        }

        SeriesViewDaily::query()->create([
            'series_id' => $seriesId,
            'view_date' => $date,
            'views_count' => $views,
        ]);
    }
}
