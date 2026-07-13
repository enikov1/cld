<?php

namespace Tests\Unit;

use App\Services\SeriesPopularityService;
use PHPUnit\Framework\TestCase;

class SeriesPopularityServiceTest extends TestCase
{
    public function test_percentile_threshold_for_top_slice(): void
    {
        $values = [10, 20, 30, 40, 50, 60, 70, 80, 90, 100];
        $threshold = SeriesPopularityService::percentileThreshold($values, 15);

        $this->assertSame(90, $threshold);
    }

    public function test_popular_series_requires_min_views_and_percentile(): void
    {
        $views = [
            1 => 100,
            2 => 80,
            3 => 60,
            4 => 40,
            5 => 10,
        ];

        $popular = SeriesPopularityService::popularSeriesIdsFromViews($views, 50, 15);

        $this->assertArrayHasKey(1, $popular);
        $this->assertArrayHasKey(2, $popular);
        $this->assertArrayNotHasKey(5, $popular);
    }
}
