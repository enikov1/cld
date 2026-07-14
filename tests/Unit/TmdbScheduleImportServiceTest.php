<?php

namespace Tests\Unit;

use App\Models\Episode;
use App\Services\TmdbClient;
use App\Services\TmdbScheduleImportService;
use PHPUnit\Framework\TestCase;

class TmdbScheduleImportServiceTest extends TestCase
{
    private TmdbScheduleImportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TmdbScheduleImportService($this->createMock(TmdbClient::class));
    }

    public function test_maps_episode_status_by_air_date(): void
    {
        $today = '2026-07-14';

        $past = $this->service->mapEpisode([
            'episode_number' => 1,
            'name' => 'Пилот',
            'air_date' => '2026-07-01',
        ], $today);

        $future = $this->service->mapEpisode([
            'episode_number' => 2,
            'name' => 'Скоро',
            'air_date' => '2026-08-01',
        ], $today);

        $tba = $this->service->mapEpisode([
            'episode_number' => 3,
            'name' => 'TBA',
            'air_date' => null,
        ], $today);

        $this->assertSame(Episode::STATUS_RELEASED, $past['status']);
        $this->assertSame('2026-07-01', $past['release_at']);
        $this->assertSame('Пилот', $past['title']);

        $this->assertSame(Episode::STATUS_SCHEDULED, $future['status']);
        $this->assertSame('2026-08-01', $future['release_at']);

        $this->assertSame(Episode::STATUS_SCHEDULED, $tba['status']);
        $this->assertNull($tba['release_at']);
    }

    public function test_map_season_skips_invalid_episodes_and_localizes_generic_title(): void
    {
        $mapped = $this->service->mapSeasonPayload([
            'name' => 'Season 2',
            'episodes' => [
                ['episode_number' => 0, 'name' => 'Special', 'air_date' => '2020-01-01'],
                ['episode_number' => 2, 'name' => 'Вторая', 'air_date' => '2020-01-15'],
                ['episode_number' => 1, 'name' => 'Первая', 'air_date' => '2020-01-08'],
            ],
        ], 2, '2026-07-14');

        $this->assertSame(2, $mapped['season_number']);
        $this->assertSame('Сезон 2', $mapped['title']);
        $this->assertCount(2, $mapped['episodes']);
        $this->assertSame(1, $mapped['episodes'][0]['episode_number']);
        $this->assertSame(2, $mapped['episodes'][1]['episode_number']);
    }

    public function test_merge_preserves_voice_and_adds_new_episodes(): void
    {
        $existing = [
            [
                'season_number' => 1,
                'title' => 'Локальный сезон',
                'episodes' => [
                    [
                        'episode_number' => 1,
                        'title' => 'Старое название',
                        'release_at' => '2020-01-01',
                        'status' => Episode::STATUS_RELEASED,
                        'voice' => 'LostFilm',
                    ],
                ],
            ],
        ];

        $imported = [
            [
                'season_number' => 1,
                'title' => 'Сезон 1',
                'episodes' => [
                    [
                        'episode_number' => 1,
                        'title' => 'Новое название',
                        'release_at' => '2020-01-02',
                        'status' => Episode::STATUS_RELEASED,
                        'voice' => null,
                    ],
                    [
                        'episode_number' => 2,
                        'title' => 'Новая серия',
                        'release_at' => '2020-01-09',
                        'status' => Episode::STATUS_RELEASED,
                        'voice' => null,
                    ],
                ],
            ],
            [
                'season_number' => 2,
                'title' => 'Сезон 2',
                'episodes' => [
                    [
                        'episode_number' => 1,
                        'title' => 'S2E1',
                        'release_at' => null,
                        'status' => Episode::STATUS_SCHEDULED,
                        'voice' => null,
                    ],
                ],
            ],
        ];

        $merged = $this->service->mergeSchedules($existing, $imported);

        $this->assertCount(2, $merged);
        $this->assertSame('Сезон 1', $merged[0]['title']);
        $this->assertSame('Новое название', $merged[0]['episodes'][0]['title']);
        $this->assertSame('LostFilm', $merged[0]['episodes'][0]['voice']);
        $this->assertSame('2020-01-02', $merged[0]['episodes'][0]['release_at']);
        $this->assertSame(2, $merged[0]['episodes'][1]['episode_number']);
        $this->assertSame(2, $merged[1]['season_number']);
    }

    public function test_status_from_air_date(): void
    {
        $this->assertSame(Episode::STATUS_RELEASED, $this->service->statusFromAirDate('2026-07-14', '2026-07-14'));
        $this->assertSame(Episode::STATUS_SCHEDULED, $this->service->statusFromAirDate('2026-07-15', '2026-07-14'));
        $this->assertSame(Episode::STATUS_SCHEDULED, $this->service->statusFromAirDate(null, '2026-07-14'));
    }
}
