<?php

namespace Tests\Unit;

use App\Models\PlayerSource;
use App\Models\Series;
use App\Models\SiteSetting;
use App\Services\AllohaPlayerSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AllohaPlayerSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_remove_for_series_deletes_alloha_sources(): void
    {
        $series = Series::query()->create([
            'kp_id' => '999001',
            'slug' => 'alloha-remove-test',
            'title' => 'Test',
            'is_active' => true,
            'is_hidden' => false,
        ]);

        PlayerSource::query()->create([
            'series_id' => $series->id,
            'provider' => 'Dragon Money',
            'iframe_url' => 'https://example.com/player',
            'source_key' => AllohaPlayerSync::SOURCE_KEY,
            'is_active' => true,
            'priority' => 90,
        ]);

        PlayerSource::query()->create([
            'series_id' => $series->id,
            'provider' => 'Coldfilm',
            'iframe_url' => '<video-player data-title-id="999001"></video-player>',
            'source_key' => 'cdnvideohub',
            'is_active' => true,
            'priority' => 100,
        ]);

        app(AllohaPlayerSync::class)->removeForSeries($series);

        $this->assertSame(1, PlayerSource::query()->where('series_id', $series->id)->count());
        $this->assertSame(
            'cdnvideohub',
            PlayerSource::query()->where('series_id', $series->id)->value('source_key'),
        );
    }

    public function test_sync_skipped_when_disabled_in_settings(): void
    {
        SiteSetting::set('player_alloha_sync_enabled', '0');

        $series = Series::query()->create([
            'kp_id' => '999002',
            'slug' => 'alloha-disabled-test',
            'title' => 'Test',
            'is_active' => true,
            'is_hidden' => false,
        ]);

        PlayerSource::query()->create([
            'series_id' => $series->id,
            'provider' => 'Old Alloha',
            'iframe_url' => 'https://example.com/old',
            'source_key' => AllohaPlayerSync::SOURCE_KEY,
            'is_active' => true,
            'priority' => 100,
        ]);

        app(AllohaPlayerSync::class)->sync($series, [
            ['id' => 1, 'name' => 'New Voice', 'iframe' => 'https://example.com/new'],
        ]);

        $this->assertSame(0, PlayerSource::query()->where('series_id', $series->id)->count());
    }
}
