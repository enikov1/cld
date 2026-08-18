<?php

namespace Tests\Unit;

use App\Models\Series;
use App\Services\TmdbPopularitySyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TmdbPopularitySyncServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_updates_years_premiere_duration_and_age(): void
    {
        config(['tmdb.api_key' => 'tmdb-test']);

        $series = Series::query()->create([
            'kp_id' => 'kp-tmdb-sync',
            'tmdb_id' => '1396',
            'slug' => 'breaking-bad',
            'title' => 'Во все тяжкие',
            'content_type' => 'series',
            'is_active' => true,
            'year' => 2000,
            'start_year' => null,
            'end_year' => null,
            'duration_minutes' => 43,
        ]);

        Http::fake(function ($request) {
            $url = (string) $request->url();

            if (str_contains($url, '/season/5')) {
                return Http::response([
                    'name' => 'Season 5',
                    'episodes' => [
                        ['episode_number' => 16, 'name' => 'Felina', 'air_date' => '2013-09-29', 'runtime' => 55],
                    ],
                ]);
            }

            if (str_contains($url, '/season/')) {
                return Http::response([
                    'name' => 'Season 1',
                    'episodes' => [
                        ['episode_number' => 1, 'name' => 'Pilot', 'air_date' => '2008-01-20', 'runtime' => 58],
                        ['episode_number' => 2, 'name' => 'Cat\'s in the Bag...', 'air_date' => '2008-01-27', 'runtime' => 48],
                    ],
                ]);
            }

            if (str_contains($url, '/tv/1396')) {
                return Http::response([
                    'id' => 1396,
                    'name' => 'Breaking Bad',
                    'first_air_date' => '2008-01-20',
                    'last_air_date' => '2013-09-29',
                    'status' => 'Ended',
                    'popularity' => 321.5,
                    'episode_run_time' => [47],
                    'number_of_episodes' => 62,
                    'seasons' => [
                        ['season_number' => 1],
                        ['season_number' => 5],
                    ],
                    'content_ratings' => [
                        'results' => [
                            ['iso_3166_1' => 'RU', 'rating' => '18+'],
                        ],
                    ],
                ]);
            }

            return Http::response([]);
        });

        $result = app(TmdbPopularitySyncService::class)->syncSeries($series, true, false);

        $this->assertTrue($result['updated']);
        $this->assertTrue($result['schedule_synced']);

        $series->refresh();
        $this->assertSame(2008, $series->year);
        $this->assertSame(2008, $series->start_year);
        $this->assertSame(2013, $series->end_year);
        $this->assertSame('2008-01-20', $series->premiere_date?->format('Y-m-d'));
        $this->assertSame('2013-09-29', $series->finale_date?->format('Y-m-d'));
        $this->assertSame(58 + 48 + 55, $series->duration_minutes);
        $this->assertSame('18', $series->age_limit);
        $this->assertSame('completed', $series->broadcast_status);
    }
}
