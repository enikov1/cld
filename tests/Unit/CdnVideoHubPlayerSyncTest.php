<?php

namespace Tests\Unit;

use App\Models\PlayerSource;
use App\Models\Series;
use App\Models\SiteSetting;
use App\Services\CdnVideoHubPlayerSync;
use App\Support\PlayerUrlHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CdnVideoHubPlayerSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_builds_embed_html_with_kp_id(): void
    {
        SiteSetting::set('player_cdnvideohub_publisher_id', '15');
        SiteSetting::set('player_cdnvideohub_aggregator', 'kp');
        SiteSetting::set('player_cdnvideohub_script_url', 'https://player.cdnvideohub.com/s2/stable/video-player.umd.js');

        $html = app(CdnVideoHubPlayerSync::class)->buildEmbedHtml('12892918');

        $this->assertStringContainsString('data-title-id="12892918"', $html);
        $this->assertStringContainsString('data-publisher-id="15"', $html);
        $this->assertStringContainsString('data-aggregator="kp"', $html);
        $this->assertStringContainsString('<video-player', $html);
        $this->assertStringContainsString('video-player.umd.js', $html);
    }

    public function test_sync_creates_player_source_when_enabled(): void
    {
        SiteSetting::set('player_cdnvideohub_auto_enabled', '1');
        SiteSetting::set('player_cdnvideohub_tab_name', 'Coldfilm');
        SiteSetting::set('player_cdnvideohub_priority', '100');
        SiteSetting::set('player_cdnvideohub_script_url', 'https://player.cdnvideohub.com/s2/stable/video-player.umd.js');

        $series = Series::query()->create([
            'kp_id' => '12892918',
            'slug' => 'test-cdn-player',
            'title' => 'Test Series',
            'is_active' => true,
            'is_hidden' => false,
        ]);

        app(CdnVideoHubPlayerSync::class)->syncIfEnabled($series);

        $source = PlayerSource::query()
            ->where('series_id', $series->id)
            ->where('source_key', CdnVideoHubPlayerSync::SOURCE_KEY)
            ->first();

        $this->assertNotNull($source);
        $this->assertSame('Coldfilm', $source->provider);
        $this->assertSame(100, $source->priority);
        $this->assertStringContainsString('data-title-id="12892918"', $source->iframe_url);

        $players = PlayerUrlHelper::activePlayersForSeries($series->fresh());
        $this->assertCount(1, $players);
        $this->assertTrue($players[0]['is_embed']);
    }

    public function test_sync_skipped_when_disabled(): void
    {
        SiteSetting::set('player_cdnvideohub_auto_enabled', '0');

        $series = Series::query()->create([
            'kp_id' => '12892918',
            'slug' => 'test-cdn-player-disabled',
            'title' => 'Test Series Disabled',
            'is_active' => true,
            'is_hidden' => false,
        ]);

        app(CdnVideoHubPlayerSync::class)->syncIfEnabled($series);

        $this->assertSame(0, PlayerSource::query()->where('series_id', $series->id)->count());
    }
}
