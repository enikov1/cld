<?php

namespace Tests\Unit;

use App\Models\Episode;
use App\Models\Season;
use App\Models\Series;
use App\Services\EpisodeProgressService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EpisodeProgressServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolved_progress_prefers_latest_released_from_schedule(): void
    {
        $series = Series::query()->create([
            'kp_id' => 'kp-progress-schedule',
            'slug' => 'progress-schedule',
            'title' => 'Progress Schedule',
            'season_number' => 1,
            'last_episode_number' => 1,
            'is_active' => true,
            'is_hidden' => false,
        ]);

        $season = Season::query()->create([
            'series_id' => $series->id,
            'season_number' => 1,
            'title' => 'Сезон 1',
        ]);

        Episode::query()->create([
            'season_id' => $season->id,
            'episode_number' => 2,
            'status' => Episode::STATUS_RELEASED,
            'release_at' => now()->subDays(3),
        ]);
        Episode::query()->create([
            'season_id' => $season->id,
            'episode_number' => 3,
            'status' => Episode::STATUS_RELEASED,
            'release_at' => now()->subDay(),
        ]);
        Episode::query()->create([
            'season_id' => $season->id,
            'episode_number' => 4,
            'status' => Episode::STATUS_SCHEDULED,
            'release_at' => now()->addDay(),
        ]);

        $progress = EpisodeProgressService::resolvedProgress($series->fresh());

        $this->assertTrue($progress['from_schedule']);
        $this->assertSame(1, $progress['season_number']);
        $this->assertSame(3, $progress['last_episode_number']);
        $this->assertSame('1 сезон, 3 серия', $progress['label']);
        $this->assertSame('1 сезон, 3 серия', $series->fresh()->episodeProgressLabel());
    }

    public function test_resolved_progress_falls_back_to_series_fields_without_released(): void
    {
        $series = Series::query()->create([
            'kp_id' => 'kp-progress-fields',
            'slug' => 'progress-fields',
            'title' => 'Progress Fields',
            'season_number' => 2,
            'last_episode_number' => 5,
            'is_active' => true,
            'is_hidden' => false,
        ]);

        $progress = EpisodeProgressService::resolvedProgress($series);

        $this->assertFalse($progress['from_schedule']);
        $this->assertSame(2, $progress['season_number']);
        $this->assertSame(5, $progress['last_episode_number']);
        $this->assertSame('2 сезон, 5 серия', $progress['label']);
    }
}
