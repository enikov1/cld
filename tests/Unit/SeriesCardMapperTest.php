<?php

namespace Tests\Unit;

use App\Models\NotificationEvent;
use App\Models\Series;
use App\Services\SeriesCardMapper;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

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
        $this->assertFalse($mapped['badge_coming_soon']);
        $this->assertSame('2 сезон, 7 серия', $mapped['episode_progress_label']);
    }

    public function test_map_series_includes_coming_soon_badge_and_countdown(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-13'));

        $series = Series::query()->create([
            'kp_id' => 'kp-coming-soon-mapper',
            'slug' => 'coming-soon-mapper',
            'title' => 'Soon Series',
            'premiere_date' => '2026-08-16',
            'is_coming_soon' => true,
            'is_active' => true,
            'is_hidden' => false,
        ]);

        $mapped = SeriesCardMapper::mapSeries([$series])[0];

        $this->assertTrue($mapped['badge_coming_soon']);
        $this->assertSame('Скоро', $mapped['badge_coming_soon_label']);
        $this->assertSame('через 3 дня', $mapped['premiere_countdown_label']);

        Carbon::setTestNow();
    }
}
