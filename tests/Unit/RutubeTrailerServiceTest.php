<?php

namespace Tests\Unit;

use App\Models\PlayerSource;
use App\Models\Series;
use App\Services\RutubeTrailerService;
use App\Support\PlayerUrlHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RutubeTrailerServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_to_embed_url_from_video_page_and_private_link(): void
    {
        $this->assertSame(
            'https://rutube.ru/play/embed/6d037bf2f83ad3f395dcfcbe05ea02c7',
            RutubeTrailerService::toEmbedUrl('https://rutube.ru/video/6d037bf2f83ad3f395dcfcbe05ea02c7/'),
        );

        $this->assertSame(
            'https://rutube.ru/play/embed/abc123?p=secretKey',
            RutubeTrailerService::toEmbedUrl('https://rutube.ru/video/private/abc123/?p=secretKey'),
        );

        $this->assertSame(
            'https://rutube.ru/play/embed/shortsId1',
            RutubeTrailerService::toEmbedUrl('https://rutube.ru/shorts/shortsId1/'),
        );
    }

    public function test_player_url_helper_normalizes_rutube_page_to_embed(): void
    {
        $normalized = PlayerUrlHelper::normalizePlayerContent(
            'https://rutube.ru/video/6d037bf2f83ad3f395dcfcbe05ea02c7/',
        );

        $this->assertSame('https://rutube.ru/play/embed/6d037bf2f83ad3f395dcfcbe05ea02c7', $normalized);
    }

    public function test_add_to_series_picks_best_trailer_and_appends_player(): void
    {
        Http::fake([
            'rutube.ru/api/search/video/*' => Http::response([
                'results' => [
                    [
                        'id' => 'fullmovie000000000000000000000001',
                        'title' => 'Эль сериал полностью',
                        'duration' => 3600,
                        'hits' => 99999,
                        'embed_url' => 'https://rutube.ru/play/embed/fullmovie000000000000000000000001',
                        'video_url' => 'https://rutube.ru/video/fullmovie000000000000000000000001/',
                    ],
                    [
                        'id' => 'trailer00000000000000000000000001',
                        'title' => 'Эль — Русский трейлер (2026)',
                        'duration' => 140,
                        'hits' => 1500,
                        'embed_url' => 'https://rutube.ru/play/embed/trailer00000000000000000000000001',
                        'video_url' => 'https://rutube.ru/video/trailer00000000000000000000000001/',
                    ],
                ],
            ]),
        ]);

        $series = Series::query()->create([
            'kp_id' => '800001',
            'slug' => 'elle-rutube-test',
            'title' => 'Эль',
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

        $result = app(RutubeTrailerService::class)->addToSeries($series);

        $this->assertTrue($result['ok']);
        $this->assertSame('trailer00000000000000000000000001', $result['trailer']['id']);

        $players = PlayerUrlHelper::activePlayersForSeries($series->fresh());
        $this->assertCount(2, $players);
        $this->assertSame('Смотреть онлайн', $players[0]['label']);
        $this->assertSame('Трейлер', $players[1]['label']);
        $this->assertSame(
            'https://rutube.ru/play/embed/trailer00000000000000000000000001',
            $players[1]['url'],
        );
        $this->assertSame(
            RutubeTrailerService::SOURCE_KEY,
            PlayerSource::query()->where('series_id', $series->id)->where('provider', 'Трейлер')->value('source_key'),
        );
    }

    public function test_add_to_series_updates_existing_rutube_trailer(): void
    {
        Http::fake([
            'rutube.ru/api/search/video/*' => Http::response([
                'results' => [[
                    'id' => 'newtrailer0000000000000000000001',
                    'title' => 'Эль трейлер',
                    'duration' => 90,
                    'hits' => 100,
                    'embed_url' => 'https://rutube.ru/play/embed/newtrailer0000000000000000000001',
                    'video_url' => 'https://rutube.ru/video/newtrailer0000000000000000000001/',
                ]],
            ]),
        ]);

        $series = Series::query()->create([
            'kp_id' => '800002',
            'slug' => 'elle-rutube-update',
            'title' => 'Эль',
            'year' => 2026,
            'is_active' => true,
            'is_hidden' => false,
        ]);

        PlayerSource::query()->create([
            'series_id' => $series->id,
            'provider' => 'Трейлер',
            'iframe_url' => 'https://rutube.ru/play/embed/oldtrailer0000000000000000000001',
            'source_key' => RutubeTrailerService::SOURCE_KEY,
            'is_active' => true,
            'priority' => 10,
        ]);

        $result = app(RutubeTrailerService::class)->addToSeries($series);
        $this->assertTrue($result['ok']);

        $this->assertSame(1, PlayerSource::query()->where('series_id', $series->id)->count());
        $this->assertSame(
            'https://rutube.ru/play/embed/newtrailer0000000000000000000001',
            PlayerSource::query()->where('series_id', $series->id)->value('iframe_url'),
        );
    }
}
