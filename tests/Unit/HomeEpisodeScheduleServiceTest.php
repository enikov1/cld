<?php

namespace Tests\Unit;

use App\Models\Episode;
use App\Models\Genre;
use App\Models\NavItem;
use App\Models\Season;
use App\Models\Series;
use App\Services\HomeEpisodeScheduleService;
use App\Support\NavMenuBuilder;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeEpisodeScheduleServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_calendar_month_groups_episodes_and_builds_timeline(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-13 12:00:00'));

        $series = Series::query()->create([
            'kp_id' => 'kp-calendar-1',
            'slug' => 'calendar-series',
            'title' => 'Календарный сериал',
            'title_original' => 'Calendar Series',
            'year' => 2026,
            'age_limit' => '16',
            'kp_rating' => 7.4,
            'imdb_rating' => 8.1,
            'channel_name' => 'HBO',
            'is_active' => true,
            'is_hidden' => false,
        ]);

        $genre = Genre::query()->create([
            'slug' => 'drama-cal',
            'name' => 'Драма',
            'is_active' => true,
            'is_hidden' => false,
        ]);
        $series->genres()->attach($genre->id);

        $season = Season::query()->create([
            'series_id' => $series->id,
            'season_number' => 2,
            'title' => 'Сезон 2',
        ]);

        Episode::query()->create([
            'season_id' => $season->id,
            'episode_number' => 5,
            'title' => 'Пятая серия',
            'release_at' => '2026-08-13 20:00:00',
            'status' => Episode::STATUS_RELEASED,
        ]);

        $data = HomeEpisodeScheduleService::calendarMonth(2026, 8, true);

        $this->assertSame(2026, $data['year']);
        $this->assertSame(8, $data['month']);
        $this->assertSame('Август 2026', $data['month_label']);
        $this->assertSame(1, $data['episode_count']);
        $this->assertSame([13], $data['days_with_episodes']);
        $this->assertArrayHasKey('2026-08-13', $data['days']);

        $item = $data['days']['2026-08-13'][0];
        $this->assertSame('Календарный сериал', $item['series_title']);
        $this->assertSame('Calendar Series', $item['title_original']);
        $this->assertSame(2, $item['season_number']);
        $this->assertSame(5, $item['episode_number']);
        $this->assertTrue($item['is_released']);
        $this->assertSame('Драма', $item['genres_label']);
        $this->assertSame('7.4', $item['kp_rating']);
        $this->assertSame('8.1', $item['imdb_rating']);
        $this->assertSame('HBO', $item['channel_name']);

        $this->assertCount(1, $data['timeline']);
        $this->assertSame('13 августа', $data['timeline'][0]['date_label']);
        $this->assertSame('четверг', $data['timeline'][0]['weekday']);
        $this->assertTrue($data['timeline'][0]['is_today']);

        Carbon::setTestNow();
    }

    public function test_calendar_menu_link_resolves_to_kalendar(): void
    {
        $this->assertSame('/kalendar/', NavMenuBuilder::resolveLink(NavItem::LINK_CALENDAR, null));
    }

    public function test_normalize_year_and_month_fall_back_to_now(): void
    {
        $now = Carbon::parse('2026-08-13');

        $this->assertSame(2026, HomeEpisodeScheduleService::normalizeYear(null, $now));
        $this->assertSame(8, HomeEpisodeScheduleService::normalizeMonth(0, $now));
        $this->assertSame(2024, HomeEpisodeScheduleService::normalizeYear(2024, $now));
        $this->assertSame(12, HomeEpisodeScheduleService::normalizeMonth(12, $now));
    }
}
